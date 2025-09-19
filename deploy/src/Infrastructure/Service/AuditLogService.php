<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'audit et de logs pour les workflows
 */
class AuditLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Log d'une transition de workflow
     */
    public function logWorkflowTransition(
        string $entityType,
        int $entityId,
        string $fromState,
        string $transition
    ): void {
        $this->logger->info('Workflow transition logged', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_state' => $fromState,
            'transition' => $transition,
            'timestamp' => new \DateTimeImmutable()
        ]);

        // Ici on pourrait persister dans une table d'audit dédiée
        // $auditLog = new AuditLog($entityType, $entityId, $fromState, $transition);
        // $this->entityManager->persist($auditLog);
        // $this->entityManager->flush();
    }

    /**
     * Log d'un changement de statut de prêt
     */
    public function logLoanStatusChange(array $context): void
    {
        $this->logger->info('Loan status change audit', $context);

        // Persistance en base pour audit complet
        // Implémenter selon les besoins de compliance
    }

    /**
     * Log des actions administrateur
     */
    public function logAdminAction(
        string $action,
        int $adminId,
        array $context = []
    ): void {
        $this->logger->warning('Admin action performed', [
            'action' => $action,
            'admin_id' => $adminId,
            'context' => $context,
            'timestamp' => new \DateTimeImmutable()
        ]);
    }

    /**
     * Log des erreurs de workflow
     */
    public function logWorkflowError(
        string $entityType,
        int $entityId,
        string $error,
        array $context = []
    ): void {
        $this->logger->error('Workflow error', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'error' => $error,
            'context' => $context
        ]);
    }

    /**
     * Récupère l'historique d'audit pour une entité
     */
    public function getAuditHistory(string $entityType, int $entityId): array
    {
        // Récupération depuis la base de données
        // Pour l'instant, retourne un tableau vide
        return [];
    }
}
