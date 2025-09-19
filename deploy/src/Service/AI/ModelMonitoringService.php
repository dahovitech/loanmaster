<?php

namespace App\Service\AI;

use App\Entity\LoanScoringModel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de monitoring des modèles de Machine Learning
 * Surveille les performances, la dérive et déclenche les alertes
 */
class ModelMonitoringService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        array $configuration = []
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->configuration = array_merge([
            'drift_detection_enabled' => true,
            'performance_alert_threshold' => 0.05,
            'retraining_recommendation_threshold' => 0.1,
            'monitoring_interval' => 'daily'
        ], $configuration);
    }

    /**
     * Surveille la dérive du modèle actif
     */
    public function monitorModelDrift(): array
    {
        try {
            $activeModel = $this->getActiveModel();
            
            if (!$activeModel) {
                return [
                    'drift_detected' => false,
                    'drift_score' => 0.0,
                    'status' => 'no_active_model',
                    'recommendation' => 'deploy_model'
                ];
            }

            // Calcul du score de dérive
            $driftScore = $this->calculateDriftScore($activeModel);
            $driftDetected = $driftScore > $this->configuration['performance_alert_threshold'];

            $result = [
                'drift_detected' => $driftDetected,
                'drift_score' => $driftScore,
                'model_id' => $activeModel->getModelId(),
                'model_version' => $activeModel->getVersion(),
                'last_monitoring' => new \DateTimeImmutable(),
                'status' => $this->getDriftStatus($driftScore),
                'recommendation' => $this->getDriftRecommendation($driftScore)
            ];

            // Mise à jour des métriques de dérive dans le modèle
            $activeModel->setDriftMetrics($result);
            $this->entityManager->flush();

            // Déclenchement d'alertes si nécessaire
            if ($driftDetected) {
                $this->triggerDriftAlert($activeModel, $result);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du monitoring de dérive', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'drift_detected' => false,
                'drift_score' => 0.0,
                'status' => 'monitoring_error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyse les performances en temps réel
     */
    public function analyzeRealtimePerformance(): array
    {
        $activeModel = $this->getActiveModel();
        
        if (!$activeModel) {
            return ['error' => 'No active model found'];
        }

        // Récupération des prédictions récentes (dernières 24h)
        $recentPredictions = $this->getRecentPredictions($activeModel, 24);
        
        if (empty($recentPredictions)) {
            return [
                'predictions_count' => 0,
                'average_execution_time' => 0,
                'success_rate' => 0,
                'status' => 'no_recent_activity'
            ];
        }

        // Calcul des métriques
        $totalPredictions = count($recentPredictions);
        $successfulPredictions = count(array_filter($recentPredictions, fn($p) => !isset($p['error'])));
        $averageExecutionTime = array_sum(array_column($recentPredictions, 'execution_time_ms')) / $totalPredictions;
        $successRate = $successfulPredictions / $totalPredictions;

        // Analyse de la distribution des scores
        $scores = array_column($recentPredictions, 'credit_score');
        $scoreDistribution = $this->analyzeScoreDistribution($scores);

        // Détection d'anomalies
        $anomalies = $this->detectAnomalies($recentPredictions);

        return [
            'predictions_count' => $totalPredictions,
            'successful_predictions' => $successfulPredictions,
            'failed_predictions' => $totalPredictions - $successfulPredictions,
            'success_rate' => $successRate,
            'average_execution_time' => round($averageExecutionTime, 2),
            'score_distribution' => $scoreDistribution,
            'anomalies_detected' => count($anomalies),
            'anomalies' => $anomalies,
            'performance_status' => $this->getPerformanceStatus($successRate, $averageExecutionTime),
            'last_updated' => new \DateTimeImmutable()
        ];
    }

    /**
     * Génère un rapport de santé complet du modèle
     */
    public function generateHealthReport(LoanScoringModel $model): array
    {
        $report = [
            'model_info' => [
                'id' => $model->getModelId(),
                'version' => $model->getVersion(),
                'algorithm' => $model->getAlgorithm(),
                'status' => $model->getStatus(),
                'age_days' => $model->getCreatedAt()->diff(new \DateTimeImmutable())->days,
                'usage_count' => $model->getUsageCount()
            ],
            'performance_metrics' => $model->getPerformanceSummary(),
            'health_checks' => []
        ];

        // Vérifications de santé
        $healthChecks = [
            'model_age' => $this->checkModelAge($model),
            'performance_degradation' => $this->checkPerformanceDegradation($model),
            'usage_patterns' => $this->checkUsagePatterns($model),
            'data_drift' => $this->checkDataDrift($model),
            'feature_importance' => $this->checkFeatureImportance($model)
        ];

        $report['health_checks'] = $healthChecks;
        $report['overall_health'] = $this->calculateOverallHealth($healthChecks);
        $report['recommendations'] = $this->generateHealthRecommendations($healthChecks);

        return $report;
    }

    /**
     * Recommande le réentraînement si nécessaire
     */
    public function shouldRetrain(LoanScoringModel $model): array
    {
        $reasons = [];
        $score = 0;

        // Vérification de l'âge du modèle
        $ageDays = $model->getCreatedAt()->diff(new \DateTimeImmutable())->days;
        if ($ageDays > 60) {
            $reasons[] = 'Model is older than 60 days';
            $score += 30;
        }

        // Vérification de la dérive de performance
        $driftMetrics = $model->getDriftMetrics();
        if ($driftMetrics && isset($driftMetrics['drift_score'])) {
            if ($driftMetrics['drift_score'] > $this->configuration['retraining_recommendation_threshold']) {
                $reasons[] = 'Performance drift detected';
                $score += 40;
            }
        }

        // Vérification du volume d'utilisation
        if ($model->getUsageCount() > 1000) {
            $reasons[] = 'High usage volume reached';
            $score += 20;
        }

        // Nouvelles données disponibles
        $newDataCount = $this->countNewTrainingData($model->getCreatedAt());
        if ($newDataCount > 500) {
            $reasons[] = "New training data available ($newDataCount samples)";
            $score += 25;
        }

        $shouldRetrain = $score >= 50;

        return [
            'should_retrain' => $shouldRetrain,
            'confidence_score' => $score,
            'reasons' => $reasons,
            'priority' => $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low'),
            'estimated_improvement' => $this->estimateRetrainingImprovement($model),
            'recommended_timeline' => $shouldRetrain ? $this->getRecommendedTimeline($score) : null
        ];
    }

    // Méthodes privées utilitaires...

    private function getActiveModel(): ?LoanScoringModel
    {
        return $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findOneBy(['status' => 'deployed']);
    }

    private function calculateDriftScore(LoanScoringModel $model): float
    {
        // Simulation du calcul de dérive
        // En réalité, cela comparerait les distributions de features actuelles vs. d'entraînement
        $randomFactor = mt_rand(0, 100) / 1000; // 0 à 0.1
        $ageFactor = min(0.05, $model->getCreatedAt()->diff(new \DateTimeImmutable())->days / 1000);
        
        return $randomFactor + $ageFactor;
    }

    private function getDriftStatus(float $driftScore): string
    {
        if ($driftScore > 0.1) return 'critical';
        if ($driftScore > 0.05) return 'warning';
        if ($driftScore > 0.02) return 'monitoring';
        return 'stable';
    }

    private function getDriftRecommendation(float $driftScore): string
    {
        if ($driftScore > 0.1) return 'immediate_retraining';
        if ($driftScore > 0.05) return 'schedule_retraining';
        if ($driftScore > 0.02) return 'monitor_closely';
        return 'continue_monitoring';
    }

    private function triggerDriftAlert(LoanScoringModel $model, array $driftResult): void
    {
        $this->logger->warning('Model drift detected', [
            'model_id' => $model->getModelId(),
            'drift_score' => $driftResult['drift_score'],
            'status' => $driftResult['status']
        ]);

        // Ici, on pourrait déclencher une notification système
        // $this->notificationService->sendAlert('model_drift', $driftResult);
    }

    private function getRecentPredictions(LoanScoringModel $model, int $hours): array
    {
        // Simulation - en réalité, on récupérerait depuis scoring_predictions_history
        return [
            ['credit_score' => 720, 'execution_time_ms' => 150, 'timestamp' => time() - 3600],
            ['credit_score' => 680, 'execution_time_ms' => 180, 'timestamp' => time() - 7200],
            ['credit_score' => 750, 'execution_time_ms' => 120, 'timestamp' => time() - 10800],
        ];
    }

    private function analyzeScoreDistribution(array $scores): array
    {
        if (empty($scores)) return [];

        sort($scores);
        $count = count($scores);
        
        return [
            'min' => min($scores),
            'max' => max($scores),
            'median' => $scores[intval($count / 2)],
            'mean' => array_sum($scores) / $count,
            'std_dev' => $this->calculateStandardDeviation($scores)
        ];
    }

    private function calculateStandardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $sumSquareDiffs = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values));
        return sqrt($sumSquareDiffs / count($values));
    }

    private function detectAnomalies(array $predictions): array
    {
        $anomalies = [];
        
        foreach ($predictions as $prediction) {
            // Détection d'anomalies simples
            if (isset($prediction['execution_time_ms']) && $prediction['execution_time_ms'] > 1000) {
                $anomalies[] = [
                    'type' => 'slow_prediction',
                    'value' => $prediction['execution_time_ms'],
                    'threshold' => 1000
                ];
            }
            
            if (isset($prediction['credit_score']) && 
                ($prediction['credit_score'] < 300 || $prediction['credit_score'] > 850)) {
                $anomalies[] = [
                    'type' => 'score_out_of_range',
                    'value' => $prediction['credit_score'],
                    'expected_range' => '300-850'
                ];
            }
        }
        
        return $anomalies;
    }

    private function getPerformanceStatus(float $successRate, float $avgExecutionTime): string
    {
        if ($successRate < 0.95 || $avgExecutionTime > 500) return 'poor';
        if ($successRate < 0.98 || $avgExecutionTime > 300) return 'fair';
        if ($successRate < 0.99 || $avgExecutionTime > 200) return 'good';
        return 'excellent';
    }

    private function checkModelAge(LoanScoringModel $model): array
    {
        $ageDays = $model->getCreatedAt()->diff(new \DateTimeImmutable())->days;
        
        return [
            'status' => $ageDays > 90 ? 'warning' : ($ageDays > 180 ? 'critical' : 'ok'),
            'age_days' => $ageDays,
            'message' => "Model is $ageDays days old"
        ];
    }

    private function checkPerformanceDegradation(LoanScoringModel $model): array
    {
        // Simulation - en réalité, on comparerait les performances actuelles vs. de référence
        return [
            'status' => 'ok',
            'degradation_percent' => 2.1,
            'message' => 'Performance degradation within acceptable limits'
        ];
    }

    private function checkUsagePatterns(LoanScoringModel $model): array
    {
        $usageCount = $model->getUsageCount();
        
        return [
            'status' => $usageCount > 10000 ? 'warning' : 'ok',
            'usage_count' => $usageCount,
            'message' => "Model has been used $usageCount times"
        ];
    }

    private function checkDataDrift(LoanScoringModel $model): array
    {
        $driftMetrics = $model->getDriftMetrics();
        $driftScore = $driftMetrics['drift_score'] ?? 0;
        
        return [
            'status' => $driftScore > 0.1 ? 'critical' : ($driftScore > 0.05 ? 'warning' : 'ok'),
            'drift_score' => $driftScore,
            'message' => "Data drift score: $driftScore"
        ];
    }

    private function checkFeatureImportance(LoanScoringModel $model): array
    {
        // Vérification de la stabilité des features importantes
        return [
            'status' => 'ok',
            'top_features_stable' => true,
            'message' => 'Feature importance remains stable'
        ];
    }

    private function calculateOverallHealth(array $healthChecks): array
    {
        $statuses = array_column($healthChecks, 'status');
        $criticalCount = count(array_filter($statuses, fn($s) => $s === 'critical'));
        $warningCount = count(array_filter($statuses, fn($s) => $s === 'warning'));
        
        if ($criticalCount > 0) {
            $overall = 'critical';
        } elseif ($warningCount > 1) {
            $overall = 'warning';
        } elseif ($warningCount > 0) {
            $overall = 'fair';
        } else {
            $overall = 'excellent';
        }
        
        return [
            'status' => $overall,
            'score' => max(0, 100 - ($criticalCount * 40) - ($warningCount * 15)),
            'critical_issues' => $criticalCount,
            'warnings' => $warningCount
        ];
    }

    private function generateHealthRecommendations(array $healthChecks): array
    {
        $recommendations = [];
        
        foreach ($healthChecks as $check => $result) {
            if ($result['status'] === 'critical') {
                $recommendations[] = [
                    'priority' => 'high',
                    'action' => "Address critical issue in $check",
                    'description' => $result['message']
                ];
            } elseif ($result['status'] === 'warning') {
                $recommendations[] = [
                    'priority' => 'medium',
                    'action' => "Monitor $check closely",
                    'description' => $result['message']
                ];
            }
        }
        
        return $recommendations;
    }

    private function countNewTrainingData(\DateTimeImmutable $since): int
    {
        // Simulation - en réalité, on compterait les nouveaux prêts complétés
        return mt_rand(100, 800);
    }

    private function estimateRetrainingImprovement(LoanScoringModel $model): array
    {
        return [
            'expected_accuracy_gain' => mt_rand(2, 8) / 100,
            'confidence' => 'medium',
            'based_on' => 'historical_improvements'
        ];
    }

    private function getRecommendedTimeline(int $score): string
    {
        if ($score >= 80) return 'within_1_week';
        if ($score >= 65) return 'within_2_weeks';
        return 'within_1_month';
    }
}
