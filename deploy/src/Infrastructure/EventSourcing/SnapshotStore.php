<?php

namespace App\Infrastructure\EventSourcing;

use App\Infrastructure\EventSourcing\AggregateRoot;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use DateTimeImmutable;
use JsonException;
use Ramsey\Uuid\UuidInterface;

/**
 * Store pour les snapshots d'agrégats
 * Optimise les performances en évitant de rejouer tous les événements
 */
class SnapshotStore
{
    private Connection $connection;
    private string $tableName;
    private int $snapshotFrequency;

    public function __construct(
        Connection $connection,
        string $tableName = 'aggregate_snapshots',
        int $snapshotFrequency = 10
    ) {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->snapshotFrequency = $snapshotFrequency;
    }

    /**
     * Sauvegarde un snapshot d'agrégat
     */
    public function saveSnapshot(AggregateRoot $aggregate): void
    {
        try {
            $snapshotData = $aggregate->takeSnapshot();
            
            $this->connection->executeStatement(
                "INSERT INTO {$this->tableName} 
                 (aggregate_id, aggregate_type, snapshot_data, version, created_at) 
                 VALUES (:aggregateId, :aggregateType, :snapshotData, :version, :createdAt)
                 ON DUPLICATE KEY UPDATE 
                 snapshot_data = :snapshotData, version = :version, created_at = :createdAt",
                [
                    'aggregateId' => $aggregate->getId()->toString(),
                    'aggregateType' => get_class($aggregate),
                    'snapshotData' => json_encode($snapshotData, JSON_THROW_ON_ERROR),
                    'version' => $aggregate->getVersion(),
                    'createdAt' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u')
                ]
            );
        } catch (Exception|JsonException $e) {
            throw new EventStoreException('Failed to save snapshot: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Charge un snapshot d'agrégat
     */
    public function loadSnapshot(UuidInterface $aggregateId, string $aggregateType): ?array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT snapshot_data, version FROM {$this->tableName} 
                 WHERE aggregate_id = :aggregateId AND aggregate_type = :aggregateType"
            );
            
            $stmt->bindValue('aggregateId', $aggregateId->toString());
            $stmt->bindValue('aggregateType', $aggregateType);
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();
            
            if (!$row) {
                return null;
            }
            
            return [
                'data' => json_decode($row['snapshot_data'], true, 512, JSON_THROW_ON_ERROR),
                'version' => (int) $row['version']
            ];
        } catch (Exception|JsonException $e) {
            throw new EventStoreException('Failed to load snapshot: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Vérifie si un snapshot doit être créé
     */
    public function shouldTakeSnapshot(AggregateRoot $aggregate): bool
    {
        $currentVersion = $aggregate->getVersion();
        
        // Prend un snapshot tous les N événements
        if ($currentVersion % $this->snapshotFrequency === 0) {
            return true;
        }
        
        // Vérifie s'il y a déjà un snapshot récent
        try {
            $stmt = $this->connection->prepare(
                "SELECT version FROM {$this->tableName} 
                 WHERE aggregate_id = :aggregateId AND aggregate_type = :aggregateType"
            );
            
            $stmt->bindValue('aggregateId', $aggregate->getId()->toString());
            $stmt->bindValue('aggregateType', get_class($aggregate));
            $result = $stmt->executeQuery();
            $row = $result->fetchAssociative();
            
            if (!$row) {
                return true; // Pas de snapshot existant
            }
            
            $lastSnapshotVersion = (int) $row['version'];
            $eventsSinceSnapshot = $currentVersion - $lastSnapshotVersion;
            
            return $eventsSinceSnapshot >= $this->snapshotFrequency;
            
        } catch (Exception $e) {
            // En cas d'erreur, on prend le snapshot par sécurité
            return true;
        }
    }

    /**
     * Supprime les anciens snapshots
     */
    public function cleanupOldSnapshots(DateTimeImmutable $olderThan): int
    {
        try {
            return $this->connection->delete(
                $this->tableName,
                ['created_at <' => $olderThan->format('Y-m-d H:i:s.u')]
            );
        } catch (Exception $e) {
            throw new EventStoreException('Failed to cleanup old snapshots: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère les statistiques des snapshots
     */
    public function getSnapshotStatistics(): array
    {
        try {
            $stmt = $this->connection->prepare(
                "SELECT 
                    COUNT(*) as total_snapshots,
                    COUNT(DISTINCT aggregate_type) as unique_types,
                    AVG(version) as avg_version,
                    MIN(created_at) as oldest_snapshot,
                    MAX(created_at) as newest_snapshot
                 FROM {$this->tableName}"
            );
            
            $result = $stmt->executeQuery();
            return $result->fetchAssociative() ?: [];
        } catch (Exception $e) {
            error_log('Failed to get snapshot statistics: ' . $e->getMessage());
            return [];
        }
    }
}
