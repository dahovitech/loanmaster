<?php

namespace App\Controller\Admin;

use App\Infrastructure\EventSourcing\EventStore;
use App\Infrastructure\EventSourcing\AuditService;
use App\Infrastructure\EventSourcing\MetricsCollector;
use App\Infrastructure\EventSourcing\SnapshotStore;
use App\Application\Query\Loan\GetLoanStatisticsQuery;
use App\Infrastructure\EventSourcing\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTimeImmutable;

/**
 * Dashboard administrateur pour l'Event Sourcing
 * Visualisation complète de l'audit et des métriques
 */
#[Route('/admin/event-sourcing', name: 'admin_event_sourcing_')]
class EventSourcingDashboardController extends AbstractController
{
    private EventStore $eventStore;
    private AuditService $auditService;
    private MetricsCollector $metricsCollector;
    private SnapshotStore $snapshotStore;
    private QueryBus $queryBus;

    public function __construct(
        EventStore $eventStore,
        AuditService $auditService,
        MetricsCollector $metricsCollector,
        SnapshotStore $snapshotStore,
        QueryBus $queryBus
    ) {
        $this->eventStore = $eventStore;
        $this->auditService = $auditService;
        $this->metricsCollector = $metricsCollector;
        $this->snapshotStore = $snapshotStore;
        $this->queryBus = $queryBus;
    }

    /**
     * Dashboard principal Event Sourcing
     */
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        // Récupération des statistiques
        $query = new GetLoanStatisticsQuery();
        $loanStats = $this->queryBus->execute($query);
        
        // Statistiques des snapshots
        $snapshotStats = $this->snapshotStore->getSnapshotStatistics();
        
        // Événements récents
        $since = new DateTimeImmutable('-24 hours');
        $recentEvents = $this->eventStore->getEventsSince($since);
        
        // Rapport d'audit des dernières 24h
        $auditReport = $this->auditService->generateAuditReport(
            $since,
            new DateTimeImmutable()
        );

        return $this->render('admin/event_sourcing/dashboard.html.twig', [
            'loanStats' => $loanStats,
            'snapshotStats' => $snapshotStats,
            'recentEvents' => array_slice($recentEvents, 0, 20), // Derniers 20 événements
            'auditReport' => $auditReport,
            'totalEvents' => count($recentEvents)
        ]);
    }

    /**
     * Explorateur d'événements
     */
    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        $aggregateId = $request->query->get('aggregateId');
        $eventType = $request->query->get('eventType');
        $since = $request->query->get('since') ? 
            new DateTimeImmutable($request->query->get('since')) : 
            new DateTimeImmutable('-7 days');
        
        $events = [];
        
        if ($aggregateId) {
            $events = $this->eventStore->getAggregateEvents($aggregateId);
        } elseif ($eventType) {
            $events = $this->eventStore->getEventsByType($eventType);
        } else {
            $events = $this->eventStore->getEventsSince($since);
        }

        return $this->render('admin/event_sourcing/events.html.twig', [
            'events' => $events,
            'aggregateId' => $aggregateId,
            'eventType' => $eventType,
            'since' => $since
        ]);
    }

    /**
     * Audit trail explorer
     */
    #[Route('/audit', name: 'audit', methods: ['GET'])]
    public function audit(Request $request): Response
    {
        $entityType = $request->query->get('entityType', 'loan');
        $entityId = $request->query->get('entityId');
        $userId = $request->query->get('userId');
        $since = $request->query->get('since') ? 
            new DateTimeImmutable($request->query->get('since')) : 
            new DateTimeImmutable('-7 days');
        $until = $request->query->get('until') ? 
            new DateTimeImmutable($request->query->get('until')) : 
            new DateTimeImmutable();
        
        $auditHistory = [];
        
        if ($entityId) {
            $auditHistory = $this->auditService->getAuditHistory(
                $entityType,
                $entityId,
                $since,
                $until
            );
        } elseif ($userId) {
            $auditHistory = $this->auditService->getAuditByUser(
                $userId,
                $since,
                $until
            );
        }
        
        // Rapport d'audit pour la période
        $auditReport = $this->auditService->generateAuditReport(
            $since,
            $until,
            $entityType
        );

        return $this->render('admin/event_sourcing/audit.html.twig', [
            'auditHistory' => $auditHistory,
            'auditReport' => $auditReport,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'userId' => $userId,
            'since' => $since,
            'until' => $until
        ]);
    }

    /**
     * Métriques et monitoring
     */
    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(Request $request): Response
    {
        $metricName = $request->query->get('metric', 'execution_time');
        $since = $request->query->get('since') ? 
            new DateTimeImmutable($request->query->get('since')) : 
            new DateTimeImmutable('-24 hours');
        $until = $request->query->get('until') ? 
            new DateTimeImmutable($request->query->get('until')) : 
            new DateTimeImmutable();
        
        // Récupération des métriques
        $metrics = $this->metricsCollector->getMetrics(
            $metricName,
            $since,
            $until
        );
        
        // Statistiques des métriques
        $metricStats = $this->metricsCollector->getMetricStatistics(
            $metricName,
            $since,
            $until
        );

        return $this->render('admin/event_sourcing/metrics.html.twig', [
            'metrics' => $metrics,
            'metricStats' => $metricStats,
            'metricName' => $metricName,
            'since' => $since,
            'until' => $until
        ]);
    }

    /**
     * Gestion des snapshots
     */
    #[Route('/snapshots', name: 'snapshots', methods: ['GET'])]
    public function snapshots(Request $request): Response
    {
        $snapshotStats = $this->snapshotStore->getSnapshotStatistics();
        
        return $this->render('admin/event_sourcing/snapshots.html.twig', [
            'snapshotStats' => $snapshotStats
        ]);
    }

    /**
     * Reconstruction d'état (Time Travel)
     */
    #[Route('/time-travel', name: 'time_travel', methods: ['GET', 'POST'])]
    public function timeTravel(Request $request): Response
    {
        $result = null;
        $error = null;
        
        if ($request->isMethod('POST')) {
            try {
                $entityType = $request->request->get('entityType');
                $entityId = $request->request->get('entityId');
                $pointInTime = new DateTimeImmutable($request->request->get('pointInTime'));
                
                $result = $this->auditService->reconstructEntityState(
                    $entityType,
                    $entityId,
                    $pointInTime
                );
                
                if (!$result) {
                    $error = "Could not reconstruct state for {$entityType} {$entityId} at {$pointInTime->format('Y-m-d H:i:s')}";
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        return $this->render('admin/event_sourcing/time_travel.html.twig', [
            'result' => $result,
            'error' => $error
        ]);
    }
}
