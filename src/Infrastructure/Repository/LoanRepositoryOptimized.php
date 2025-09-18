<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Loan;
use App\Domain\Repository\LoanRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository optimisé pour les entités Loan avec mise en cache intelligente
 */
class LoanRepositoryOptimized extends ServiceEntityRepository implements LoanRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheInterface $loanCalculationsCache,
        private readonly CacheInterface $statisticsCache
    ) {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Trouve les prêts actifs d'un utilisateur avec cache et optimisation query
     */
    public function findActiveByUser(int $userId): array
    {
        $cacheKey = "user_active_loans_{$userId}";
        
        return $this->loanCalculationsCache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(1800); // 30 minutes
            
            return $this->createOptimizedQueryBuilder('l')
                ->select('l', 'u', 'lk') // Select join pour éviter les requêtes N+1
                ->innerJoin('l.user', 'u')
                ->leftJoin('l.loanKyc', 'lk')
                ->where('l.user = :userId')
                ->andWhere('l.status IN (:activeStatuses)')
                ->setParameter('userId', $userId)
                ->setParameter('activeStatuses', ['PENDING', 'APPROVED', 'DISBURSED'])
                ->orderBy('l.createdAt', 'DESC')
                ->getQuery()
                ->enableResultCache(1800, "active_loans_user_{$userId}")
                ->getResult();
        });
    }

    /**
     * Calcule les statistiques de prêts avec cache long
     */
    public function getLoanStatistics(): array
    {
        return $this->statisticsCache->get('loan_statistics', function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes
            
            $qb = $this->createOptimizedQueryBuilder('l');
            
            // Requête optimisée avec agrégations
            $result = $qb
                ->select([
                    'COUNT(l.id) as total_loans',
                    'COUNT(CASE WHEN l.status = :approved THEN 1 END) as approved_loans',
                    'COUNT(CASE WHEN l.status = :pending THEN 1 END) as pending_loans',
                    'AVG(l.amount) as average_amount',
                    'SUM(l.amount) as total_amount'
                ])
                ->setParameter('approved', 'APPROVED')
                ->setParameter('pending', 'PENDING')
                ->getQuery()
                ->enableResultCache(300)
                ->getSingleResult();
                
            return $result;
        });
    }

    /**
     * Recherche de prêts avec filtres optimisés
     */
    public function findByFilters(array $filters, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createOptimizedQueryBuilder('l')
            ->select('l', 'u') // Eager loading de l'utilisateur
            ->innerJoin('l.user', 'u');

        // Application des filtres avec index
        if (isset($filters['status'])) {
            $qb->andWhere('l.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['amount_min'])) {
            $qb->andWhere('l.amount >= :amount_min')
               ->setParameter('amount_min', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $qb->andWhere('l.amount <= :amount_max')
               ->setParameter('amount_max', $filters['amount_max']);
        }

        if (isset($filters['date_from'])) {
            $qb->andWhere('l.createdAt >= :date_from')
               ->setParameter('date_from', $filters['date_from']);
        }

        // Pagination optimisée
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit)
           ->orderBy('l.createdAt', 'DESC');

        return $qb->getQuery()
                  ->enableResultCache(600) // 10 minutes de cache
                  ->getResult();
    }

    /**
     * Trouve les prêts expirant bientôt (pour notifications)
     */
    public function findExpiringLoans(\DateTimeInterface $expirationDate): array
    {
        return $this->createOptimizedQueryBuilder('l')
            ->select('l', 'u') // Eager loading
            ->innerJoin('l.user', 'u')
            ->where('l.dueDate <= :expirationDate')
            ->andWhere('l.status IN (:activeStatuses)')
            ->setParameter('expirationDate', $expirationDate)
            ->setParameter('activeStatuses', ['APPROVED', 'DISBURSED'])
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->enableResultCache(1800) // Cache 30 minutes
            ->getResult();
    }

    /**
     * QueryBuilder optimisé avec hints Doctrine
     */
    private function createOptimizedQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->setHint(\Doctrine\ORM\Query::HINT_FORCE_PARTIAL_LOAD, false)
            ->setHint(\Doctrine\ORM\Query::HINT_INCLUDE_META_COLUMNS, true);
    }

    /**
     * Invalidation de cache ciblée
     */
    public function invalidateUserCache(int $userId): void
    {
        $this->loanCalculationsCache->delete("user_active_loans_{$userId}");
        $this->statisticsCache->delete('loan_statistics');
    }

    /**
     * Méthode pour pré-chauffer le cache
     */
    public function warmupCache(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->findActiveByUser($userId);
        }
    }
}
