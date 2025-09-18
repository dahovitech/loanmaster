<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareAdapterInterface;

/**
 * Service de gestion centralisée du cache et optimisation des performances
 */
class DatabaseOptimizationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $loanCalculationsCache,
        private readonly CacheInterface $userDataCache,
        private readonly CacheInterface $statisticsCache
    ) {}

    /**
     * Optimise une requête Doctrine avec cache et hints
     */
    public function optimizeQuery(string $dql, array $parameters = [], int $ttl = 3600): mixed
    {
        $query = $this->entityManager->createQuery($dql);
        
        // Application des paramètres
        foreach ($parameters as $key => $value) {
            $query->setParameter($key, $value);
        }
        
        // Hints d'optimisation Doctrine
        $query->setHint(\Doctrine\ORM\Query::HINT_FORCE_PARTIAL_LOAD, false);
        $query->setHint(\Doctrine\ORM\Query::HINT_INCLUDE_META_COLUMNS, true);
        
        // Cache de résultats si TTL défini
        if ($ttl > 0) {
            $query->enableResultCache($ttl);
        }
        
        return $query->getResult();
    }

    /**
     * Exécute une requête native optimisée avec cache
     */
    public function executeOptimizedNativeQuery(string $sql, array $parameters = [], int $ttl = 1800): array
    {
        $cacheKey = 'native_query_' . md5($sql . serialize($parameters));
        
        return $this->statisticsCache->get($cacheKey, function () use ($sql, $parameters, $ttl) {
            $connection = $this->entityManager->getConnection();
            $stmt = $connection->prepare($sql);
            
            return $stmt->executeQuery($parameters)->fetchAllAssociative();
        }, $ttl);
    }

    /**
     * Précharge les données critiques en cache
     */
    public function warmupCriticalData(): void
    {
        // Statistiques globales
        $this->executeOptimizedNativeQuery(
            'SELECT COUNT(*) as total_loans, AVG(amount) as avg_amount FROM loan WHERE status = ?',
            ['APPROVED'],
            3600
        );

        // Utilisateurs actifs du jour
        $this->executeOptimizedNativeQuery(
            'SELECT COUNT(*) as active_users FROM "user" WHERE last_login_at >= ?',
            [date('Y-m-d')],
            1800
        );

        // Top 10 des prêts récents
        $this->optimizeQuery(
            'SELECT l FROM App\Domain\Entity\Loan l ORDER BY l.createdAt DESC',
            [],
            1800
        );
    }

    /**
     * Nettoie les caches liés à un utilisateur
     */
    public function invalidateUserRelatedCache(int $userId): void
    {
        if ($this->userDataCache instanceof TagAwareAdapterInterface) {
            $this->userDataCache->invalidateTags(["user_{$userId}"]);
        }
        
        if ($this->loanCalculationsCache instanceof TagAwareAdapterInterface) {
            $this->loanCalculationsCache->invalidateTags(["user_{$userId}"]);
        }
        
        // Invalider les statistiques globales
        $this->statisticsCache->clear();
    }

    /**
     * Analyse les performances des requêtes lentes
     */
    public function analyzeSlowQueries(): array
    {
        $sql = "
            SELECT query, mean_exec_time, calls, total_exec_time
            FROM pg_stat_statements 
            WHERE mean_exec_time > ? 
            ORDER BY mean_exec_time DESC 
            LIMIT 10
        ";
        
        return $this->executeOptimizedNativeQuery($sql, [100], 300); // 5 minutes de cache
    }

    /**
     * Optimise les index manquants (analyse)
     */
    public function suggestMissingIndexes(): array
    {
        $sql = "
            SELECT schemaname, tablename, attname, n_distinct, correlation
            FROM pg_stats 
            WHERE schemaname = 'public' 
            AND n_distinct > 100 
            AND correlation < 0.1
            ORDER BY n_distinct DESC
        ";
        
        return $this->executeOptimizedNativeQuery($sql, [], 7200); // 2 heures de cache
    }

    /**
     * Statistiques de cache pour monitoring
     */
    public function getCacheStatistics(): array
    {
        return [
            'loan_calculations' => $this->getCachePoolStats('loan_calculations'),
            'user_data' => $this->getCachePoolStats('user_data'),
            'statistics' => $this->getCachePoolStats('statistics'),
        ];
    }

    /**
     * Force la régénération des caches essentiels
     */
    public function regenerateEssentialCaches(): void
    {
        // Clear all caches
        $this->loanCalculationsCache->clear();
        $this->userDataCache->clear();
        $this->statisticsCache->clear();
        
        // Regenerate critical data
        $this->warmupCriticalData();
    }

    /**
     * Optimise la base de données (maintenance)
     */
    public function performDatabaseMaintenance(): array
    {
        $results = [];
        
        // Analyse des tables principales
        $tables = ['loan', 'user', 'user_kyc'];
        
        foreach ($tables as $table) {
            $this->entityManager->getConnection()->executeStatement("ANALYZE {$table}");
            $results[] = "Analyzed table: {$table}";
        }
        
        // Statistiques de vacuum
        $vacuumStats = $this->executeOptimizedNativeQuery(
            "SELECT schemaname, tablename, last_vacuum, last_autovacuum, last_analyze, last_autoanalyze 
             FROM pg_stat_user_tables 
             WHERE schemaname = 'public'",
            [],
            0 // Pas de cache pour les stats de maintenance
        );
        
        $results['vacuum_stats'] = $vacuumStats;
        
        return $results;
    }

    /**
     * Obtient les statistiques d'un pool de cache spécifique
     */
    private function getCachePoolStats(string $poolName): array
    {
        // Cette méthode devrait être adaptée selon l'implémentation du cache utilisé
        return [
            'pool' => $poolName,
            'status' => 'active',
            'last_check' => new \DateTime()
        ];
    }
}
