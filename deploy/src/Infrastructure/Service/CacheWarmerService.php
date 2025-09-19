<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Infrastructure\Repository\LoanRepositoryOptimized;
use App\Infrastructure\Repository\UserRepositoryOptimized;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Service de pré-chargement intelligent des caches
 */
class CacheWarmerService implements CacheWarmerInterface
{
    public function __construct(
        private readonly DatabaseOptimizationService $databaseOptimization,
        private readonly LoanRepositoryOptimized $loanRepository,
        private readonly UserRepositoryOptimized $userRepository
    ) {}

    /**
     * Indique si ce warmer est optionnel
     */
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * Préchauffe les caches critiques
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $warmedFiles = [];

        try {
            // 1. Statistiques globales du système
            $this->databaseOptimization->warmupCriticalData();
            $warmedFiles[] = 'Global statistics cache warmed';

            // 2. Utilisateurs les plus actifs
            $this->userRepository->preloadActiveUsersCache();
            $warmedFiles[] = 'Active users cache warmed';

            // 3. Prêts récents et statistiques
            $this->loanRepository->getLoanStatistics();
            $warmedFiles[] = 'Loan statistics cache warmed';

            // 4. Utilisateurs avec KYC validé (pour les dashboards admin)
            $this->userRepository->findWithValidatedKyc();
            $warmedFiles[] = 'Validated KYC users cache warmed';

            // 5. Cache pour les utilisateurs les plus consultés (basé sur l'activité)
            $activeUserIds = $this->getTopActiveUserIds();
            if (!empty($activeUserIds)) {
                $this->loanRepository->warmupCache($activeUserIds);
                $warmedFiles[] = sprintf('Loan cache warmed for %d active users', count($activeUserIds));
            }

        } catch (\Exception $e) {
            // En cas d'erreur, on continue sans faire échouer le déploiement
            $warmedFiles[] = 'Cache warming failed: ' . $e->getMessage();
        }

        return $warmedFiles;
    }

    /**
     * Pré-charge les données pour un utilisateur spécifique
     */
    public function warmupUserData(int $userId): void
    {
        try {
            // Prêts actifs de l'utilisateur
            $this->loanRepository->findActiveByUser($userId);
            
            // Données utilisateur avec KYC
            $this->userRepository->findByEmail($this->getUserEmailById($userId));
            
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer l'opération
            error_log("Failed to warmup cache for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Pré-charge les données pour les dashboards administrateur
     */
    public function warmupAdminDashboard(): array
    {
        $results = [];

        try {
            // Statistiques globales
            $stats = $this->loanRepository->getLoanStatistics();
            $results['loan_stats'] = $stats;

            // Statistiques utilisateurs
            $userStats = $this->userRepository->getUserStatsByStatus();
            $results['user_stats'] = $userStats;

            // Utilisateurs les plus actifs
            $activeUsers = $this->userRepository->getMostActiveUsers(20);
            $results['active_users'] = count($activeUsers);

            // Prêts expirant bientôt
            $expiringLoans = $this->loanRepository->findExpiringLoans(
                new \DateTime('+7 days')
            );
            $results['expiring_loans'] = count($expiringLoans);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Nettoie et régénère tous les caches
     */
    public function regenerateAllCaches(): array
    {
        $results = [];

        // Clear all caches
        $this->databaseOptimization->regenerateEssentialCaches();
        $results[] = 'All caches cleared';

        // Warm up again
        $warmedFiles = $this->warmUp('');
        $results = array_merge($results, $warmedFiles);

        return $results;
    }

    /**
     * Obtient les IDs des utilisateurs les plus actifs
     */
    private function getTopActiveUserIds(int $limit = 50): array
    {
        try {
            $activeUsers = $this->userRepository->getMostActiveUsers($limit);
            return array_map(fn($user) => $user['id'] ?? $user[0]->getId(), $activeUsers);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Obtient l'email d'un utilisateur par son ID (pour le cache)
     */
    private function getUserEmailById(int $userId): ?string
    {
        try {
            // Cette méthode devrait être optimisée avec un cache séparé
            // Pour l'instant, on utilise une requête simple
            return null; // Placeholder - serait implémenté avec une requête optimisée
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Vérifie la santé des caches
     */
    public function checkCacheHealth(): array
    {
        return $this->databaseOptimization->getCacheStatistics();
    }
}
