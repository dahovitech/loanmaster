<?php

declare(strict_types=1);

namespace App\Domain\Event\Loan;

use App\Domain\Event\AbstractDomainEvent;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

/**
 * Événement déclenché lors du changement de statut d'un prêt
 * Audit complet avec historique et justifications
 */
class LoanStatusChanged extends AbstractDomainEvent
{
    public function __construct(
        UuidInterface $loanId,
        string $previousStatus,
        string $newStatus,
        string $reason,
        ?UuidInterface $changedBy = null,
        ?string $comments = null,
        array $additionalData = [],
        int $version = 1
    ) {
        $payload = [
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'reason' => $reason,
            'changedBy' => $changedBy?->toString(),
            'comments' => $comments,
            'additionalData' => $additionalData,
            'statusHistory' => [
                'from' => $previousStatus,
                'to' => $newStatus,
                'timestamp' => new DateTimeImmutable(),
                'automated' => $changedBy === null
            ],
            'auditTrail' => [
                'action' => 'status_change',
                'context' => 'loan_lifecycle',
                'severity' => $this->calculateSeverity($previousStatus, $newStatus),
                'requiresNotification' => $this->requiresNotification($newStatus)
            ]
        ];
        
        parent::__construct($loanId, $payload, $version);
    }

    public function getPreviousStatus(): string
    {
        return $this->payload['previousStatus'];
    }

    public function getNewStatus(): string
    {
        return $this->payload['newStatus'];
    }

    public function getReason(): string
    {
        return $this->payload['reason'];
    }

    public function getChangedBy(): ?string
    {
        return $this->payload['changedBy'];
    }

    public function getComments(): ?string
    {
        return $this->payload['comments'];
    }

    public function getStatusHistory(): array
    {
        return $this->payload['statusHistory'];
    }

    public function getAuditTrail(): array
    {
        return $this->payload['auditTrail'];
    }

    public function isAutomated(): bool
    {
        return $this->payload['statusHistory']['automated'];
    }

    public function requiresNotification(): bool
    {
        return $this->payload['auditTrail']['requiresNotification'];
    }

    /**
     * Calcule la sévérité du changement de statut
     */
    private function calculateSeverity(string $from, string $to): string
    {
        $criticalTransitions = [
            'approved' => 'rejected',
            'active' => 'defaulted',
            'pending' => 'cancelled'
        ];
        
        if (isset($criticalTransitions[$from]) && $criticalTransitions[$from] === $to) {
            return 'critical';
        }
        
        $warningTransitions = ['pending', 'under_review', 'requires_documents'];
        if (in_array($to, $warningTransitions)) {
            return 'warning';
        }
        
        return 'info';
    }

    /**
     * Détermine si le changement nécessite une notification
     */
    private function requiresNotification(string $status): bool
    {
        $notificationStatuses = ['approved', 'rejected', 'requires_documents', 'funded', 'defaulted'];
        return in_array($status, $notificationStatuses);
    }
}
