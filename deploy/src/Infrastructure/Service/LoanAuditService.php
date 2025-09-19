<?php

namespace App\Infrastructure\Service;

use App\Domain\Loan\Loan;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Service de logging spécialisé pour les opérations de prêts
 */
class LoanAuditService
{
    public function __construct(
        private LoggerInterface $loanLogger,
        private LoggerInterface $auditLogger,
        private MonitoringService $monitoringService,
        private Security $security
    ) {}

    /**
     * Log la création d'un prêt
     */
    public function logLoanCreated(Loan $loan): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_created',
            'loan_id' => $loan->getId(),
            'loan_amount' => $loan->getAmount(),
            'loan_type' => $loan->getType(),
            'borrower_id' => $loan->getBorrowerId(),
            'user_id' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'loan_data' => [
                'amount' => $loan->getAmount(),
                'interest_rate' => $loan->getInterestRate(),
                'duration' => $loan->getDuration(),
                'purpose' => $loan->getPurpose() ?? null
            ]
        ];

        $this->loanLogger->info('Nouveau prêt créé', $context);
        $this->auditLogger->info('Audit: Création de prêt', $context);
        
        $this->monitoringService->monitorLoanEvent('created', $context['loan_data'], $context['user_id']);
        $this->monitoringService->incrementCounter('loans.created');
        $this->monitoringService->trackValue('loans.amount.created', $loan->getAmount());
    }

    /**
     * Log la modification du statut d'un prêt
     */
    public function logLoanStatusChanged(Loan $loan, string $previousStatus, string $newStatus, ?string $reason = null): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_status_changed',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'user_id' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'loan_amount' => $loan->getAmount()
        ];

        $this->loanLogger->info("Statut de prêt modifié: {$previousStatus} → {$newStatus}", $context);
        $this->auditLogger->info('Audit: Changement de statut de prêt', $context);
        
        $this->monitoringService->monitorLoanEvent("status_changed_to_{$newStatus}", [
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'loan_id' => $loan->getId(),
            'amount' => $loan->getAmount()
        ], $context['user_id']);
        
        $this->monitoringService->incrementCounter("loans.status.{$newStatus}");
        
        // Métriques spéciales pour les statuts critiques
        if (in_array($newStatus, ['approved', 'rejected', 'defaulted'])) {
            $this->monitoringService->incrementCounter("loans.{$newStatus}");
            
            if ($newStatus === 'approved') {
                $this->monitoringService->trackValue('loans.amount.approved', $loan->getAmount());
            }
        }
    }

    /**
     * Log l'approbation d'un prêt
     */
    public function logLoanApproved(Loan $loan, array $approvalData = []): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_approved',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'loan_amount' => $loan->getAmount(),
            'approver_id' => $user?->getUserIdentifier(),
            'approval_data' => $approvalData,
            'timestamp' => new \DateTime()
        ];

        $this->loanLogger->info('Prêt approuvé', $context);
        $this->auditLogger->info('Audit: Approbation de prêt', $context);
        
        $this->monitoringService->monitorLoanEvent('approved', array_merge([
            'loan_id' => $loan->getId(),
            'amount' => $loan->getAmount()
        ], $approvalData), $context['approver_id']);
        
        $this->monitoringService->logBusinessEvent('loan_approval_completed', [
            'loan_id' => $loan->getId(),
            'amount' => $loan->getAmount(),
            'approver' => $user?->getUserIdentifier()
        ]);
    }

    /**
     * Log le rejet d'un prêt
     */
    public function logLoanRejected(Loan $loan, string $reason): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_rejected',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'loan_amount' => $loan->getAmount(),
            'rejection_reason' => $reason,
            'rejector_id' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime()
        ];

        $this->loanLogger->warning('Prêt rejeté', $context);
        $this->auditLogger->info('Audit: Rejet de prêt', $context);
        
        $this->monitoringService->monitorLoanEvent('rejected', [
            'loan_id' => $loan->getId(),
            'amount' => $loan->getAmount(),
            'reason' => $reason
        ], $context['rejector_id']);
        
        $this->monitoringService->incrementCounter('loans.rejected');
        $this->monitoringService->trackValue('loans.amount.rejected', $loan->getAmount());
    }

    /**
     * Log les paiements de prêt
     */
    public function logLoanPayment(Loan $loan, float $amount, string $paymentMethod, array $paymentData = []): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_payment',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'payment_amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_data' => $paymentData,
            'processed_by' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'remaining_balance' => $loan->getRemainingBalance() ?? null
        ];

        $this->loanLogger->info('Paiement de prêt reçu', $context);
        $this->auditLogger->info('Audit: Paiement de prêt', $context);
        
        $this->monitoringService->monitorPaymentEvent('received', [
            'loan_id' => $loan->getId(),
            'amount' => $amount,
            'method' => $paymentMethod
        ], $context['processed_by']);
        
        $this->monitoringService->incrementCounter('payments.received');
        $this->monitoringService->trackValue('payments.amount.received', $amount);
    }

    /**
     * Log les échéances manquées
     */
    public function logMissedPayment(Loan $loan, \DateTime $dueDate, float $missedAmount): void
    {
        $context = [
            'action' => 'missed_payment',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'due_date' => $dueDate->format('Y-m-d'),
            'missed_amount' => $missedAmount,
            'days_overdue' => (new \DateTime())->diff($dueDate)->days,
            'timestamp' => new \DateTime()
        ];

        $this->loanLogger->warning('Échéance manquée détectée', $context);
        $this->auditLogger->warning('Audit: Échéance manquée', $context);
        
        $this->monitoringService->monitorLoanEvent('payment_missed', [
            'loan_id' => $loan->getId(),
            'amount' => $missedAmount,
            'days_overdue' => $context['days_overdue']
        ]);
        
        $this->monitoringService->incrementCounter('payments.missed');
        $this->monitoringService->trackValue('payments.amount.missed', $missedAmount);
    }

    /**
     * Log les modifications de prêt
     */
    public function logLoanModified(Loan $loan, array $changes): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_modified',
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'changes' => $changes,
            'modified_by' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime()
        ];

        $this->loanLogger->info('Prêt modifié', $context);
        $this->auditLogger->info('Audit: Modification de prêt', $context);
        
        $this->monitoringService->monitorLoanEvent('modified', [
            'loan_id' => $loan->getId(),
            'changes' => array_keys($changes)
        ], $context['modified_by']);
        
        $this->monitoringService->incrementCounter('loans.modified');
    }

    /**
     * Log l'accès aux données de prêt (RGPD)
     */
    public function logLoanDataAccess(Loan $loan, string $accessType = 'view'): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'loan_data_access',
            'access_type' => $accessType,
            'loan_id' => $loan->getId(),
            'borrower_id' => $loan->getBorrowerId(),
            'accessor_id' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $this->auditLogger->info('Audit: Accès aux données de prêt', $context);
        
        $this->monitoringService->monitorSecurityEvent('data_access', [
            'resource_type' => 'loan',
            'resource_id' => $loan->getId(),
            'access_type' => $accessType
        ], $context['accessor_id']);
        
        $this->monitoringService->incrementCounter("data_access.loan.{$accessType}");
    }

    /**
     * Log l'export de données (RGPD)
     */
    public function logDataExport(string $dataType, array $exportedIds, string $format = 'json'): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'data_export',
            'data_type' => $dataType,
            'exported_count' => count($exportedIds),
            'format' => $format,
            'exported_by' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'exported_ids' => $exportedIds
        ];

        $this->auditLogger->info('Audit: Export de données', $context);
        
        $this->monitoringService->monitorSecurityEvent('data_export', [
            'type' => $dataType,
            'count' => count($exportedIds),
            'format' => $format
        ], $context['exported_by']);
        
        $this->monitoringService->incrementCounter("data_export.{$dataType}");
    }

    /**
     * Log la suppression de données (RGPD)
     */
    public function logDataDeletion(string $dataType, array $deletedIds, string $reason = 'user_request'): void
    {
        $user = $this->security->getUser();
        
        $context = [
            'action' => 'data_deletion',
            'data_type' => $dataType,
            'deleted_count' => count($deletedIds),
            'reason' => $reason,
            'deleted_by' => $user?->getUserIdentifier(),
            'timestamp' => new \DateTime(),
            'deleted_ids' => $deletedIds
        ];

        $this->auditLogger->warning('Audit: Suppression de données', $context);
        
        $this->monitoringService->monitorSecurityEvent('data_deletion', [
            'type' => $dataType,
            'count' => count($deletedIds),
            'reason' => $reason
        ], $context['deleted_by']);
        
        $this->monitoringService->incrementCounter("data_deletion.{$dataType}");
    }

    /**
     * Génère un rapport d'audit pour un prêt spécifique
     */
    public function generateLoanAuditReport(string $loanId): array
    {
        // Cette méthode nécessiterait l'accès à une base de données ou un stockage de logs
        // Pour l'instant, on retourne la structure du rapport
        
        return [
            'loan_id' => $loanId,
            'generated_at' => new \DateTime(),
            'events' => [
                // Les événements seraient récupérés depuis les logs
            ],
            'summary' => [
                'total_events' => 0,
                'status_changes' => 0,
                'payments' => 0,
                'modifications' => 0,
                'data_accesses' => 0
            ]
        ];
    }
}
