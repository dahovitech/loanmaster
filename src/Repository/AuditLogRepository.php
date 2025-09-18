<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les logs d'audit pour une entité spécifique
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit pour un utilisateur spécifique
     */
    public function findByUser(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit par action
     */
    public function findByAction(string $action, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.action = :action')
            ->setParameter('action', $action)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit par niveau de sévérité
     */
    public function findBySeverity(string $severity, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.severity = :severity')
            ->setParameter('severity', $severity)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit à haute sévérité
     */
    public function findHighSeverityLogs(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.severity IN (:severities)')
            ->setParameter('severities', ['critical', 'high', 'error'])
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit dans une plage de dates
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.createdAt >= :startDate')
            ->andWhere('a.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des actions par jour
     */
    public function getActionStatsByDay(int $days = 30): array
    {
        $startDate = new \DateTime('-' . $days . ' days');
        
        return $this->createQueryBuilder('a')
            ->select('DATE(a.createdAt) as date, a.action, COUNT(a.id) as count')
            ->andWhere('a.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('DATE(a.createdAt), a.action')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des actions par utilisateur
     */
    public function getActionStatsByUser(int $days = 30): array
    {
        $startDate = new \DateTime('-' . $days . ' days');
        
        return $this->createQueryBuilder('a')
            ->select('a.userId, a.userName, a.action, COUNT(a.id) as count')
            ->andWhere('a.createdAt >= :startDate')
            ->andWhere('a.userId IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->groupBy('a.userId, a.userName, a.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des erreurs par IP
     */
    public function getErrorStatsByIp(int $days = 7): array
    {
        $startDate = new \DateTime('-' . $days . ' days');
        
        return $this->createQueryBuilder('a')
            ->select('a.ipAddress, COUNT(a.id) as count')
            ->andWhere('a.createdAt >= :startDate')
            ->andWhere('a.severity IN (:severities)')
            ->andWhere('a.ipAddress IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('severities', ['error', 'critical', 'high'])
            ->groupBy('a.ipAddress')
            ->orderBy('count', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les logs d'audit avec des données RGPD
     */
    public function findGdprLogs(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.gdprData IS NOT NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle dans les logs d'audit
     */
    public function search(string $query, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere(
                'a.description LIKE :query OR ' .
                'a.action LIKE :query OR ' .
                'a.entityType LIKE :query OR ' .
                'a.userName LIKE :query'
            )
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Requête flexible avec filtres multiples
     */
    public function findWithFilters(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a');

        if (isset($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $filters['entityType']);
        }

        if (isset($filters['userId'])) {
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $filters['userId']);
        }

        if (isset($filters['severity'])) {
            $qb->andWhere('a.severity = :severity')
               ->setParameter('severity', $filters['severity']);
        }

        if (isset($filters['ipAddress'])) {
            $qb->andWhere('a.ipAddress = :ipAddress')
               ->setParameter('ipAddress', $filters['ipAddress']);
        }

        if (isset($filters['startDate'])) {
            $qb->andWhere('a.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $qb->andWhere('a.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere(
                'a.description LIKE :search OR ' .
                'a.action LIKE :search OR ' .
                'a.entityType LIKE :search OR ' .
                'a.userName LIKE :search'
            )->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('a.createdAt', 'DESC')
                  ->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total de logs avec filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('a')
                   ->select('COUNT(a.id)');

        if (isset($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (isset($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $filters['entityType']);
        }

        if (isset($filters['userId'])) {
            $qb->andWhere('a.userId = :userId')
               ->setParameter('userId', $filters['userId']);
        }

        if (isset($filters['severity'])) {
            $qb->andWhere('a.severity = :severity')
               ->setParameter('severity', $filters['severity']);
        }

        if (isset($filters['startDate'])) {
            $qb->andWhere('a.createdAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $qb->andWhere('a.createdAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        if (isset($filters['search'])) {
            $qb->andWhere(
                'a.description LIKE :search OR ' .
                'a.action LIKE :search OR ' .
                'a.entityType LIKE :search OR ' .
                'a.userName LIKE :search'
            )->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Supprime les anciens logs d'audit (nettoyage automatique)
     */
    public function deleteOldLogs(int $retentionDays = 365): int
    {
        $cutoffDate = new \DateTime('-' . $retentionDays . ' days');
        
        return $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
