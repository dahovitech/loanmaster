<?php

namespace App\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Service de monitoring et collecte de métriques pour LoanMaster
 */
class MonitoringService
{
    private array $metrics = [];
    private array $timers = [];
    
    public function __construct(
        private LoggerInterface $performanceLogger,
        private LoggerInterface $businessLogger,
        private LoggerInterface $auditLogger,
        private RequestStack $requestStack,
        private Stopwatch $stopwatch
    ) {}

    /**
     * Démarre un timer de performance
     */
    public function startTimer(string $name, string $category = 'default'): void
    {
        $this->stopwatch->start($name, $category);
        $this->timers[$name] = microtime(true);
    }

    /**
     * Arrête un timer et log la métrique
     */
    public function stopTimer(string $name): float
    {
        $event = $this->stopwatch->stop($name);
        $duration = $event->getDuration();
        
        $this->logPerformanceMetric($name, $duration);
        
        unset($this->timers[$name]);
        return $duration;
    }

    /**
     * Log une métrique de performance
     */
    public function logPerformanceMetric(string $metric, $value, array $context = []): void
    {
        $this->performanceLogger->info('Performance metric', [
            'metric' => $metric,
            'value' => $value,
            'unit' => $context['unit'] ?? 'ms',
            'timestamp' => microtime(true),
            'request_id' => $this->getRequestId(),
            'memory_usage' => memory_get_peak_usage(true),
            'context' => $context
        ]);
    }

    /**
     * Log un événement métier
     */
    public function logBusinessEvent(string $event, array $data = [], string $userId = null): void
    {
        $this->businessLogger->info('Business event', [
            'event' => $event,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => new \DateTime(),
            'request_id' => $this->getRequestId(),
            'session_id' => $this->getSessionId()
        ]);
    }

    /**
     * Log un événement d'audit
     */
    public function logAuditEvent(string $action, array $data = [], string $userId = null): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $this->auditLogger->info('Audit event', [
            'action' => $action,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => new \DateTime(),
            'ip_address' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'request_id' => $this->getRequestId(),
            'session_id' => $this->getSessionId(),
            'request_uri' => $request?->getRequestUri(),
            'request_method' => $request?->getMethod()
        ]);
    }

    /**
     * Collecte les métriques système
     */
    public function collectSystemMetrics(): array
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_usage' => sys_getloadavg(),
            'disk_free' => disk_free_space('/'),
            'disk_total' => disk_total_space('/'),
            'timestamp' => microtime(true)
        ];

        $this->logPerformanceMetric('system_metrics', $metrics);
        
        return $metrics;
    }

    /**
     * Monitore les requêtes base de données
     */
    public function monitorDatabaseQuery(string $query, float $duration, array $params = []): void
    {
        $this->logPerformanceMetric('database_query', $duration, [
            'query' => $this->sanitizeQuery($query),
            'params_count' => count($params),
            'unit' => 'ms'
        ]);

        // Alert si la requête prend trop de temps
        if ($duration > 1000) { // Plus d'1 seconde
            $this->performanceLogger->warning('Slow database query detected', [
                'duration' => $duration,
                'query' => $this->sanitizeQuery($query),
                'request_id' => $this->getRequestId()
            ]);
        }
    }

    /**
     * Monitore l'utilisation du cache
     */
    public function monitorCacheOperation(string $operation, string $key, bool $hit = null, float $duration = null): void
    {
        $context = [
            'operation' => $operation,
            'key' => $key,
            'request_id' => $this->getRequestId()
        ];

        if ($hit !== null) {
            $context['hit'] = $hit;
        }

        if ($duration !== null) {
            $context['duration'] = $duration;
            $context['unit'] = 'ms';
        }

        $this->logPerformanceMetric('cache_operation', $context);
    }

    /**
     * Monitore les événements de prêt
     */
    public function monitorLoanEvent(string $event, array $loanData, string $userId = null): void
    {
        $this->logBusinessEvent("loan.$event", $loanData, $userId);
        
        // Métriques spécifiques aux prêts
        $this->incrementCounter("loan.$event");
        
        if (isset($loanData['amount'])) {
            $this->trackValue("loan.amount.$event", $loanData['amount']);
        }
    }

    /**
     * Monitore les événements de sécurité
     */
    public function monitorSecurityEvent(string $event, array $data = [], string $userId = null): void
    {
        $this->logAuditEvent("security.$event", $data, $userId);
        $this->incrementCounter("security.$event");
    }

    /**
     * Monitore les événements de paiement
     */
    public function monitorPaymentEvent(string $event, array $paymentData, string $userId = null): void
    {
        $this->logBusinessEvent("payment.$event", $paymentData, $userId);
        $this->incrementCounter("payment.$event");
        
        if (isset($paymentData['amount'])) {
            $this->trackValue("payment.amount.$event", $paymentData['amount']);
        }
    }

    /**
     * Incrémente un compteur
     */
    public function incrementCounter(string $metric, int $value = 1): void
    {
        if (!isset($this->metrics[$metric])) {
            $this->metrics[$metric] = 0;
        }
        
        $this->metrics[$metric] += $value;
        
        $this->logPerformanceMetric("counter.$metric", $this->metrics[$metric], [
            'type' => 'counter',
            'increment' => $value
        ]);
    }

    /**
     * Trace une valeur métrique
     */
    public function trackValue(string $metric, $value): void
    {
        $this->logPerformanceMetric("gauge.$metric", $value, [
            'type' => 'gauge'
        ]);
    }

    /**
     * Obtient les métriques actuelles
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset les métriques
     */
    public function resetMetrics(): void
    {
        $this->metrics = [];
    }

    /**
     * Obtient l'ID de la requête courante
     */
    private function getRequestId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return null;
        }

        // Générer un ID unique pour la requête si pas déjà présent
        if (!$request->attributes->has('request_id')) {
            $request->attributes->set('request_id', uniqid('req_', true));
        }

        return $request->attributes->get('request_id');
    }

    /**
     * Obtient l'ID de session
     */
    private function getSessionId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession()->getId();
    }

    /**
     * Sanitize une requête SQL pour les logs
     */
    private function sanitizeQuery(string $query): string
    {
        // Remplacer les valeurs sensibles par des placeholders
        $sanitized = preg_replace('/\b\d{16,19}\b/', '[CARD_NUMBER]', $query);
        $sanitized = preg_replace('/password\s*=\s*[\'"][^\'"]+[\'"]/', 'password=[HIDDEN]', $sanitized);
        
        return $sanitized;
    }

    /**
     * Génère un rapport de monitoring
     */
    public function generateReport(): array
    {
        return [
            'timestamp' => new \DateTime(),
            'system_metrics' => $this->collectSystemMetrics(),
            'application_metrics' => $this->getMetrics(),
            'active_timers' => array_keys($this->timers),
            'request_id' => $this->getRequestId(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ]
        ];
    }
}
