<?php

namespace App\Infrastructure\EventSourcing\EventHandler;

use App\Domain\Event\Loan\LoanApplicationCreated;
use App\Domain\Event\Loan\LoanStatusChanged;
use App\Domain\Event\Loan\LoanFunded;
use App\Domain\Event\Loan\LoanPaymentReceived;
use App\Domain\Event\Loan\LoanRiskAssessed;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use DateTimeImmutable;

/**
 * Handler pour mettre à jour les projections des prêts
 * Maintient les vues matérialisées à jour
 */
class LoanProjectionHandler
{
    private Connection $connection;
    private string $tableName;

    public function __construct(Connection $connection, string $tableName = 'loan_projections')
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    #[AsMessageHandler]
    public function handleLoanApplicationCreated(LoanApplicationCreated $event): void
    {
        try {
            $this->connection->insert($this->tableName, [
                'loan_id' => $event->getAggregateId(),
                'customer_id' => $event->getCustomerId(),
                'status' => 'pending',
                'requested_amount' => $event->getRequestedAmount(),
                'approved_amount' => 0,
                'current_balance' => 0,
                'interest_rate' => 5.0, // Taux par défaut
                'risk_score' => 0,
                'risk_level' => null,
                'created_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u'),
                'updated_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u'),
                'funded_at' => null,
                'completed_at' => null
            ]);
        } catch (Exception $e) {
            // Log l'erreur mais ne fait pas échouer le traitement
            error_log('Failed to update loan projection: ' . $e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handleLoanStatusChanged(LoanStatusChanged $event): void
    {
        try {
            $updateData = [
                'status' => $event->getNewStatus(),
                'updated_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u')
            ];

            // Ajout de timestamps spécifiques selon le statut
            if ($event->getNewStatus() === 'funded') {
                $updateData['funded_at'] = $event->getOccurredOn()->format('Y-m-d H:i:s.u');
            } elseif ($event->getNewStatus() === 'completed') {
                $updateData['completed_at'] = $event->getOccurredOn()->format('Y-m-d H:i:s.u');
            }

            $this->connection->update(
                $this->tableName,
                $updateData,
                ['loan_id' => $event->getAggregateId()]
            );
        } catch (Exception $e) {
            error_log('Failed to update loan status projection: ' . $e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handleLoanRiskAssessed(LoanRiskAssessed $event): void
    {
        try {
            $this->connection->update(
                $this->tableName,
                [
                    'risk_score' => $event->getRiskScore(),
                    'risk_level' => $event->getRiskLevel(),
                    'interest_rate' => 5.0 + $event->getInterestRateAdjustment(), // Mise à jour du taux
                    'updated_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u')
                ],
                ['loan_id' => $event->getAggregateId()]
            );
        } catch (Exception $e) {
            error_log('Failed to update loan risk projection: ' . $e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handleLoanFunded(LoanFunded $event): void
    {
        try {
            $this->connection->update(
                $this->tableName,
                [
                    'approved_amount' => $event->getFundedAmount(),
                    'current_balance' => $event->getFundedAmount(),
                    'status' => 'active',
                    'funded_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u'),
                    'updated_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u')
                ],
                ['loan_id' => $event->getAggregateId()]
            );
        } catch (Exception $e) {
            error_log('Failed to update loan funding projection: ' . $e->getMessage());
        }
    }

    #[AsMessageHandler]
    public function handleLoanPaymentReceived(LoanPaymentReceived $event): void
    {
        try {
            // Récupération du solde actuel
            $stmt = $this->connection->prepare(
                "SELECT current_balance FROM {$this->tableName} WHERE loan_id = :loanId"
            );
            $stmt->bindValue('loanId', $event->getAggregateId());
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();
            
            if ($row) {
                $newBalance = $row['current_balance'] - $event->getPrincipalPaid();
                
                $updateData = [
                    'current_balance' => max(0, $newBalance), // Ne peut pas être négatif
                    'updated_at' => $event->getOccurredOn()->format('Y-m-d H:i:s.u')
                ];
                
                // Si le prêt est complètement remboursé
                if ($newBalance <= 0) {
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = $event->getOccurredOn()->format('Y-m-d H:i:s.u');
                }
                
                $this->connection->update(
                    $this->tableName,
                    $updateData,
                    ['loan_id' => $event->getAggregateId()]
                );
            }
        } catch (Exception $e) {
            error_log('Failed to update loan payment projection: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les statistiques depuis les projections
     */
    public function getLoanStatistics(): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_loans,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_loans,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_loans,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_loans,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_loans,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_loans,
                    COUNT(CASE WHEN status = 'defaulted' THEN 1 END) as defaulted_loans,
                    AVG(requested_amount) as avg_requested_amount,
                    SUM(approved_amount) as total_approved_amount,
                    SUM(current_balance) as total_outstanding_balance,
                    AVG(risk_score) as avg_risk_score
                FROM {$this->tableName}
            ";
            
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery();
            
            return $result->fetchAssociative() ?: [];
        } catch (Exception $e) {
            error_log('Failed to get loan statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les prêts d'un client
     */
    public function getLoansByCustomer(string $customerId): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT * FROM {$this->tableName} WHERE customer_id = :customerId ORDER BY created_at DESC"
            );
            $stmt->bindValue('customerId', $customerId);
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (Exception $e) {
            error_log('Failed to get loans by customer: ' . $e->getMessage());
            return [];
        }
    }
}
