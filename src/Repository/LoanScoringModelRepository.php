<?php

namespace App\Repository;

use App\Entity\LoanScoringModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour les modèles de scoring IA/ML
 */
class LoanScoringModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanScoringModel::class);
    }

    /**
     * Trouve le modèle actuellement déployé en production
     */
    public function findActiveModel(): ?LoanScoringModel
    {
        return $this->findOneBy(['status' => 'deployed'], ['deployedAt' => 'DESC']);
    }

    /**
     * Trouve tous les modèles déployés (peut y en avoir plusieurs temporairement)
     */
    public function findDeployedModels(): array
    {
        return $this->findBy(['status' => 'deployed'], ['deployedAt' => 'DESC']);
    }

    /**
     * Trouve les modèles candidats au déploiement (entraînés et performants)
     */
    public function findDeploymentCandidates(float $minAccuracy = 0.80): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.status = :status')
            ->andWhere('m.accuracy >= :minAccuracy')
            ->setParameter('status', 'trained')
            ->setParameter('minAccuracy', $minAccuracy)
            ->orderBy('m.accuracy', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les modèles nécessitant un réentraînement
     */
    public function findModelsNeedingRetraining(int $maxAgeDays = 90): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$maxAgeDays} days");
        
        return $this->createQueryBuilder('m')
            ->where('m.status IN (:statuses)')
            ->andWhere('m.createdAt < :cutoffDate')
            ->setParameter('statuses', ['deployed', 'trained'])
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les modèles avec les meilleures performances par algorithme
     */
    public function findBestModelsByAlgorithm(): array
    {
        $qb = $this->createQueryBuilder('m');
        
        return $qb
            ->select('m.algorithm, MAX(m.accuracy) as maxAccuracy')
            ->where('m.status IN (:statuses)')
            ->setParameter('statuses', ['deployed', 'trained'])
            ->groupBy('m.algorithm')
            ->orderBy('maxAccuracy', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des modèles
     */
    public function getModelStatistics(): array
    {
        $qb = $this->createQueryBuilder('m');
        
        $stats = $qb
            ->select([
                'COUNT(m.id) as totalModels',
                'AVG(m.accuracy) as avgAccuracy',
                'MAX(m.accuracy) as maxAccuracy',
                'MIN(m.accuracy) as minAccuracy',
                'SUM(m.usageCount) as totalPredictions'
            ])
            ->getQuery()
            ->getSingleResult();

        // Statistiques par statut
        $statusStats = $this->createQueryBuilder('m')
            ->select('m.status, COUNT(m.id) as count')
            ->groupBy('m.status')
            ->getQuery()
            ->getResult();

        // Statistiques par algorithme
        $algorithmStats = $this->createQueryBuilder('m')
            ->select('m.algorithm, COUNT(m.id) as count, AVG(m.accuracy) as avgAccuracy')
            ->groupBy('m.algorithm')
            ->getQuery()
            ->getResult();

        return [
            'global' => $stats,
            'by_status' => array_column($statusStats, 'count', 'status'),
            'by_algorithm' => $algorithmStats
        ];
    }

    /**
     * Trouve les modèles avec filtres avancés
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createFilteredQuery($filters);
        
        return $qb
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les modèles avec filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createFilteredQuery($filters);
        
        return (int) $qb
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve l'historique de déploiement d'un modèle
     */
    public function findDeploymentHistory(string $modelId): array
    {
        // En réalité, cela viendrait de la table model_deployment_history
        // Pour l'instant, simulation basée sur les timestamps
        $model = $this->findOneBy(['modelId' => $modelId]);
        
        if (!$model) {
            return [];
        }

        $history = [];
        
        if ($model->getCreatedAt()) {
            $history[] = [
                'event' => 'created',
                'timestamp' => $model->getCreatedAt(),
                'status' => 'training'
            ];
        }
        
        if ($model->getDeployedAt()) {
            $history[] = [
                'event' => 'deployed',
                'timestamp' => $model->getDeployedAt(),
                'status' => 'deployed'
            ];
        }
        
        if ($model->getRetiredAt()) {
            $history[] = [
                'event' => 'retired',
                'timestamp' => $model->getRetiredAt(),
                'status' => 'retired'
            ];
        }
        
        return $history;
    }

    /**
     * Trouve les modèles similaires (même algorithme, performance proche)
     */
    public function findSimilarModels(LoanScoringModel $model, float $accuracyTolerance = 0.05): array
    {
        $minAccuracy = max(0, $model->getAccuracy() - $accuracyTolerance);
        $maxAccuracy = min(1, $model->getAccuracy() + $accuracyTolerance);
        
        return $this->createQueryBuilder('m')
            ->where('m.algorithm = :algorithm')
            ->andWhere('m.accuracy BETWEEN :minAccuracy AND :maxAccuracy')
            ->andWhere('m.id != :excludeId')
            ->setParameter('algorithm', $model->getAlgorithm())
            ->setParameter('minAccuracy', $minAccuracy)
            ->setParameter('maxAccuracy', $maxAccuracy)
            ->setParameter('excludeId', $model->getId())
            ->orderBy('ABS(m.accuracy - :targetAccuracy)', 'ASC')
            ->setParameter('targetAccuracy', $model->getAccuracy())
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les modèles utilisés récemment
     */
    public function findRecentlyUsed(int $days = 7, int $limit = 10): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('m')
            ->where('m.lastUsedAt >= :cutoffDate')
            ->andWhere('m.status != :retired')
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('retired', 'retired')
            ->orderBy('m.lastUsedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour les statistiques d'utilisation d'un modèle
     */
    public function updateUsageStats(string $modelId): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.usageCount', 'm.usageCount + 1')
            ->set('m.lastUsedAt', ':now')
            ->where('m.modelId = :modelId')
            ->setParameter('modelId', $modelId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Archive les anciens modèles automatiquement
     */
    public function archiveOldModels(int $keepDays = 365): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$keepDays} days");
        
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.status', ':retired')
            ->set('m.retiredAt', ':now')
            ->where('m.createdAt < :cutoffDate')
            ->andWhere('m.status NOT IN (:protectedStatuses)')
            ->setParameter('retired', 'retired')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('protectedStatuses', ['deployed'])
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les modèles par performance dans une plage donnée
     */
    public function findByPerformanceRange(
        float $minAccuracy = 0.0, 
        float $maxAccuracy = 1.0,
        string $orderBy = 'accuracy',
        string $direction = 'DESC'
    ): array {
        $validOrderFields = ['accuracy', 'precision', 'recall', 'f1Score', 'createdAt', 'usageCount'];
        $orderBy = in_array($orderBy, $validOrderFields) ? $orderBy : 'accuracy';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        
        return $this->createQueryBuilder('m')
            ->where('m.accuracy BETWEEN :minAccuracy AND :maxAccuracy')
            ->andWhere('m.status != :retired')
            ->setParameter('minAccuracy', $minAccuracy)
            ->setParameter('maxAccuracy', $maxAccuracy)
            ->setParameter('retired', 'retired')
            ->orderBy("m.{$orderBy}", $direction)
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée un QueryBuilder avec filtres appliqués
     */
    private function createFilteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m');
        
        if (isset($filters['status'])) {
            $qb->andWhere('m.status = :status')
               ->setParameter('status', $filters['status']);
        }
        
        if (isset($filters['algorithm'])) {
            $qb->andWhere('m.algorithm = :algorithm')
               ->setParameter('algorithm', $filters['algorithm']);
        }
        
        if (isset($filters['min_accuracy'])) {
            $qb->andWhere('m.accuracy >= :minAccuracy')
               ->setParameter('minAccuracy', (float) $filters['min_accuracy']);
        }
        
        if (isset($filters['max_accuracy'])) {
            $qb->andWhere('m.accuracy <= :maxAccuracy')
               ->setParameter('maxAccuracy', (float) $filters['max_accuracy']);
        }
        
        if (isset($filters['created_after'])) {
            $qb->andWhere('m.createdAt >= :createdAfter')
               ->setParameter('createdAfter', new \DateTimeImmutable($filters['created_after']));
        }
        
        if (isset($filters['created_before'])) {
            $qb->andWhere('m.createdAt <= :createdBefore')
               ->setParameter('createdBefore', new \DateTimeImmutable($filters['created_before']));
        }
        
        if (isset($filters['created_by'])) {
            $qb->andWhere('m.createdBy = :createdBy')
               ->setParameter('createdBy', $filters['created_by']);
        }
        
        // Tri par défaut
        $qb->orderBy('m.createdAt', 'DESC');
        
        return $qb;
    }

    /**
     * Sauvegarde un modèle (helper method)
     */
    public function save(LoanScoringModel $model, bool $flush = false): void
    {
        $this->getEntityManager()->persist($model);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un modèle (helper method)
     */
    public function remove(LoanScoringModel $model, bool $flush = false): void
    {
        $this->getEntityManager()->remove($model);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
