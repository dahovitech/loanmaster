<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use App\Repository\UserConsentRepository;
use App\Service\Audit\AuditLoggerService;
use App\Service\GDPR\GDPRService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/compliance', name: 'admin_compliance_')]
#[IsGranted('ROLE_ADMIN')]
class ComplianceDashboardController extends AbstractController
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private UserConsentRepository $userConsentRepository,
        private AuditLoggerService $auditLogger,
        private GDPRService $gdprService
    ) {}

    #[Route('/', name: 'dashboard')]
    public function dashboard(): Response
    {
        // Statistiques générales
        $stats = [
            'audit' => [
                'totalLogs' => $this->auditLogRepository->count([]),
                'highSeverityLogs' => count($this->auditLogRepository->findHighSeverityLogs(100)),
                'recentLogs' => count($this->auditLogRepository->findByDateRange(new \DateTime('-7 days'), new \DateTime(), 100)),
            ],
            'gdpr' => $this->gdprService->getComplianceStats(),
        ];

        return $this->render('admin/compliance/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/audit-logs', name: 'audit_logs')]
    public function auditLogs(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Filtres
        $filters = [
            'action' => $request->query->get('action'),
            'entityType' => $request->query->get('entityType'),
            'severity' => $request->query->get('severity'),
            'userId' => $request->query->get('userId'),
            'search' => $request->query->get('search'),
        ];

        // Dates
        if ($request->query->get('startDate')) {
            $filters['startDate'] = new \DateTime($request->query->get('startDate'));
        }
        if ($request->query->get('endDate')) {
            $filters['endDate'] = new \DateTime($request->query->get('endDate'));
        }

        // Supprimer les filtres vides
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $auditLogs = $this->auditLogRepository->findWithFilters($filters, $limit, $offset);
        $totalLogs = $this->auditLogRepository->countWithFilters($filters);
        $totalPages = ceil($totalLogs / $limit);

        // Statistiques pour les filtres
        $availableActions = $this->getAvailableActions();
        $availableEntityTypes = $this->getAvailableEntityTypes();

        return $this->render('admin/compliance/audit_logs.html.twig', [
            'auditLogs' => $auditLogs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'filters' => $filters,
            'availableActions' => $availableActions,
            'availableEntityTypes' => $availableEntityTypes,
        ]);
    }

    #[Route('/audit-logs/{id}', name: 'audit_log_detail')]
    public function auditLogDetail(int $id): Response
    {
        $auditLog = $this->auditLogRepository->find($id);
        
        if (!$auditLog) {
            throw $this->createNotFoundException('Log d\'audit non trouvé');
        }

        // Logs liés (même entité, utilisateur, etc.)
        $relatedLogs = [];
        if ($auditLog->getEntityType() && $auditLog->getEntityId()) {
            $relatedLogs = $this->auditLogRepository->findByEntity(
                $auditLog->getEntityType(),
                $auditLog->getEntityId(),
                10
            );
        }

        return $this->render('admin/compliance/audit_log_detail.html.twig', [
            'auditLog' => $auditLog,
            'relatedLogs' => $relatedLogs,
        ]);
    }

    #[Route('/consent-management', name: 'consent_management')]
    public function consentManagement(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Filtres
        $filters = [
            'userId' => $request->query->get('userId'),
            'consentType' => $request->query->get('consentType'),
            'status' => $request->query->get('status'),
            'expiring' => $request->query->getBoolean('expiring'),
            'expired' => $request->query->getBoolean('expired'),
        ];

        // Dates
        if ($request->query->get('startDate')) {
            $filters['startDate'] = new \DateTime($request->query->get('startDate'));
        }
        if ($request->query->get('endDate')) {
            $filters['endDate'] = new \DateTime($request->query->get('endDate'));
        }

        // Supprimer les filtres vides
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $consents = $this->userConsentRepository->findWithFilters($filters, $limit, $offset);
        $totalConsents = $this->userConsentRepository->countWithFilters($filters);
        $totalPages = ceil($totalConsents / $limit);

        // Statistiques
        $stats = [
            'consentRates' => $this->userConsentRepository->getConsentRates(),
            'expiringSoon' => count($this->gdprService->getExpiringSoonConsents()),
        ];

        return $this->render('admin/compliance/consent_management.html.twig', [
            'consents' => $consents,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalConsents' => $totalConsents,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    #[Route('/gdpr-requests', name: 'gdpr_requests')]
    public function gdprRequests(): Response
    {
        // Récupérer les logs d'audit liés aux demandes RGPD
        $gdprLogs = $this->auditLogRepository->findGdprLogs(100);

        return $this->render('admin/compliance/gdpr_requests.html.twig', [
            'gdprLogs' => $gdprLogs,
        ]);
    }

    #[Route('/statistics', name: 'statistics')]
    public function statistics(Request $request): Response
    {
        $days = $request->query->getInt('days', 30);
        
        // Statistiques d'audit
        $auditStats = [
            'actionsByDay' => $this->auditLogRepository->getActionStatsByDay($days),
            'actionsByUser' => $this->auditLogRepository->getActionStatsByUser($days),
            'errorsByIp' => $this->auditLogRepository->getErrorStatsByIp(7),
        ];

        // Statistiques de consentement
        $consentStats = [
            'byDay' => $this->userConsentRepository->getConsentStatsByDay($days),
            'byType' => $this->userConsentRepository->getConsentStatsByType(),
            'rates' => $this->userConsentRepository->getConsentRates(),
            'withdrawn' => count($this->userConsentRepository->findWithdrawnConsents($days)),
        ];

        return $this->render('admin/compliance/statistics.html.twig', [
            'auditStats' => $auditStats,
            'consentStats' => $consentStats,
            'days' => $days,
        ]);
    }

    #[Route('/export', name: 'export')]
    public function export(Request $request): Response
    {
        $type = $request->query->get('type', 'audit_logs');
        $format = $request->query->get('format', 'csv');

        try {
            switch ($type) {
                case 'audit_logs':
                    return $this->exportAuditLogs($format, $request);
                case 'consents':
                    return $this->exportConsents($format, $request);
                default:
                    throw new \InvalidArgumentException('Type d\'export non supporté');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_compliance_dashboard');
        }
    }

    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function apiStats(): JsonResponse
    {
        $stats = [
            'audit' => [
                'total' => $this->auditLogRepository->count([]),
                'today' => count($this->auditLogRepository->findByDateRange(new \DateTime('today'), new \DateTime(), 1000)),
                'highSeverity' => count($this->auditLogRepository->findHighSeverityLogs(100)),
            ],
            'consent' => [
                'total' => $this->userConsentRepository->count([]),
                'active' => count($this->userConsentRepository->findBy(['status' => 'granted'])),
                'expiring' => count($this->gdprService->getExpiringSoonConsents()),
            ],
        ];

        return $this->json($stats);
    }

    #[Route('/api/recent-activity', name: 'api_recent_activity', methods: ['GET'])]
    public function apiRecentActivity(): JsonResponse
    {
        $recentLogs = $this->auditLogRepository->findByDateRange(
            new \DateTime('-24 hours'),
            new \DateTime(),
            20
        );

        $activity = array_map(function($log) {
            return [
                'id' => $log->getId(),
                'action' => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'userName' => $log->getUserName(),
                'severity' => $log->getSeverity(),
                'description' => $log->getDescription(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $recentLogs);

        return $this->json($activity);
    }

    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cleanup', $request->request->get('_token'))) {
            return $this->json(['error' => 'Token CSRF invalide'], 403);
        }

        try {
            // Nettoyage des logs d'audit
            $deletedLogs = $this->auditLogger->cleanup();
            
            // Nettoyage RGPD
            $gdprCleanup = $this->gdprService->cleanup();

            return $this->json([
                'success' => true,
                'deletedLogs' => $deletedLogs,
                'gdprCleanup' => $gdprCleanup,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function exportAuditLogs(string $format, Request $request): Response
    {
        // Récupérer les logs selon les filtres
        $startDate = $request->query->get('startDate') 
            ? new \DateTime($request->query->get('startDate'))
            : new \DateTime('-30 days');
        $endDate = $request->query->get('endDate')
            ? new \DateTime($request->query->get('endDate'))
            : new \DateTime();

        $logs = $this->auditLogRepository->findByDateRange($startDate, $endDate, 1000);

        if ($format === 'csv') {
            $csv = "ID,Action,Entité,ID Entité,Utilisateur,IP,Sévérité,Description,Date\n";
            foreach ($logs as $log) {
                $csv .= sprintf(
                    "%d,%s,%s,%s,%s,%s,%s,\"%s\",%s\n",
                    $log->getId(),
                    $log->getAction(),
                    $log->getEntityType() ?: '',
                    $log->getEntityId() ?: '',
                    $log->getUserName() ?: '',
                    $log->getIpAddress() ?: '',
                    $log->getSeverity(),
                    str_replace('"', '""', $log->getDescription() ?: ''),
                    $log->getCreatedAt()->format('Y-m-d H:i:s')
                );
            }

            $response = new Response($csv);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs.csv"');
            
            return $response;
        }

        throw new \InvalidArgumentException('Format d\'export non supporté');
    }

    private function exportConsents(string $format, Request $request): Response
    {
        $consents = $this->userConsentRepository->findAll();

        if ($format === 'csv') {
            $csv = "ID,Utilisateur ID,Type,Statut,Créé le,Mis à jour le,Expire le,Version\n";
            foreach ($consents as $consent) {
                $csv .= sprintf(
                    "%d,%d,%s,%s,%s,%s,%s,%s\n",
                    $consent->getId(),
                    $consent->getUserId(),
                    $consent->getConsentType(),
                    $consent->getStatus(),
                    $consent->getCreatedAt()->format('Y-m-d H:i:s'),
                    $consent->getUpdatedAt() ? $consent->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                    $consent->getExpiresAt() ? $consent->getExpiresAt()->format('Y-m-d H:i:s') : '',
                    $consent->getVersion()
                );
            }

            $response = new Response($csv);
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="consents.csv"');
            
            return $response;
        }

        throw new \InvalidArgumentException('Format d\'export non supporté');
    }

    private function getAvailableActions(): array
    {
        // Cette méthode pourrait être optimisée avec une requête SQL distincte
        return [
            'login', 'logout', 'login_failed', 'create', 'update', 'delete', 'view',
            'consent_granted', 'consent_withdrawn', 'data_exported', 'data_anonymized'
        ];
    }

    private function getAvailableEntityTypes(): array
    {
        // Cette méthode pourrait être optimisée avec une requête SQL distincte
        return [
            'User', 'LoanApplication', 'UserConsent', 'AuditLog', 'GDPR'
        ];
    }
}
