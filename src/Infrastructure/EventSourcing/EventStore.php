<?php

namespace App\Infrastructure\EventSourcing;

use App\Domain\Event\DomainEventInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Ramsey\Uuid\UuidInterface;
use DateTime;
use DateTimeImmutable;
use JsonException;

/**
 * Event Store pour la persistance des événements
 * Implémentation optimisée pour l'Event Sourcing
 */
class EventStore
{
    private Connection $connection;
    private string $tableName;

    public function __construct(Connection $connection, string $tableName = 'event_store')
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * Append un événement au store
     */
    public function append(string $aggregateId, DomainEventInterface $event, int $expectedVersion = null): void
    {
        try {
            $this->connection->beginTransaction();

            // Vérification de la version pour éviter les conflits de concurrence
            if ($expectedVersion !== null) {
                $currentVersion = $this->getAggregateVersion($aggregateId);
                if ($currentVersion !== $expectedVersion) {
                    throw new ConcurrencyException(
                        sprintf('Expected version %d, but aggregate %s is at version %d', 
                            $expectedVersion, $aggregateId, $currentVersion)
                    );
                }
            }

            $nextVersion = $this->getAggregateVersion($aggregateId) + 1;

            $this->connection->insert($this->tableName, [
                'aggregate_id' => $aggregateId,
                'event_type' => get_class($event),
                'event_data' => json_encode($event->jsonSerialize(), JSON_THROW_ON_ERROR),
                'version' => $nextVersion,
                'occurred_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
                'metadata' => json_encode($this->getMetadata(), JSON_THROW_ON_ERROR)
            ]);

            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw new EventStoreException('Failed to append event: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère tous les événements d'un agrégat
     */
    public function getAggregateEvents(string $aggregateId, int $fromVersion = 1): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT * FROM {$this->tableName} 
                 WHERE aggregate_id = :aggregateId AND version >= :fromVersion 
                 ORDER BY version ASC"
            );
            
            $stmt->bindValue('aggregateId', $aggregateId);
            $stmt->bindValue('fromVersion', $fromVersion);
            $result = $stmt->executeQuery();
            
            $events = [];
            while ($row = $result->fetchAssociative()) {
                $events[] = $this->deserializeEvent($row);
            }
            
            return $events;
        } catch (Exception $e) {
            throw new EventStoreException('Failed to get aggregate events: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère tous les événements depuis une certaine date
     */
    public function getEventsSince(DateTimeImmutable $since): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT * FROM {$this->tableName} 
                 WHERE occurred_at >= :since 
                 ORDER BY occurred_at ASC, version ASC"
            );
            
            $stmt->bindValue('since', $since->format('Y-m-d H:i:s.u'));
            $result = $stmt->executeQuery();
            
            $events = [];
            while ($row = $result->fetchAssociative()) {
                $events[] = $this->deserializeEvent($row);
            }
            
            return $events;
        } catch (Exception $e) {
            throw new EventStoreException('Failed to get events since date: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère tous les événements d'un type spécifique
     */
    public function getEventsByType(string $eventType): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT * FROM {$this->tableName} 
                 WHERE event_type = :eventType 
                 ORDER BY occurred_at ASC"
            );
            
            $stmt->bindValue('eventType', $eventType);
            $result = $stmt->executeQuery();
            
            $events = [];
            while ($row = $result->fetchAssociative()) {
                $events[] = $this->deserializeEvent($row);
            }
            
            return $events;
        } catch (Exception $e) {
            throw new EventStoreException('Failed to get events by type: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère la version actuelle d'un agrégat
     */
    public function getAggregateVersion(string $aggregateId): int
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT MAX(version) as max_version FROM {$this->tableName} WHERE aggregate_id = :aggregateId"
            );
            
            $stmt->bindValue('aggregateId', $aggregateId);
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();
            
            return (int) ($row['max_version'] ?? 0);
        } catch (Exception $e) {
            throw new EventStoreException('Failed to get aggregate version: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Désérialise un événement depuis la base de données
     */
    private function deserializeEvent(array $row): DomainEventInterface
    {
        try {
            $eventType = $row['event_type'];
            $eventData = json_decode($row['event_data'], true, 512, JSON_THROW_ON_ERROR);
            
            // Reconstruit l'événement depuis les données JSON
            if (!class_exists($eventType)) {
                throw new EventStoreException("Event type {$eventType} does not exist");
            }
            
            $event = $eventType::fromArray($eventData);
            
            // Ajoute les métadonnées de l'event store
            if ($event instanceof StoredDomainEvent) {
                $event->setStoredMetadata([
                    'version' => $row['version'],
                    'occurred_at' => $row['occurred_at'],
                    'metadata' => json_decode($row['metadata'], true)
                ]);
            }
            
            return $event;
        } catch (JsonException $e) {
            throw new EventStoreException('Failed to deserialize event: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Génère les métadonnées pour l'événement
     */
    private function getMetadata(): array
    {
        return [
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $this->getCurrentIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'correlation_id' => $this->getCorrelationId()
        ];
    }

    private function getCurrentUserId(): ?string
    {
        // TODO: Intégrer avec le système de sécurité Symfony
        return null;
    }

    private function getCurrentIpAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function getCorrelationId(): string
    {
        // Génère ou récupère un ID de corrélation pour tracer les opérations
        return $_SERVER['HTTP_X_CORRELATION_ID'] ?? uniqid('correlation_', true);
    }
}
