<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository optimisé pour les entités User avec stratégies de cache
 */
class UserRepositoryOptimized extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheInterface $userDataCache,
        private readonly CacheInterface $userKycCache
    ) {
        parent::__construct($registry, User::class);
    }

    /**
     * Trouve un utilisateur par email avec cache
     */
    public function findByEmail(string $email): ?User
    {
        $cacheKey = "user_email_" . md5($email);
        
        return $this->userDataCache->get($cacheKey, function (ItemInterface $item) use ($email) {
            $item->expiresAfter(1800); // 30 minutes
            
            return $this->createQueryBuilder('u')
                ->select('u', 'p', 'k') // Eager loading des relations importantes
                ->leftJoin('u.profile', 'p')
                ->leftJoin('u.kyc', 'k')
                ->where('u.email = :email')
                ->setParameter('email', $email)
                ->getQuery()
                ->enableResultCache(1800)
                ->getOneOrNullResult();
        });
    }

    /**
     * Trouve les utilisateurs avec KYC validé
     */
    public function findWithValidatedKyc(): array
    {
        return $this->userKycCache->get('users_validated_kyc', function (ItemInterface $item) {
            $item->expiresAfter(3600); // 1 heure
            
            return $this->createQueryBuilder('u')
                ->select('u', 'k')
                ->innerJoin('u.kyc', 'k')
                ->where('k.status = :validated')
                ->setParameter('validated', 'VALIDATED')
                ->orderBy('k.validatedAt', 'DESC')
                ->getQuery()
                ->enableResultCache(3600)
                ->getResult();
        });
    }

    /**
     * Recherche d'utilisateurs avec filtres et pagination optimisée
     */
    public function searchUsers(array $criteria, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u', 'p', 'k') // Relations principales
            ->leftJoin('u.profile', 'p')
            ->leftJoin('u.kyc', 'k');

        // Filtres avec index
        if (isset($criteria['role'])) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
               ->setParameter('role', json_encode($criteria['role']));
        }

        if (isset($criteria['kyc_status'])) {
            $qb->andWhere('k.status = :kyc_status')
               ->setParameter('kyc_status', $criteria['kyc_status']);
        }

        if (isset($criteria['created_after'])) {
            $qb->andWhere('u.createdAt >= :created_after')
               ->setParameter('created_after', $criteria['created_after']);
        }

        if (isset($criteria['email_domain'])) {
            $qb->andWhere('u.email LIKE :email_domain')
               ->setParameter('email_domain', '%@' . $criteria['email_domain']);
        }

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit)
           ->orderBy('u.createdAt', 'DESC');

        return $qb->getQuery()
                  ->enableResultCache(600) // 10 minutes
                  ->getResult();
    }

    /**
     * Compte des utilisateurs par statut avec cache
     */
    public function getUserStatsByStatus(): array
    {
        return $this->userDataCache->get('user_stats_by_status', function (ItemInterface $item) {
            $item->expiresAfter(600); // 10 minutes
            
            return $this->createQueryBuilder('u')
                ->select([
                    'COUNT(u.id) as total_users',
                    'COUNT(CASE WHEN u.isVerified = 1 THEN 1 END) as verified_users',
                    'COUNT(CASE WHEN k.status = :validated THEN 1 END) as kyc_validated',
                    'COUNT(CASE WHEN k.status = :pending THEN 1 END) as kyc_pending'
                ])
                ->leftJoin('u.kyc', 'k')
                ->setParameter('validated', 'VALIDATED')
                ->setParameter('pending', 'PENDING')
                ->getQuery()
                ->enableResultCache(600)
                ->getSingleResult();
        });
    }

    /**
     * Utilisateurs les plus actifs (avec cache long)
     */
    public function getMostActiveUsers(int $limit = 10): array
    {
        return $this->userDataCache->get("most_active_users_{$limit}", function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(3600); // 1 heure
            
            return $this->createQueryBuilder('u')
                ->select('u', 'COUNT(l.id) as loan_count')
                ->leftJoin('u.loans', 'l')
                ->groupBy('u.id')
                ->orderBy('loan_count', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->enableResultCache(3600)
                ->getResult();
        });
    }

    /**
     * Invalidation de cache ciblée
     */
    public function invalidateUserCache(int $userId, ?string $email = null): void
    {
        if ($email) {
            $this->userDataCache->delete("user_email_" . md5($email));
        }
        
        // Invalider les caches globaux
        $this->userDataCache->delete('user_stats_by_status');
        $this->userKycCache->delete('users_validated_kyc');
    }

    /**
     * Préchargement intelligent du cache
     */
    public function preloadActiveUsersCache(): void
    {
        // Précharge les utilisateurs les plus consultés
        $this->getMostActiveUsers();
        $this->getUserStatsByStatus();
        $this->findWithValidatedKyc();
    }
}
