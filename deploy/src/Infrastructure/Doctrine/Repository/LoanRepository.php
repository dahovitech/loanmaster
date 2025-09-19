<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Entity\Loan;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use App\Domain\ValueObject\UserId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository optimisé pour les requêtes de prêts avec cache et index
 */
final class LoanRepository extends ServiceEntityRepository implements LoanRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private CacheInterface $cache
    ) {
        parent::__construct($registry, Loan::class);
    }

    public function save(Loan $loan): void
    {
        $this->getEntityManager()->persist($loan);
        $this->getEntityManager()->flush();
        
        // Invalider les caches liés
        $this->invalidateUserCache($loan->getUserId());
    }

    public function findById(LoanId $id): ?Loan
    {
        return $this->cache->get(
            'loan_' . $id->toString(),
            function (ItemInterface $item) use ($id) {
                $item->expiresAfter(3600); // 1 heure
                
                return $this->createQueryBuilder('l')
                    ->select('l', 'u') // Eager loading de l'utilisateur
                    ->leftJoin('l.user', 'u')
                    ->where('l.id = :id')
                    ->setParameter('id', $id->toString())
                    ->getQuery()
                    ->getOneOrNullResult();
            }
        );
    }

    public function findByNumber(string $number): ?Loan
    {
        return $this->createQueryBuilder('l')
            ->select('l', 'u')
            ->leftJoin('l.user', 'u')
            ->where('l.number = :number')
            ->setParameter('number', $number)
            ->getQuery()
            ->useQueryCache(true)
            ->getOneOrNullResult();
    }

    public function findByUserId(UserId $userId): array
    {
        return $this->cache->get(
            'user_loans_' . $userId->toString(),
            function (ItemInterface $item) use ($userId) {
                $item->expiresAfter(1800); // 30 minutes
                
                return $this->createOptimizedUserLoansQuery($userId)
                    ->getQuery()
                    ->getResult();
            }
        );
    }

    public function findActiveLoansForUser(UserId $userId): array
    {
        return $this->cache->get(
            'user_active_loans_' . $userId->toString(),
            function (ItemInterface $item) use ($userId) {
                $item->expiresAfter(900); // 15 minutes
                
                return $this->createOptimizedUserLoansQuery($userId)
                    ->andWhere('l.status IN (:activeStatuses)')
                    ->setParameter('activeStatuses', [
                        LoanStatus::PENDING->value,
                        LoanStatus::UNDER_REVIEW->value,
                        LoanStatus::APPROVED->value,
                        LoanStatus::FUNDED->value,
                        LoanStatus::ACTIVE->value
                    ])
                    ->getQuery()
                    ->getResult();
            }
        );
    }

    public function findByStatus(LoanStatus $status): array
    {
        return $this->createQueryBuilder('l')
            ->select('l', 'u')
            ->leftJoin('l.user', 'u')
            ->where('l.status = :status')
            ->setParameter('status', $status->value)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->getResult();
    }

    public function findPendingLoans(): array
    {
        return $this->cache->get(
            'pending_loans',
            function (ItemInterface $item) {
                $item->expiresAfter(300); // 5 minutes
                
                return $this->createQueryBuilder('l')
                    ->select('l', 'u')
                    ->leftJoin('l.user', 'u')
                    ->where('l.status = :status')
                    ->setParameter('status', LoanStatus::PENDING->value)
                    ->orderBy('l.createdAt', 'ASC') // FIFO pour les examens
                    ->getQuery()
                    ->getResult();
            }
        );
    }

    public function remove(Loan $loan): void
    {
        $this->getEntityManager()->remove($loan);
        $this->getEntityManager()->flush();
        
        // Invalider les caches
        $this->invalidateUserCache($loan->getUserId());
        $this->cache->delete('loan_' . $loan->getId()->toString());
    }

    public function nextIdentity(): LoanId
    {
        return LoanId::generate();
    }

    /**
     * Requêtes d'analyse et de reporting optimisées
     */
    public function getLoanStatsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->cache->get(
            'loan_stats_' . $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d'),
            function (ItemInterface $item) use ($startDate, $endDate) {
                $item->expiresAfter(3600); // 1 heure
                
                $qb = $this->createQueryBuilder('l');
                
                return $qb
                    ->select([
                        'COUNT(l.id) as total_loans',
                        'AVG(l.amount) as avg_amount',
                        'SUM(l.amount) as total_amount',
                        'l.status',
                        'l.type as loan_type'
                    ])
                    ->where($qb->expr()->between('l.createdAt', ':startDate', ':endDate'))
                    ->setParameter('startDate', $startDate)
                    ->setParameter('endDate', $endDate)
                    ->groupBy('l.status', 'l.type')
                    ->getQuery()
                    ->getArrayResult();
            }
        );
    }

    public function findLoansWithUpcomingPayments(int $days = 7): array
    {
        $futureDate = new \DateTimeImmutable("+{$days} days");
        
        return $this->createQueryBuilder('l')
            ->select('l', 'u')
            ->leftJoin('l.user', 'u')
            ->where('l.status = :activeStatus')
            ->andWhere('l.nextPaymentDate <= :futureDate')
            ->andWhere('l.nextPaymentDate >= :today')
            ->setParameter('activeStatus', LoanStatus::ACTIVE->value)
            ->setParameter('futureDate', $futureDate)
            ->setParameter('today', new \DateTimeImmutable())
            ->orderBy('l.nextPaymentDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findHighRiskLoans(): array
    {
        return $this->cache->get(
            'high_risk_loans',
            function (ItemInterface $item) {
                $item->expiresAfter(1800); // 30 minutes
                
                $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
                
                return $this->createQueryBuilder('l')
                    ->select('l', 'u')
                    ->leftJoin('l.user', 'u')
                    ->where('l.status = :activeStatus')
                    ->andWhere('l.lastPaymentDate < :thirtyDaysAgo OR l.lastPaymentDate IS NULL')
                    ->setParameter('activeStatus', LoanStatus::ACTIVE->value)
                    ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
                    ->orderBy('l.lastPaymentDate', 'ASC')
                    ->getQuery()
                    ->getResult();
            }
        );
    }

    /**
     * Recherche full-text optimisée pour l'admin
     */
    public function searchLoans(string $searchTerm, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l', 'u')
            ->leftJoin('l.user', 'u');

        // Recherche full-text sur plusieurs champs
        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('l.number', ':searchTerm'),
                    $qb->expr()->like('u.email', ':searchTerm'),
                    $qb->expr()->like('u.firstName', ':searchTerm'),
                    $qb->expr()->like('u.lastName', ':searchTerm'),
                    $qb->expr()->like('l.projectDescription', ':searchTerm')
                )
            )
            ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        // Filtres
        if (isset($filters['status'])) {
            $qb->andWhere('l.status IN (:statuses)')
               ->setParameter('statuses', (array) $filters['status']);
        }

        if (isset($filters['type'])) {
            $qb->andWhere('l.type IN (:types)')
               ->setParameter('types', (array) $filters['type']);
        }

        if (isset($filters['amountMin'])) {
            $qb->andWhere('l.amount >= :amountMin')
               ->setParameter('amountMin', $filters['amountMin']);
        }

        if (isset($filters['amountMax'])) {
            $qb->andWhere('l.amount <= :amountMax')
               ->setParameter('amountMax', $filters['amountMax']);
        }

        if (isset($filters['dateFrom'])) {
            $qb->andWhere('l.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (isset($filters['dateTo'])) {
            $qb->andWhere('l.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo']);
        }

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit)
           ->orderBy('l.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Optimisation: Requête de base pour les prêts utilisateur
     */
    private function createOptimizedUserLoansQuery(UserId $userId): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->select('l', 'u') // Eager loading pour éviter N+1
            ->leftJoin('l.user', 'u')
            ->where('l.userId = :userId')
            ->setParameter('userId', $userId->toString())
            ->orderBy('l.createdAt', 'DESC');
    }

    /**
     * Invalidation de cache ciblée
     */
    private function invalidateUserCache(UserId $userId): void
    {
        $this->cache->delete('user_loans_' . $userId->toString());
        $this->cache->delete('user_active_loans_' . $userId->toString());
        $this->cache->delete('pending_loans');
        $this->cache->delete('high_risk_loans');
    }
}
