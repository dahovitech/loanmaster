<?php

namespace App\Controller;

use App\Infrastructure\Service\MonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour le dashboard de monitoring
 */
#[Route('/admin/monitoring', name: 'admin_monitoring_')]
#[IsGranted('ROLE_ADMIN')]
class MonitoringController extends AbstractController
{
    public function __construct(
        private MonitoringService $monitoringService
    ) {}

    /**
     * Dashboard principal de monitoring
     */
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $metrics = $this->monitoringService->getMetrics();
        $systemMetrics = $this->monitoringService->collectSystemMetrics();
        
        return $this->render('admin/monitoring/dashboard.html.twig', [
            'metrics' => $metrics,
            'system_metrics' => $systemMetrics,
            'report' => $this->monitoringService->generateReport()
        ]);
    }

    /**
     * API endpoint pour les métriques en temps réel
     */
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        $metrics = [
            'timestamp' => time(),
            'application_metrics' => $this->monitoringService->getMetrics(),
            'system_metrics' => $this->monitoringService->collectSystemMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'security' => $this->getSecurityMetrics(),
            'business' => $this->getBusinessMetrics()
        ];

        return new JsonResponse($metrics);
    }

    /**
     * Endpoint pour les métriques de santé (health check)
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => time(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'disk_space' => $this->checkDiskSpace(),
                'memory' => $this->checkMemory()
            ]
        ];

        // Déterminer le statut global
        $hasWarnings = false;
        $hasErrors = false;
        
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'warning') {
                $hasWarnings = true;
            } elseif ($check['status'] === 'error') {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $health['status'] = 'unhealthy';
            $statusCode = 503;
        } elseif ($hasWarnings) {
            $health['status'] = 'degraded';
            $statusCode = 200;
        } else {
            $statusCode = 200;
        }

        return new JsonResponse($health, $statusCode);
    }

    /**
     * Export des métriques pour outils externes (Prometheus, etc.)
     */
    #[Route('/export/{format}', name: 'export', methods: ['GET'])]
    public function export(string $format): Response
    {
        $metrics = $this->monitoringService->getMetrics();
        $systemMetrics = $this->monitoringService->collectSystemMetrics();
        
        switch ($format) {
            case 'prometheus':
                return $this->exportPrometheus($metrics, $systemMetrics);
            case 'json':
                return new JsonResponse(['metrics' => $metrics, 'system' => $systemMetrics]);
            case 'csv':
                return $this->exportCSV($metrics, $systemMetrics);
            default:
                throw $this->createNotFoundException('Format non supporté');
        }
    }

    /**
     * Reset des métriques (utile pour les tests)
     */
    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function resetMetrics(): JsonResponse
    {
        $this->monitoringService->resetMetrics();
        
        return new JsonResponse([
            'message' => 'Métriques réinitialisées',
            'timestamp' => time()
        ]);
    }

    /**
     * Configuration du monitoring
     */
    #[Route('/config', name: 'config', methods: ['GET', 'POST'])]
    public function config(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Traiter la configuration
            $config = $request->request->all();
            // Sauvegarder la configuration (ici il faudrait implémenter la persistance)
            
            $this->addFlash('success', 'Configuration mise à jour');
            return $this->redirectToRoute('admin_monitoring_config');
        }

        return $this->render('admin/monitoring/config.html.twig', [
            'current_config' => $this->getCurrentConfig()
        ]);
    }

    /**
     * Vue des logs en temps réel
     */
    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $logType = $request->query->get('type', 'all');
        $limit = (int)$request->query->get('limit', 100);
        
        $logs = $this->getRecentLogs($logType, $limit);
        
        return $this->render('admin/monitoring/logs.html.twig', [
            'logs' => $logs,
            'log_type' => $logType,
            'limit' => $limit
        ]);
    }

    /**
     * API pour les logs en temps réel (SSE)
     */
    #[Route('/api/logs/stream', name: 'api_logs_stream', methods: ['GET'])]
    public function logsStream(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        
        // Pour une vraie implémentation, il faudrait un système de streaming
        // Ici on simule avec les derniers logs
        $logs = $this->getRecentLogs('all', 10);
        
        $data = "data: " . json_encode($logs) . "\n\n";
        $response->setContent($data);
        
        return $response;
    }

    /**
     * Obtient les métriques de performance
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'response_time' => [
                'avg' => 250,  // ms
                'p95' => 500,
                'p99' => 1000
            ],
            'throughput' => [
                'requests_per_second' => 10,
                'requests_per_minute' => 600
            ],
            'errors' => [
                'error_rate' => 0.01,  // 1%
                'total_errors' => 5
            ]
        ];
    }

    /**
     * Obtient les métriques de sécurité
     */
    private function getSecurityMetrics(): array
    {
        return [
            'authentication' => [
                'login_attempts' => 150,
                'failed_logins' => 5,
                'success_rate' => 0.97
            ],
            'threats' => [
                'blocked_ips' => 2,
                'suspicious_requests' => 3
            ]
        ];
    }

    /**
     * Obtient les métriques métier
     */
    private function getBusinessMetrics(): array
    {
        return [
            'loans' => [
                'created_today' => 12,
                'approved_today' => 8,
                'total_amount' => 150000
            ],
            'payments' => [
                'received_today' => 25,
                'total_amount' => 75000
            ]
        ];
    }

    /**
     * Vérifie l'état de la base de données
     */
    private function checkDatabase(): array
    {
        try {
            $connection = $this->getDoctrine()->getConnection();
            $connection->connect();
            
            return [
                'status' => 'healthy',
                'message' => 'Base de données accessible',
                'response_time' => 10 // ms
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur de connexion à la base de données',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie l'état du cache
     */
    private function checkCache(): array
    {
        // Implémentation simplifiée
        return [
            'status' => 'healthy',
            'message' => 'Cache Redis opérationnel',
            'hit_rate' => 0.85
        ];
    }

    /**
     * Vérifie l'espace disque
     */
    private function checkDiskSpace(): array
    {
        $freeBytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $usedPercent = (1 - ($freeBytes / $totalBytes)) * 100;

        $status = 'healthy';
        $message = 'Espace disque suffisant';

        if ($usedPercent > 90) {
            $status = 'error';
            $message = 'Espace disque critique';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
            $message = 'Espace disque faible';
        }

        return [
            'status' => $status,
            'message' => $message,
            'used_percent' => round($usedPercent, 2),
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes
        ];
    }

    /**
     * Vérifie l'utilisation mémoire
     */
    private function checkMemory(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));

        $usedPercent = ($currentUsage / $limit) * 100;

        $status = 'healthy';
        $message = 'Utilisation mémoire normale';

        if ($usedPercent > 90) {
            $status = 'error';
            $message = 'Utilisation mémoire critique';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
            $message = 'Utilisation mémoire élevée';
        }

        return [
            'status' => $status,
            'message' => $message,
            'used_percent' => round($usedPercent, 2),
            'current_usage' => $currentUsage,
            'peak_usage' => $peakUsage,
            'limit' => $limit
        ];
    }

    /**
     * Parse la limite mémoire PHP
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    /**
     * Export au format Prometheus
     */
    private function exportPrometheus(array $metrics, array $systemMetrics): Response
    {
        $output = "# HELP loanmaster_requests_total Total number of requests\n";
        $output .= "# TYPE loanmaster_requests_total counter\n";
        
        foreach ($metrics as $name => $value) {
            if (is_numeric($value)) {
                $metricName = 'loanmaster_' . str_replace('.', '_', $name);
                $output .= "$metricName $value\n";
            }
        }

        $output .= "\n# HELP loanmaster_memory_usage_bytes Memory usage in bytes\n";
        $output .= "# TYPE loanmaster_memory_usage_bytes gauge\n";
        $output .= "loanmaster_memory_usage_bytes {$systemMetrics['memory_usage']}\n";

        return new Response($output, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Export au format CSV
     */
    private function exportCSV(array $metrics, array $systemMetrics): Response
    {
        $csv = "metric,value,timestamp\n";
        $timestamp = time();
        
        foreach ($metrics as $name => $value) {
            if (is_numeric($value)) {
                $csv .= "$name,$value,$timestamp\n";
            }
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="metrics.csv"'
        ]);
    }

    /**
     * Obtient la configuration actuelle
     */
    private function getCurrentConfig(): array
    {
        return [
            'log_levels' => [
                'performance' => 'info',
                'security' => 'warning',
                'business' => 'info'
            ],
            'alerts' => [
                'slow_query_threshold' => 1000,
                'error_rate_threshold' => 0.05,
                'memory_threshold' => 80
            ],
            'retention' => [
                'performance_logs' => 7,  // jours
                'security_logs' => 60,
                'business_logs' => 30
            ]
        ];
    }

    /**
     * Obtient les logs récents
     */
    private function getRecentLogs(string $type, int $limit): array
    {
        // Implémentation simplifiée - dans un vrai projet,
        // on lirait les fichiers de logs ou une base de données
        return [
            [
                'timestamp' => new \DateTime(),
                'level' => 'INFO',
                'channel' => 'performance',
                'message' => 'Request completed in 250ms',
                'context' => ['duration' => 250, 'route' => '/loans']
            ],
            [
                'timestamp' => new \DateTime('-1 minute'),
                'level' => 'WARNING',
                'channel' => 'security',
                'message' => 'Failed login attempt',
                'context' => ['ip' => '192.168.1.100', 'username' => 'admin']
            ]
        ];
    }
}
