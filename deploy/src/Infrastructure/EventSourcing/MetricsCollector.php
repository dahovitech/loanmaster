<?php

namespace App\Infrastructure\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use DateTimeImmutable;
use JsonException;

/**
 * Collecteur de métriques pour l'Event Sourcing
 * Surveillance des performances et monitoring
 */
class MetricsCollector
{
    private Connection $connection;
    private string $tableName;
    private array $metricsBuffer = [];
    private int $bufferSize;

    public function __construct(
        Connection $connection,
        string $tableName = 'event_store_metrics',
        int $bufferSize = 100
    ) {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->bufferSize = $bufferSize;
    }

    /**
     * Enregistre une métrique
     */
    public function recordMetric(string $metricName, float $value, array $tags = []): void
    {
        $this->metricsBuffer[] = [
            'metric_name' => $metricName,
            'metric_value' => $value,
            'tags' => $tags,
            'recorded_at' => new DateTimeImmutable()
        ];
        
        // Flush automatique si le buffer est plein
        if (count($this->metricsBuffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    /**
     * Enregistre le temps d'exécution d'une opération
     */
    public function recordExecutionTime(string $operation, float $startTime, array $tags = []): void
    {
        $executionTime = microtime(true) - $startTime;
        $this->recordMetric(
            'execution_time',
            $executionTime,
            array_merge($tags, ['operation' => $operation])
        );
    }

    /**
     * Enregistre un compteur
     */
    public function incrementCounter(string $counterName, array $tags = [], int $increment = 1): void
    {
        $this->recordMetric(
            'counter_' . $counterName,
            $increment,
            $tags
        );
    }

    /**
     * Enregistre une jauge (valeur instantanée)
     */
    public function recordGauge(string $gaugeName, float $value, array $tags = []): void
    {
        $this->recordMetric(
            'gauge_' . $gaugeName,
            $value,
            $tags
        );
    }

    /**
     * Flush les métriques en base de données
     */
    public function flush(): void
    {
        if (empty($this->metricsBuffer)) {
            return;
        }
        
        try {
            $this->connection->beginTransaction();
            
            foreach ($this->metricsBuffer as $metric) {
                $this->connection->insert($this->tableName, [
                    'metric_name' => $metric['metric_name'],
                    'metric_value' => $metric['metric_value'],
                    'tags' => json_encode($metric['tags'], JSON_THROW_ON_ERROR),
                    'recorded_at' => $metric['recorded_at']->format('Y-m-d H:i:s.u')
                ]);
            }
            
            $this->connection->commit();
            $this->metricsBuffer = [];
            
        } catch (Exception|JsonException $e) {
            $this->connection->rollBack();
            error_log('Failed to flush metrics: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les métriques pour une période donnée
     */
    public function getMetrics(
        string $metricName,
        DateTimeImmutable $since,
        DateTimeImmutable $until,
        array $tags = []
    ): array {
        try {
            $sql = "SELECT * FROM {$this->tableName} 
                    WHERE metric_name = :metricName 
                    AND recorded_at BETWEEN :since AND :until";
            
            $params = [
                'metricName' => $metricName,
                'since' => $since->format('Y-m-d H:i:s.u'),
                'until' => $until->format('Y-m-d H:i:s.u')
            ];
            
            // Filtrage par tags si spécifié
            if (!empty($tags)) {
                $tagConditions = [];
                foreach ($tags as $key => $value) {
                    $tagConditions[] = "JSON_EXTRACT(tags, '$.{$key}') = :tag_{$key}";
                    $params["tag_{$key}"] = $value;
                }
                $sql .= ' AND ' . implode(' AND ', $tagConditions);
            }
            
            $sql .= ' ORDER BY recorded_at ASC';
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->executeQuery();
            return $result->fetchAllAssociative();
            
        } catch (Exception $e) {
            error_log('Failed to get metrics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcule des statistiques sur les métriques
     */
    public function getMetricStatistics(
        string $metricName,
        DateTimeImmutable $since,
        DateTimeImmutable $until,
        array $tags = []
    ): array {
        try {
            $sql = "SELECT 
                        COUNT(*) as count,
                        AVG(metric_value) as average,
                        MIN(metric_value) as minimum,
                        MAX(metric_value) as maximum,
                        SUM(metric_value) as total,
                        STDDEV(metric_value) as stddev
                    FROM {$this->tableName} 
                    WHERE metric_name = :metricName 
                    AND recorded_at BETWEEN :since AND :until";
            
            $params = [
                'metricName' => $metricName,
                'since' => $since->format('Y-m-d H:i:s.u'),
                'until' => $until->format('Y-m-d H:i:s.u')
            ];
            
            // Filtrage par tags si spécifié
            if (!empty($tags)) {
                $tagConditions = [];
                foreach ($tags as $key => $value) {
                    $tagConditions[] = "JSON_EXTRACT(tags, '$.{$key}') = :tag_{$key}";
                    $params["tag_{$key}"] = $value;
                }
                $sql .= ' AND ' . implode(' AND ', $tagConditions);
            }
            
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->executeQuery();
            return $result->fetchAssociative() ?: [];
            
        } catch (Exception $e) {
            error_log('Failed to get metric statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Nettoie les anciennes métriques
     */
    public function cleanupOldMetrics(DateTimeImmutable $olderThan): int
    {
        try {
            return $this->connection->delete(
                $this->tableName,
                ['recorded_at <' => $olderThan->format('Y-m-d H:i:s.u')]
            );
        } catch (Exception $e) {
            error_log('Failed to cleanup old metrics: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Destructeur - s'assure que les métriques sont flushées
     */
    public function __destruct()
    {
        $this->flush();
    }
}
