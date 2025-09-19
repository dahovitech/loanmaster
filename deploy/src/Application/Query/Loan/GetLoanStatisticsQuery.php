<?php

namespace App\Application\Query\Loan;

use App\Infrastructure\EventSourcing\Query\QueryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Requête pour récupérer les statistiques des prêts
 */
class GetLoanStatisticsQuery implements QueryInterface
{
    private ?string $status;
    private ?string $riskLevel;
    private ?\DateTimeImmutable $since;
    private ?\DateTimeImmutable $until;

    public function __construct(
        ?string $status = null,
        ?string $riskLevel = null,
        ?\DateTimeImmutable $since = null,
        ?\DateTimeImmutable $until = null
    ) {
        $this->status = $status;
        $this->riskLevel = $riskLevel;
        $this->since = $since;
        $this->until = $until;
    }

    public function execute(Connection $connection): array
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
                    AVG(risk_score) as avg_risk_score,
                    MIN(created_at) as earliest_loan,
                    MAX(created_at) as latest_loan
                FROM loan_projections
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($this->status) {
                $sql .= ' AND status = :status';
                $params['status'] = $this->status;
            }
            
            if ($this->riskLevel) {
                $sql .= ' AND risk_level = :riskLevel';
                $params['riskLevel'] = $this->riskLevel;
            }
            
            if ($this->since) {
                $sql .= ' AND created_at >= :since';
                $params['since'] = $this->since->format('Y-m-d H:i:s');
            }
            
            if ($this->until) {
                $sql .= ' AND created_at <= :until';
                $params['until'] = $this->until->format('Y-m-d H:i:s');
            }
            
            $stmt = $connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->executeQuery();
            $stats = $result->fetchAssociative();
            
            // Ajout de statistiques supplémentaires
            $stats['approval_rate'] = $stats['total_loans'] > 0 ? 
                ($stats['approved_loans'] / $stats['total_loans']) * 100 : 0;
            
            $stats['completion_rate'] = $stats['active_loans'] > 0 ? 
                ($stats['completed_loans'] / ($stats['active_loans'] + $stats['completed_loans'])) * 100 : 0;
            
            $stats['default_rate'] = $stats['total_loans'] > 0 ? 
                ($stats['defaulted_loans'] / $stats['total_loans']) * 100 : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to execute loan statistics query: ' . $e->getMessage(), 0, $e);
        }
    }
}
