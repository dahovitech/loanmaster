<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Redis;
use RedisCluster;

/**
 * Service de configuration et monitoring Redis avancé
 */
class RedisConfigurationService
{
    private ?Redis $redis = null;
    private ?RedisCluster $cluster = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $redisUrl,
        private readonly bool $clusterMode = false,
        private readonly array $clusterNodes = []
    ) {}

    /**
     * Initialise la connexion Redis avec configuration optimale
     */
    public function initializeConnection(): bool
    {
        try {
            if ($this->clusterMode) {
                $this->initializeCluster();
            } else {
                $this->initializeSingle();
            }

            $this->optimizeRedisConfiguration();
            
            $this->logger->info('Redis connection initialized successfully', [
                'mode' => $this->clusterMode ? 'cluster' : 'single',
                'url' => $this->redisUrl
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize Redis connection', [
                'error' => $e->getMessage(),
                'url' => $this->redisUrl
            ]);

            return false;
        }
    }

    /**
     * Configure Redis pour les performances optimales
     */
    public function optimizeRedisConfiguration(): void
    {
        if (!$this->redis && !$this->cluster) {
            return;
        }

        $connection = $this->redis ?? $this->cluster;

        try {
            // Configuration des timeouts
            $connection->setOption(Redis::OPT_READ_TIMEOUT, 5);
            $connection->setOption(Redis::OPT_TCP_KEEPALIVE, 1);

            // Configuration de la sérialisation
            $connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);

            // Configuration de la compression
            $connection->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
            $connection->setOption(Redis::OPT_COMPRESSION_LEVEL, 6);

            // Configuration du pipeline
            $connection->setOption(Redis::OPT_MAX_RETRIES, 3);

            $this->logger->info('Redis configuration optimized');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to optimize Redis configuration', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Analyse des performances Redis
     */
    public function analyzePerformance(): array
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return ['error' => 'No Redis connection available'];
        }

        try {
            $info = $connection->info();
            $stats = $connection->info('stats');
            $memory = $connection->info('memory');
            $clients = $connection->info('clients');

            return [
                'connection_info' => [
                    'connected_clients' => $clients['connected_clients'] ?? 0,
                    'blocked_clients' => $clients['blocked_clients'] ?? 0,
                    'tracking_clients' => $clients['tracking_clients'] ?? 0
                ],
                'memory_info' => [
                    'used_memory' => $this->formatBytes($memory['used_memory'] ?? 0),
                    'used_memory_peak' => $this->formatBytes($memory['used_memory_peak'] ?? 0),
                    'used_memory_rss' => $this->formatBytes($memory['used_memory_rss'] ?? 0),
                    'memory_fragmentation_ratio' => $memory['mem_fragmentation_ratio'] ?? 0
                ],
                'performance_stats' => [
                    'total_commands_processed' => $stats['total_commands_processed'] ?? 0,
                    'instantaneous_ops_per_sec' => $stats['instantaneous_ops_per_sec'] ?? 0,
                    'total_net_input_bytes' => $this->formatBytes($stats['total_net_input_bytes'] ?? 0),
                    'total_net_output_bytes' => $this->formatBytes($stats['total_net_output_bytes'] ?? 0),
                    'keyspace_hits' => $stats['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $stats['keyspace_misses'] ?? 0,
                    'hit_ratio' => $this->calculateHitRatio($stats)
                ],
                'configuration' => [
                    'maxmemory' => $this->formatBytes($info['maxmemory'] ?? 0),
                    'maxmemory_policy' => $info['maxmemory_policy'] ?? 'unknown',
                    'tcp_keepalive' => $info['tcp_keepalive'] ?? 0
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to analyze Redis performance', [
                'error' => $e->getMessage()
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Monitoring de la santé Redis
     */
    public function healthCheck(): array
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return [
                'status' => 'error',
                'message' => 'No Redis connection available'
            ];
        }

        try {
            // Test de connectivité basique
            $pingResult = $connection->ping();
            
            if ($pingResult !== '+PONG') {
                return [
                    'status' => 'error',
                    'message' => 'Redis ping failed',
                    'ping_result' => $pingResult
                ];
            }

            // Test de lecture/écriture
            $testKey = 'health_check_' . time();
            $testValue = 'health_check_value';
            
            $connection->setex($testKey, 10, $testValue);
            $retrievedValue = $connection->get($testKey);
            $connection->del($testKey);

            if ($retrievedValue !== $testValue) {
                return [
                    'status' => 'warning',
                    'message' => 'Redis read/write test failed'
                ];
            }

            // Vérifications des métriques critiques
            $info = $connection->info();
            $warnings = [];

            // Vérifier l'utilisation mémoire
            if (isset($info['used_memory'], $info['maxmemory']) && $info['maxmemory'] > 0) {
                $memoryUsagePercent = ($info['used_memory'] / $info['maxmemory']) * 100;
                if ($memoryUsagePercent > 90) {
                    $warnings[] = "High memory usage: {$memoryUsagePercent}%";
                }
            }

            // Vérifier le nombre de connexions
            if (isset($info['connected_clients']) && $info['connected_clients'] > 1000) {
                $warnings[] = "High number of connected clients: {$info['connected_clients']}";
            }

            // Vérifier la fragmentation mémoire
            if (isset($info['mem_fragmentation_ratio']) && $info['mem_fragmentation_ratio'] > 1.5) {
                $warnings[] = "High memory fragmentation: {$info['mem_fragmentation_ratio']}";
            }

            return [
                'status' => empty($warnings) ? 'healthy' : 'warning',
                'message' => empty($warnings) ? 'Redis is healthy' : 'Redis has warnings',
                'warnings' => $warnings,
                'response_time_ms' => $this->measureResponseTime(),
                'last_check' => new \DateTime()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Redis health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Health check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Optimisation automatique des paramètres Redis
     */
    public function autoOptimize(): array
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return ['error' => 'No Redis connection available'];
        }

        $results = [];

        try {
            $info = $connection->info();
            
            // Optimisation de la politique de mémoire
            if (isset($info['maxmemory_policy']) && $info['maxmemory_policy'] === 'noeviction') {
                $connection->config('SET', 'maxmemory-policy', 'allkeys-lru');
                $results[] = 'Changed maxmemory-policy to allkeys-lru';
            }

            // Optimisation des timeouts
            $connection->config('SET', 'timeout', '300');
            $results[] = 'Set connection timeout to 300 seconds';

            // Optimisation du TCP keepalive
            $connection->config('SET', 'tcp-keepalive', '60');
            $results[] = 'Set TCP keepalive to 60 seconds';

            // Optimisation des logs
            $connection->config('SET', 'slowlog-log-slower-than', '10000');
            $connection->config('SET', 'slowlog-max-len', '128');
            $results[] = 'Configured slow log parameters';

            $this->logger->info('Redis auto-optimization completed', [
                'optimizations' => $results
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Redis auto-optimization failed', [
                'error' => $e->getMessage()
            ]);

            $results[] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Nettoyage et maintenance Redis
     */
    public function performMaintenance(): array
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return ['error' => 'No Redis connection available'];
        }

        $results = [];

        try {
            // Nettoyer les clés expirées
            $expiredKeys = 0;
            $cursor = 0;
            
            do {
                $keys = $connection->scan($cursor, 'MATCH', '*', 'COUNT', 100);
                
                if ($keys !== false) {
                    foreach ($keys as $key) {
                        $ttl = $connection->ttl($key);
                        if ($ttl === -2) { // Clé expirée
                            $connection->del($key);
                            $expiredKeys++;
                        }
                    }
                }
            } while ($cursor > 0);

            $results['expired_keys_cleaned'] = $expiredKeys;

            // Défragmentation si nécessaire
            $info = $connection->info('memory');
            if (isset($info['mem_fragmentation_ratio']) && $info['mem_fragmentation_ratio'] > 1.5) {
                $connection->memory('purge');
                $results['defragmentation'] = 'performed';
            }

            // Nettoyage du slow log
            $connection->slowlog('reset');
            $results['slowlog'] = 'reset';

            $this->logger->info('Redis maintenance completed', $results);

        } catch (\Exception $e) {
            $this->logger->error('Redis maintenance failed', [
                'error' => $e->getMessage()
            ]);

            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Export des configurations Redis
     */
    public function exportConfiguration(): array
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return ['error' => 'No Redis connection available'];
        }

        try {
            $config = $connection->config('GET', '*');
            
            return [
                'export_time' => new \DateTime(),
                'redis_version' => $connection->info('server')['redis_version'] ?? 'unknown',
                'configuration' => $config
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to export Redis configuration', [
                'error' => $e->getMessage()
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    // Méthodes privées

    private function initializeSingle(): void
    {
        $this->redis = new Redis();
        
        $urlParts = parse_url($this->redisUrl);
        $host = $urlParts['host'] ?? 'localhost';
        $port = $urlParts['port'] ?? 6379;
        $password = $urlParts['pass'] ?? null;
        $database = ltrim($urlParts['path'] ?? '0', '/');

        $this->redis->connect($host, $port);
        
        if ($password) {
            $this->redis->auth($password);
        }
        
        $this->redis->select((int) $database);
    }

    private function initializeCluster(): void
    {
        $this->cluster = new RedisCluster(null, $this->clusterNodes);
    }

    private function calculateHitRatio(array $stats): float
    {
        $hits = $stats['keyspace_hits'] ?? 0;
        $misses = $stats['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function measureResponseTime(): float
    {
        $connection = $this->redis ?? $this->cluster;
        
        if (!$connection) {
            return 0;
        }

        $startTime = microtime(true);
        $connection->ping();
        $endTime = microtime(true);
        
        return round(($endTime - $startTime) * 1000, 2); // en millisecondes
    }
}
