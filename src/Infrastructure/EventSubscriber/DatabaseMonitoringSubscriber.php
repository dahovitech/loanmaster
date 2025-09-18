<?php

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Service\MonitoringService;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events as ORMEvents;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Event Subscriber pour le monitoring des opérations Doctrine/Database
 */
class DatabaseMonitoringSubscriber implements EventSubscriber
{
    private array $queryTimes = [];
    private array $activeQueries = [];
    
    public function __construct(
        private MonitoringService $monitoringService
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postConnect,
            ORMEvents::preFlush,
            ORMEvents::postFlush,
            ORMEvents::postPersist,
            ORMEvents::postUpdate,
            ORMEvents::postRemove,
        ];
    }

    /**
     * Monitoring de la connexion à la base de données
     */
    public function postConnect(ConnectionEventArgs $args): void
    {
        $this->monitoringService->logPerformanceMetric('database_connection', 1, [
            'type' => 'connection_established',
            'platform' => $args->getConnection()->getDatabasePlatform()->getName()
        ]);
        
        $this->monitoringService->incrementCounter('database.connections');
    }

    /**
     * Monitoring avant le flush Doctrine
     */
    public function preFlush(PreFlushEventArgs $args): void
    {
        $this->monitoringService->startTimer('doctrine_flush');
        
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        
        $scheduledInserts = count($unitOfWork->getScheduledEntityInsertions());
        $scheduledUpdates = count($unitOfWork->getScheduledEntityUpdates());
        $scheduledDeletes = count($unitOfWork->getScheduledEntityDeletions());
        
        $this->monitoringService->logPerformanceMetric('doctrine_preflush', [
            'insertions' => $scheduledInserts,
            'updates' => $scheduledUpdates,
            'deletions' => $scheduledDeletes,
            'total' => $scheduledInserts + $scheduledUpdates + $scheduledDeletes
        ]);
    }

    /**
     * Monitoring après le flush Doctrine
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        $duration = $this->monitoringService->stopTimer('doctrine_flush');
        
        $this->monitoringService->logPerformanceMetric('doctrine_flush_completed', $duration, [
            'unit' => 'ms'
        ]);
        
        $this->monitoringService->incrementCounter('doctrine.flushes');
        
        // Alert si le flush prend trop de temps
        if ($duration > 1000) { // Plus d'1 seconde
            $this->monitoringService->logPerformanceMetric('doctrine_slow_flush', $duration, [
                'alert' => true,
                'threshold' => 1000
            ]);
        }
    }

    /**
     * Monitoring des entités persistées
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);
        $entityType = $this->getEntityType($entityClass);
        
        $this->monitoringService->logPerformanceMetric('entity_persisted', 1, [
            'entity_class' => $entityClass,
            'entity_type' => $entityType
        ]);
        
        $this->monitoringService->incrementCounter('doctrine.persists');
        $this->monitoringService->incrementCounter("doctrine.persists.{$entityType}");
        
        // Monitoring spécifique pour les entités métier importantes
        if ($entityType === 'loan') {
            $this->monitoringService->logBusinessEvent('entity_loan_created', [
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'entity_class' => $entityClass
            ]);
        }
    }

    /**
     * Monitoring des entités mises à jour
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);
        $entityType = $this->getEntityType($entityClass);
        
        $this->monitoringService->logPerformanceMetric('entity_updated', 1, [
            'entity_class' => $entityClass,
            'entity_type' => $entityType
        ]);
        
        $this->monitoringService->incrementCounter('doctrine.updates');
        $this->monitoringService->incrementCounter("doctrine.updates.{$entityType}");
        
        // Monitoring spécifique pour les entités métier importantes
        if ($entityType === 'loan') {
            $this->monitoringService->logBusinessEvent('entity_loan_updated', [
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'entity_class' => $entityClass
            ]);
        }
    }

    /**
     * Monitoring des entités supprimées
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityClass = get_class($entity);
        $entityType = $this->getEntityType($entityClass);
        
        $this->monitoringService->logPerformanceMetric('entity_removed', 1, [
            'entity_class' => $entityClass,
            'entity_type' => $entityType
        ]);
        
        $this->monitoringService->incrementCounter('doctrine.removes');
        $this->monitoringService->incrementCounter("doctrine.removes.{$entityType}");
        
        // Monitoring spécifique pour les entités métier importantes
        if ($entityType === 'loan') {
            $this->monitoringService->logAuditEvent('entity_loan_deleted', [
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'entity_class' => $entityClass
            ]);
        }
    }

    /**
     * Extrait le type d'entité à partir du nom de classe
     */
    private function getEntityType(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        $className = end($parts);
        
        // Convertir en snake_case pour les métriques
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className));
    }
}
