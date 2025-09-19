<?php

namespace App\GraphQL\Resolver;

use App\Infrastructure\EventSourcing\AuditService;
use App\Infrastructure\EventSourcing\EventStore;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Résolveur GraphQL pour les requêtes d'audit
 * Conformité RGPD et traçabilité complète
 */
class AuditResolver
{
    private AuditService $auditService;
    private EventStore $eventStore;

    public function __construct(
        AuditService $auditService,
        EventStore $eventStore
    ) {
        $this->auditService = $auditService;
        $this->eventStore = $eventStore;
    }

    /**
     * Récupère l'historique d'audit
     */
    public function history($root, array $args, $context, $info): array
    {
        $entityType = $args['entityType'];
        $entityId = isset($args['entityId']) ? $args['entityId'] : null;
        $userId = $args['userId'] ?? null;
        $since = isset($args['since']) ? new DateTimeImmutable($args['since']) : null;
        $until = isset($args['until']) ? new DateTimeImmutable($args['until']) : null;
        $limit = $args['limit'] ?? 100;
        
        if ($entityId) {
            $auditEntries = $this->auditService->getAuditHistory(
                $entityType,
                $entityId,
                $since,
                $until,
                $limit
            );
        } elseif ($userId) {
            $auditEntries = $this->auditService->getAuditByUser(
                $userId,
                $since,
                $until
            );
        } else {
            // Audit général limité
            $auditEntries = [];
        }
        
        return array_map([$this, 'transformAuditEntry'], $auditEntries);
    }

    /**
     * Récupère l'historique des événements Event Sourcing
     */
    public function eventHistory($root, array $args, $context, $info): array
    {
        $aggregateId = $args['aggregateId'];
        $fromVersion = $args['fromVersion'] ?? 1;
        
        $events = $this->eventStore->getAggregateEvents($aggregateId, $fromVersion);
        
        return array_map([$this, 'transformDomainEvent'], $events);
    }

    /**
     * Reconstitue l'état d'une entité à un moment donné (Time Travel)
     */
    public function reconstructState($root, array $args, $context, $info): ?array
    {
        $entityType = $args['entityType'];
        $entityId = $args['entityId'];
        $pointInTime = new DateTimeImmutable($args['pointInTime']);
        
        $state = $this->auditService->reconstructEntityState(
            $entityType,
            $entityId,
            $pointInTime
        );
        
        if (!$state) {
            return null;
        }
        
        // Récupération des événements appliqués
        $events = $this->eventStore->getAggregateEvents($entityId);
        $appliedEvents = array_filter($events, function($event) use ($pointInTime) {
            return $event->getOccurredOn() <= $pointInTime;
        });
        
        return [
            'entityId' => $entityId,
            'entityType' => $entityType,
            'version' => $state['version'] ?? 0,
            'state' => $state,
            'pointInTime' => $pointInTime,
            'eventsApplied' => array_map([$this, 'transformDomainEvent'], $appliedEvents)
        ];
    }

    /**
     * Génère un rapport d'audit pour conformité
     */
    public function generateComplianceReport($root, array $args, $context, $info): array
    {
        $since = new DateTimeImmutable($args['since']);
        $until = new DateTimeImmutable($args['until']);
        $entityType = $args['entityType'] ?? null;
        
        $report = $this->auditService->generateAuditReport($since, $until, $entityType);
        
        return [
            'periodStart' => $since,
            'periodEnd' => $until,
            'entityType' => $entityType,
            'summary' => $this->generateReportSummary($report),
            'details' => array_map([$this, 'transformReportItem'], $report),
            'generatedAt' => new DateTimeImmutable(),
            'generatedBy' => $this->getCurrentUserId($context)
        ];
    }

    /**
     * Recherche dans l'audit trail avec critères avancés
     */
    public function searchAuditTrail($root, array $args, $context, $info): array
    {
        $criteria = $args['criteria'];
        
        // Construction de la requête de recherche
        $results = [];
        
        // TODO: Implémenter la recherche avancée dans l'audit trail
        // avec support pour les recherches textuelles, filtres multiples, etc.
        
        return [
            'results' => $results,
            'totalCount' => count($results),
            'executionTime' => 0,
            'criteria' => $criteria
        ];
    }

    /**
     * Analyse des anomalies dans l'audit trail
     */
    public function detectAnomalies($root, array $args, $context, $info): array
    {
        $since = new DateTimeImmutable($args['since'] ?? '-7 days');
        $until = new DateTimeImmutable($args['until'] ?? 'now');
        
        $anomalies = [];
        
        // Détection d'anomalies simples
        // TODO: Implémenter des algorithmes de détection d'anomalies
        
        // 1. Activité inhabituelle (trop d'événements en peu de temps)
        // 2. Accès depuis des IP suspectes
        // 3. Opérations hors heures normales
        // 4. Tentatives d'accès à des ressources non autorisées
        
        return [
            'anomalies' => $anomalies,
            'analysisStart' => $since,
            'analysisEnd' => $until,
            'riskLevel' => 'LOW', // Basé sur les anomalies détectées
            'recommendedActions' => []
        ];
    }

    /**
     * Transforme une entrée d'audit en format GraphQL
     */
    private function transformAuditEntry(array $entry): array
    {
        return [
            'id' => $entry['id'] ?? uniqid(),
            'entityType' => $entry['entity_type'],
            'entityId' => $entry['entity_id'],
            'eventType' => $entry['event_type'],
            'oldValues' => $entry['old_values'] ? json_decode($entry['old_values'], true) : null,
            'newValues' => $entry['new_values'] ? json_decode($entry['new_values'], true) : null,
            'userId' => $entry['user_id'],
            'ipAddress' => $entry['ip_address'],
            'userAgent' => $entry['user_agent'],
            'correlationId' => $entry['correlation_id'],
            'occurredAt' => $entry['occurred_at'],
            'context' => $entry['context'] ? json_decode($entry['context'], true) : null
        ];
    }

    /**
     * Transforme un événement domaine en format GraphQL
     */
    private function transformDomainEvent($event): array
    {
        return [
            'id' => uniqid('event_'),
            'aggregateId' => $event->getAggregateId(),
            'eventType' => $event->getEventName(),
            'version' => $event->getVersion(),
            'payload' => $event->getPayload(),
            'occurredAt' => $event->getOccurredOn(),
            'metadata' => $event instanceof \App\Infrastructure\EventSourcing\StoredDomainEvent ? 
                $event->getStoredMetadata() : []
        ];
    }

    /**
     * Génère un résumé du rapport d'audit
     */
    private function generateReportSummary(array $report): array
    {
        $totalEvents = array_sum(array_column($report, 'event_count'));
        $uniqueEntities = array_sum(array_column($report, 'unique_entities'));
        $uniqueUsers = array_sum(array_column($report, 'unique_users'));
        
        return [
            'totalEvents' => $totalEvents,
            'uniqueEntities' => $uniqueEntities,
            'uniqueUsers' => $uniqueUsers,
            'entityTypes' => count(array_unique(array_column($report, 'entity_type'))),
            'eventTypes' => count(array_unique(array_column($report, 'event_type')))
        ];
    }

    /**
     * Transforme un item de rapport
     */
    private function transformReportItem(array $item): array
    {
        return [
            'entityType' => $item['entity_type'],
            'eventType' => $item['event_type'],
            'eventCount' => (int) $item['event_count'],
            'uniqueEntities' => (int) $item['unique_entities'],
            'uniqueUsers' => (int) $item['unique_users'],
            'firstEvent' => $item['first_event'],
            'lastEvent' => $item['last_event']
        ];
    }

    /**
     * Récupère l'ID de l'utilisateur actuel
     */
    private function getCurrentUserId($context): ?string
    {
        return $context['user']['id'] ?? null;
    }
}
