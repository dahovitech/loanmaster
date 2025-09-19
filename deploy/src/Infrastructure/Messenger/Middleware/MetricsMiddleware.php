<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Middleware pour collecter des métriques sur les messages Messenger
 */
class MetricsMiddleware implements MiddlewareInterface
{
    private const METRICS_CACHE_PREFIX = 'messenger_metrics_';
    private const METRICS_TTL = 3600; // 1 heure

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageClass = get_class($message);
        $isReceived = $envelope->last(ReceivedStamp::class) !== null;
        
        if (!$isReceived) {
            return $stack->next()->handle($envelope, $stack);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            // Traitement du message
            $envelope = $stack->next()->handle($envelope, $stack);
            
            // Collecte des métriques de succès
            $this->collectMetrics($messageClass, [
                'duration' => (microtime(true) - $startTime) * 1000,
                'memory_usage' => memory_get_usage(true) - $startMemory,
                'status' => 'success',
                'timestamp' => time()
            ]);
            
            return $envelope;
            
        } catch (\Throwable $exception) {
            // Collecte des métriques d'échec
            $this->collectMetrics($messageClass, [
                'duration' => (microtime(true) - $startTime) * 1000,
                'memory_usage' => memory_get_usage(true) - $startMemory,
                'status' => 'failed',
                'error_class' => get_class($exception),
                'timestamp' => time()
            ]);
            
            throw $exception;
        }
    }

    private function collectMetrics(string $messageClass, array $metrics): void
    {
        try {
            $cacheKey = self::METRICS_CACHE_PREFIX . md5($messageClass);
            
            // Récupérer les métriques existantes
            $existingMetrics = $this->cache->get($cacheKey, function () {
                return [
                    'total_processed' => 0,
                    'total_success' => 0,
                    'total_failed' => 0,
                    'avg_duration' => 0,
                    'max_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'avg_memory' => 0,
                    'max_memory' => 0,
                    'last_processed' => null,
                    'error_rate' => 0,
                    'throughput_per_hour' => 0
                ];
            });

            // Mise à jour des métriques
            $existingMetrics['total_processed']++;
            
            if ($metrics['status'] === 'success') {
                $existingMetrics['total_success']++;
            } else {
                $existingMetrics['total_failed']++;
            }

            // Métriques de performance
            $duration = $metrics['duration'];
            $existingMetrics['avg_duration'] = $this->calculateRunningAverage(
                $existingMetrics['avg_duration'],
                $duration,
                $existingMetrics['total_processed']
            );
            $existingMetrics['max_duration'] = max($existingMetrics['max_duration'], $duration);
            $existingMetrics['min_duration'] = min($existingMetrics['min_duration'], $duration);

            // Métriques mémoire
            $memory = $metrics['memory_usage'];
            $existingMetrics['avg_memory'] = $this->calculateRunningAverage(
                $existingMetrics['avg_memory'],
                $memory,
                $existingMetrics['total_processed']
            );
            $existingMetrics['max_memory'] = max($existingMetrics['max_memory'], $memory);

            // Taux d'erreur
            $existingMetrics['error_rate'] = ($existingMetrics['total_failed'] / $existingMetrics['total_processed']) * 100;

            // Timestamp de dernière exécution
            $existingMetrics['last_processed'] = $metrics['timestamp'];

            // Calcul du throughput (simplifié)
            $existingMetrics['throughput_per_hour'] = $this->calculateThroughput($existingMetrics);

            // Sauvegarder les métriques mises à jour
            $this->cache->set($cacheKey, $existingMetrics, self::METRICS_TTL);

            // Log des métriques pour monitoring externe
            $this->logger->info('Message metrics collected', [
                'message_class' => $messageClass,
                'total_processed' => $existingMetrics['total_processed'],
                'error_rate' => round($existingMetrics['error_rate'], 2),
                'avg_duration_ms' => round($existingMetrics['avg_duration'], 2),
                'throughput_per_hour' => $existingMetrics['throughput_per_hour']
            ]);

            // Alertes basées sur les métriques
            $this->checkMetricAlerts($messageClass, $existingMetrics);

        } catch (\Exception $e) {
            $this->logger->error('Failed to collect message metrics', [
                'message_class' => $messageClass,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function calculateRunningAverage(float $currentAvg, float $newValue, int $count): float
    {
        return ($currentAvg * ($count - 1) + $newValue) / $count;
    }

    private function calculateThroughput(array $metrics): float
    {
        // Calcul simplifié du throughput sur la dernière heure
        // Dans une implémentation réelle, on utiliserait une fenêtre glissante
        return $metrics['total_processed']; // Messages par heure (approximatif)
    }

    private function checkMetricAlerts(string $messageClass, array $metrics): void
    {
        // Alertes basées sur les seuils
        $alerts = [];

        // Taux d'erreur élevé
        if ($metrics['error_rate'] > 10 && $metrics['total_processed'] > 10) {
            $alerts[] = "High error rate: {$metrics['error_rate']}%";
        }

        // Durée de traitement élevée
        if ($metrics['avg_duration'] > 5000) { // 5 secondes
            $alerts[] = "High average duration: {$metrics['avg_duration']}ms";
        }

        // Consommation mémoire élevée
        if ($metrics['max_memory'] > 100 * 1024 * 1024) { // 100MB
            $alerts[] = "High memory usage: " . round($metrics['max_memory'] / 1024 / 1024, 2) . "MB";
        }

        // Throughput faible
        if ($metrics['throughput_per_hour'] < 1 && $metrics['total_processed'] > 5) {
            $alerts[] = "Low throughput: {$metrics['throughput_per_hour']} msg/hour";
        }

        // Log des alertes
        if (!empty($alerts)) {
            $this->logger->warning('Message processing alerts detected', [
                'message_class' => $messageClass,
                'alerts' => $alerts,
                'metrics' => $metrics
            ]);
        }
    }

    /**
     * Récupère les métriques pour un type de message
     */
    public function getMetricsForMessage(string $messageClass): array
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . md5($messageClass);
        
        return $this->cache->get($cacheKey, function () {
            return [
                'total_processed' => 0,
                'total_success' => 0,
                'total_failed' => 0,
                'error_rate' => 0
            ];
        });
    }

    /**
     * Récupère toutes les métriques disponibles
     */
    public function getAllMetrics(): array
    {
        // Cette méthode nécessiterait une implémentation pour itérer sur toutes les clés de cache
        // Pour simplifier, on retourne un tableau vide
        return [];
    }
}
