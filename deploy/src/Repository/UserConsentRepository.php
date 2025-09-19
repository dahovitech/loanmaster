<?php

namespace App\Repository;

use App\Entity\UserConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserConsent>
 */
class UserConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserConsent::class);
    }

    public function save(UserConsent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserConsent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les consentements pour un utilisateur spécifique
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un consentement spécifique pour un utilisateur et un type
     */
    public function findUserConsent(int $userId, string $consentType): ?UserConsent
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.userId = :userId')
            ->andWhere('c.consentType = :consentType')
            ->setParameter('userId', $userId)
            ->setParameter('consentType', $consentType)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les consentements accordés pour un utilisateur
     */
    public function findGrantedConsents(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.userId = :userId')
            ->andWhere('c.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', UserConsent::STATUS_GRANTED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a accordé un consentement spécifique
     */
    public function hasValidConsent(int $userId, string $consentType): bool
    {
        $consent = $this->findUserConsent($userId, $consentType);
        
        return $consent && $consent->isValid();
    }

    /**
     * Trouve les consentements expirés
     */
    public function findExpiredConsents(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.expiresAt IS NOT NULL')
            ->andWhere('c.expiresAt < :now')
            ->andWhere('c.status = :status')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', UserConsent::STATUS_GRANTED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les consentements qui expirent bientôt
     */
    public function findExpiringSoon(int $days = 30): array
    {
        $futureDate = new \DateTimeImmutable('+' . $days . ' days');
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.expiresAt IS NOT NULL')
            ->andWhere('c.expiresAt <= :futureDate')
            ->andWhere('c.expiresAt > :now')
            ->andWhere('c.status = :status')
            ->setParameter('futureDate', $futureDate)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('status', UserConsent::STATUS_GRANTED)
            ->orderBy('c.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des consentements par type
     */
    public function getConsentStatsByType(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.consentType, c.status, COUNT(c.id) as count')
            ->groupBy('c.consentType, c.status')
            ->orderBy('c.consentType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des consentements par jour
     */
    public function getConsentStatsByDay(int $days = 30): array
    {
        $startDate = new \DateTime('-' . $days . ' days');
        
        return $this->createQueryBuilder('c')
            ->select('DATE(c.createdAt) as date, c.status, COUNT(c.id) as count')
            ->andWhere('c.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('DATE(c.createdAt), c.status')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les consentements retirés avec leurs raisons
     */
    public function findWithdrawnConsents(int $days = 30): array
    {
        $startDate = new \DateTime('-' . $days . ' days');
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.withdrawnAt >= :startDate')
            ->setParameter('status', UserConsent::STATUS_WITHDRAWN)
            ->setParameter('startDate', $startDate)
            ->orderBy('c.withdrawnAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec des consentements incomplets
     */
    public function findUsersWithIncompleteConsents(array $requiredTypes): array
    {
        $result = [];
        
        // Récupère tous les utilisateurs qui ont au moins un consentement
        $users = $this->createQueryBuilder('c')
            ->select('DISTINCT c.userId')
            ->getQuery()
            ->getScalarResult();

        foreach ($users as $userRow) {
            $userId = $userRow['userId'];
            $userConsents = $this->findByUser($userId);
            
            $consentTypes = array_map(fn($consent) => $consent->getConsentType(), $userConsents);
            $validConsents = array_filter($userConsents, fn($consent) => $consent->isValid());
            $validConsentTypes = array_map(fn($consent) => $consent->getConsentType(), $validConsents);
            
            $missingTypes = array_diff($requiredTypes, $validConsentTypes);
            
            if (!empty($missingTypes)) {
                $result[] = [
                    'userId' => $userId,
                    'missingTypes' => $missingTypes,
                    'totalConsents' => count($userConsents),
                    'validConsents' => count($validConsents),
                ];
            }
        }
        
        return $result;
    }

    /**
     * Anonymise les consentements d'un utilisateur (pour suppression de compte)
     */
    public function anonymizeUserConsents(int $userId): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.userId', ':anonymousId')
            ->set('c.ipAddress', 'NULL')
            ->set('c.userAgent', 'NULL')
            ->set('c.metadata', 'NULL')
            ->andWhere('c.userId = :userId')
            ->setParameter('anonymousId', 0) // ID anonyme
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /**
     * Nettoyage automatique des anciens consentements
     */
    public function deleteOldConsents(int $retentionDays = 2555): int // ~7 ans par défaut (RGPD)
    {
        $cutoffDate = new \DateTime('-' . $retentionDays . ' days');
        
        return $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.createdAt < :cutoffDate')
            ->andWhere('c.status IN (:inactiveStatuses)')
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('inactiveStatuses', [
                UserConsent::STATUS_DENIED,
                UserConsent::STATUS_WITHDRAWN
            ])
            ->getQuery()
            ->execute();
    }

    /**
     * Recherche de consentements avec filtres multiples
     */
    public function findWithFilters(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('c');

        if (isset($filters['userId'])) {
            $qb->andWhere('c.userId = :userId')
               ->setParameter('userId', $filters['userId']);
        }

        if (isset($filters['consentType'])) {
            $qb->andWhere('c.consentType = :consentType')
               ->setParameter('consentType', $filters['consentType']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['startDate'])) {
            $qb->andWhere('c.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $qb->andWhere('c.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        if (isset($filters['expiring']) && $filters['expiring']) {
            $futureDate = new \DateTimeImmutable('+30 days');
            $qb->andWhere('c.expiresAt IS NOT NULL')
               ->andWhere('c.expiresAt <= :futureDate')
               ->andWhere('c.expiresAt > :now')
               ->setParameter('futureDate', $futureDate)
               ->setParameter('now', new \DateTimeImmutable());
        }

        if (isset($filters['expired']) && $filters['expired']) {
            $qb->andWhere('c.expiresAt IS NOT NULL')
               ->andWhere('c.expiresAt < :now')
               ->setParameter('now', new \DateTimeImmutable());
        }

        return $qb->orderBy('c.createdAt', 'DESC')
                  ->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total de consentements avec filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('c')
                   ->select('COUNT(c.id)');

        if (isset($filters['userId'])) {
            $qb->andWhere('c.userId = :userId')
               ->setParameter('userId', $filters['userId']);
        }

        if (isset($filters['consentType'])) {
            $qb->andWhere('c.consentType = :consentType')
               ->setParameter('consentType', $filters['consentType']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['startDate'])) {
            $qb->andWhere('c.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $qb->andWhere('c.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les taux de consentement par type
     */
    public function getConsentRates(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.consentType, c.status, COUNT(c.id) as count')
            ->groupBy('c.consentType, c.status')
            ->getQuery()
            ->getResult();

        $rates = [];
        foreach ($result as $row) {
            $type = $row['consentType'];
            $status = $row['status'];
            $count = $row['count'];
            
            if (!isset($rates[$type])) {
                $rates[$type] = [
                    'total' => 0,
                    'granted' => 0,
                    'denied' => 0,
                    'withdrawn' => 0,
                    'pending' => 0,
                ];
            }
            
            $rates[$type]['total'] += $count;
            $rates[$type][$status] += $count;
        }

        // Calcule les pourcentages
        foreach ($rates as $type => &$data) {
            if ($data['total'] > 0) {
                $data['grantedRate'] = round(($data['granted'] / $data['total']) * 100, 2);
                $data['deniedRate'] = round(($data['denied'] / $data['total']) * 100, 2);
                $data['withdrawnRate'] = round(($data['withdrawn'] / $data['total']) * 100, 2);
                $data['pendingRate'] = round(($data['pending'] / $data['total']) * 100, 2);
            }
        }

        return $rates;
    }
}
