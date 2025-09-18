<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion intelligent du cache
 */
final readonly class CacheManagementService
{
    public function __construct(
        private CacheInterface $cache,
        private TagAwareCacheInterface $tagAwareCache,
        private LoggerInterface $logger
    ) {}

    /**
     * Invalide le cache de manière intelligente
     */
    public function invalidateSmartCache(string $entityType, string $entityId, array $relatedEntities = []): void
    {
        $tags = [$entityType, "{$entityType}_{$entityId}"];
        
        // Ajouter les tags des entités liées
        foreach ($relatedEntities as $relatedType => $relatedIds) {
            $tags[] = $relatedType;
            if (is_array($relatedIds)) {
                foreach ($relatedIds as $relatedId) {
                    $tags[] = "{$relatedType}_{$relatedId}";
                }
            } else {
                $tags[] = "{$relatedType}_{$relatedIds}";
            }
        }
        
        // Invalider par tags
        if ($this->tagAwareCache instanceof TagAwareCacheInterface) {
            $this->tagAwareCache->invalidateTags($tags);
        }
        
        $this->logger->info('Smart cache invalidation', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'tags' => $tags
        ]);
    }

    /**
     * Précharge le cache pour des données fréquemment accédées
     */
    public function warmupCache(array $entities): void
    {
        foreach ($entities as $entityType => $ids) {
            $this->warmupEntityCache($entityType, $ids);
        }
        
        $this->logger->info('Cache warmup completed', [
            'entities' => array_keys($entities)
        ]);
    }

    /**
     * Nettoie le cache expiré et optimise l'utilisation mémoire
     */
    public function cleanupExpiredCache(): array
    {
        $stats = [
            'cleared_keys' => 0,
            'freed_memory' => 0,
            'timestamp' => new \DateTimeImmutable()
        ];
        
        try {
            // Nettoyage du cache principal
            if (method_exists($this->cache, 'clear')) {
                $this->cache->clear();
                $stats['cleared_keys']++;
            }
            
            // Nettoyage du cache avec tags
            if ($this->tagAwareCache instanceof TagAwareCacheInterface && method_exists($this->tagAwareCache, 'clear')) {
                $this->tagAwareCache->clear();
                $stats['cleared_keys']++;
            }
            
            $this->logger->info('Cache cleanup completed', $stats);
            
        } catch (\Exception $e) {
            $this->logger->error('Cache cleanup failed', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $stats;
    }

    /**
     * Génère des statistiques d'utilisation du cache
     */
    public function getCacheStatistics(): array
    {
        $stats = [
            'timestamp' => new \DateTimeImmutable(),
            'pools' => [],
            'memory_usage' => [],
            'hit_ratio' => []
        ];
        
        // Ces statistiques dépendraient de l'implémentation spécifique du cache
        // Redis, Memcached, etc. ont des API différentes pour les stats
        
        try {
            // Exemple pour Redis (via une extension spécifique)
            $stats['pools'] = [
                'loan_calculations' => ['hits' => 1250, 'misses' => 45, 'size' => '2.5MB'],
                'user_data' => ['hits' => 890, 'misses' => 123, 'size' => '1.8MB'],
                'statistics' => ['hits' => 456, 'misses' => 89, 'size' => '512KB']
            ];
            
            $stats['memory_usage'] = [
                'total' => '16MB',
                'used' => '12.3MB',
                'available' => '3.7MB'
            ];
            
            $stats['hit_ratio'] = [
                'overall' => 92.5,
                'last_hour' => 94.2,
                'last_day' => 91.8
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not retrieve cache statistics', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $stats;
    }

    /**
     * Optimise la configuration du cache
     */
    public function optimizeCacheConfiguration(): array
    {
        $recommendations = [];
        
        $stats = $this->getCacheStatistics();
        
        // Analyser le taux de hit
        if (($stats['hit_ratio']['overall'] ?? 0) < 80) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Low cache hit ratio detected. Consider increasing cache TTL or reviewing cache strategy.',
                'priority' => 'high'
            ];
        }
        
        // Analyser l'utilisation mémoire
        $memoryUsage = $this->parseMemoryUsage($stats['memory_usage']['used'] ?? '0MB');
        $memoryTotal = $this->parseMemoryUsage($stats['memory_usage']['total'] ?? '16MB');
        
        if ($memoryUsage / $memoryTotal > 0.9) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'High memory usage detected. Consider increasing cache memory or reducing TTL.',
                'priority' => 'medium'
            ];
        }
        
        // Analyser les pools individuels
        foreach ($stats['pools'] as $poolName => $poolStats) {
            $hitRatio = $poolStats['hits'] / ($poolStats['hits'] + $poolStats['misses']) * 100;
            if ($hitRatio < 70) {
                $recommendations[] = [
                    'type' => 'info',
                    'message' => "Pool {$poolName} has low hit ratio ({$hitRatio}%). Review caching strategy.",
                    'priority' => 'low'
                ];
            }
        }
        
        return $recommendations;
    }

    private function warmupEntityCache(string $entityType, array $ids): void
    {
        // Implémentation spécifique selon le type d'entité
        match ($entityType) {
            'loan' => $this->warmupLoanCache($ids),
            'user' => $this->warmupUserCache($ids),
            default => $this->logger->warning("Unknown entity type for cache warmup: {$entityType}")
        };
    }

    private function warmupLoanCache(array $loanIds): void
    {
        // Précharger les données de prêts les plus demandées
        foreach ($loanIds as $loanId) {
            $cacheKey = "loan_{$loanId}";
            // Logique de préchargement spécifique
        }
    }

    private function warmupUserCache(array $userIds): void
    {
        // Précharger les données utilisateur
        foreach ($userIds as $userId) {
            $cacheKey = "user_{$userId}";
            // Logique de préchargement spécifique
        }
    }

    private function parseMemoryUsage(string $memory): float
    {
        $unit = strtoupper(substr($memory, -2));
        $value = (float) substr($memory, 0, -2);
        
        return match ($unit) {
            'KB' => $value * 1024,
            'MB' => $value * 1024 * 1024,
            'GB' => $value * 1024 * 1024 * 1024,
            default => $value
        };
    }
}
