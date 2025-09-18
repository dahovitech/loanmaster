<?php

namespace App\Infrastructure\EventSourcing;

use App\Domain\Event\DomainEventInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use DateTimeImmutable;
use JsonException;

/**
 * Service d'audit complet pour l'Event Sourcing
 * Fournit des capacités d'audit détaillées et conformité RGPD
 */
class AuditService
{
    private Connection $connection;
    private EventStore $eventStore;
    private string $auditTableName;

    public function __construct(
        Connection $connection,
        EventStore $eventStore,
        string $auditTableName = 'audit_trail'
    ) {
        $this->connection = $connection;
        $this->eventStore = $eventStore;
        $this->auditTableName = $auditTableName;
    }

    /**
     * Enregistre une entrée d'audit
     */
    public function recordAuditEntry(
        string $entityType,
        string $entityId,
        string $eventType,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $correlationId = null,
        ?array $context = null
    ): void {
        try {
            $this->connection->insert($this->auditTableName, [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'event_type' => $eventType,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_THROW_ON_ERROR) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_THROW_ON_ERROR) : null,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'correlation_id' => $correlationId ?? $this->generateCorrelationId(),
                'occurred_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
                'context' => $context ? json_encode($context, JSON_THROW_ON_ERROR) : null
            ]);
        } catch (Exception|JsonException $e) {
            // Log mais ne fait pas échouer l'opération principale
            error_log('Failed to record audit entry: ' . $e->getMessage());
        }
    }

    /**
     * Récupère l'historique d'audit pour une entité
     */
    public function getAuditHistory(
        string $entityType,
        string $entityId,
        ?DateTimeImmutable $since = null,
        ?DateTimeImmutable $until = null,
        int $limit = 100
    ): array {
        try {
            $sql = "SELECT * FROM {$this->auditTableName} WHERE entity_type = :entityType AND entity_id = :entityId";
            $params = [
                'entityType' => $entityType,
                'entityId' => $entityId
            ];
            
            if ($since) {
                $sql .= ' AND occurred_at >= :since';
                $params['since'] = $since->format('Y-m-d H:i:s.u');
            }
            
            if ($until) {
                $sql .= ' AND occurred_at <= :until';
                $params['until'] = $until->format('Y-m-d H:i:s.u');
            }
            
            $sql .= ' ORDER BY occurred_at DESC LIMIT :limit';
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (Exception $e) {
            error_log('Failed to get audit history: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère l'audit trail par utilisateur (pour conformité RGPD)
     */
    public function getAuditByUser(
        string $userId,
        ?DateTimeImmutable $since = null,
        ?DateTimeImmutable $until = null
    ): array {
        try {
            $sql = "SELECT * FROM {$this->auditTableName} WHERE user_id = :userId";
            $params = ['userId' => $userId];
            
            if ($since) {
                $sql .= ' AND occurred_at >= :since';
                $params['since'] = $since->format('Y-m-d H:i:s.u');
            }
            
            if ($until) {
                $sql .= ' AND occurred_at <= :until';
                $params['until'] = $until->format('Y-m-d H:i:s.u');
            }
            
            $sql .= ' ORDER BY occurred_at DESC';
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (Exception $e) {
            error_log('Failed to get audit by user: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprime les données d'audit d'un utilisateur (pour conformité RGPD - droit à l'oubli)
     */
    public function deleteUserAuditData(string $userId): int
    {
        try {
            return $this->connection->delete($this->auditTableName, ['user_id' => $userId]);
        } catch (Exception $e) {
            error_log('Failed to delete user audit data: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Anonymise les données d'audit d'un utilisateur
     */
    public function anonymizeUserAuditData(string $userId): int
    {
        try {
            return $this->connection->update(
                $this->auditTableName,
                [
                    'user_id' => 'anonymized_' . substr(md5($userId), 0, 8),
                    'ip_address' => '0.0.0.0',
                    'user_agent' => 'anonymized'
                ],
                ['user_id' => $userId]
            );
        } catch (Exception $e) {
            error_log('Failed to anonymize user audit data: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Génère un rapport d'audit pour une période donnée
     */
    public function generateAuditReport(
        DateTimeImmutable $since,
        DateTimeImmutable $until,
        ?string $entityType = null
    ): array {
        try {
            $sql = "
                SELECT 
                    entity_type,
                    event_type,
                    COUNT(*) as event_count,
                    COUNT(DISTINCT entity_id) as unique_entities,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(occurred_at) as first_event,
                    MAX(occurred_at) as last_event
                FROM {$this->auditTableName} 
                WHERE occurred_at BETWEEN :since AND :until
            ";
            
            $params = [
                'since' => $since->format('Y-m-d H:i:s.u'),
                'until' => $until->format('Y-m-d H:i:s.u')
            ];
            
            if ($entityType) {
                $sql .= ' AND entity_type = :entityType';
                $params['entityType'] = $entityType;
            }
            
            $sql .= ' GROUP BY entity_type, event_type ORDER BY event_count DESC';
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (Exception $e) {
            error_log('Failed to generate audit report: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Reconstitue l'état d'une entité à un moment donné
     */
    public function reconstructEntityState(
        string $entityType,
        string $entityId,
        DateTimeImmutable $pointInTime
    ): ?array {
        try {
            // Récupère tous les événements jusqu'à ce point dans le temps
            $events = $this->eventStore->getAggregateEvents($entityId);
            
            $state = null;
            foreach ($events as $event) {
                if ($event->getOccurredOn() <= $pointInTime) {
                    // Applique l'événement à l'état
                    $state = $this->applyEventToState($state, $event);
                } else {
                    break; // On a dépassé le point dans le temps
                }
            }
            
            return $state;
        } catch (Exception $e) {
            error_log('Failed to reconstruct entity state: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Applique un événement à un état (simplifié)
     */
    private function applyEventToState(?array $state, DomainEventInterface $event): array
    {
        if ($state === null) {
            $state = [
                'id' => $event->getAggregateId(),
                'version' => 0,
                'events' => []
            ];
        }
        
        $state['version'] = $event->getVersion();
        $state['events'][] = [
            'type' => $event->getEventName(),
            'payload' => $event->getPayload(),
            'occurred_at' => $event->getOccurredOn()->format(DATE_ATOM)
        ];
        
        return $state;
    }

    /**
     * Génère un ID de corrélation unique
     */
    private function generateCorrelationId(): string
    {
        return 'audit_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
}
