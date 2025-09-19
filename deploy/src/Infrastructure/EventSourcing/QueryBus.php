<?php

namespace App\Infrastructure\EventSourcing;

use App\Infrastructure\EventSourcing\Query\QueryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Query Bus pour les projections et vues matérialisées
 */
class QueryBus
{
    private Connection $connection;
    private MetricsCollector $metricsCollector;

    public function __construct(
        Connection $connection,
        MetricsCollector $metricsCollector = null
    ) {
        $this->connection = $connection;
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Exécute une requête
     */
    public function execute(QueryInterface $query): mixed
    {
        $startTime = microtime(true);
        $queryName = get_class($query);
        
        try {
            $result = $query->execute($this->connection);
            
            // Métriques de performance
            $executionTime = microtime(true) - $startTime;
            $this->metricsCollector?->recordMetric(
                'query_execution_time',
                $executionTime,
                ['query_type' => $queryName, 'status' => 'success']
            );
            
            return $result;
            
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Métriques d'échec
            $this->metricsCollector?->recordMetric(
                'query_execution_time',
                $executionTime,
                ['query_type' => $queryName, 'status' => 'failed']
            );
            
            throw $e;
        }
    }
}
