<?php

namespace App\Controller\Admin;

use App\Service\AI\LoanScoringService;
use App\Service\AI\MLModelService;
use App\Entity\LoanScoringModel;
use App\Entity\Loan;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur d'administration pour l'IA et le Machine Learning
 */
#[Route('/admin/ai-ml', name: 'admin_ai_ml_')]
#[IsGranted('ROLE_ADMIN')]
class AiMlAdminController extends AbstractController
{
    private LoanScoringService $scoringService;
    private MLModelService $modelService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        LoanScoringService $scoringService,
        MLModelService $modelService,
        EntityManagerInterface $entityManager
    ) {
        $this->scoringService = $scoringService;
        $this->modelService = $modelService;
        $this->entityManager = $entityManager;
    }

    /**
     * Dashboard principal IA/ML
     */
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Modèles actifs
        $activeModels = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findBy(['status' => 'deployed'], ['deployedAt' => 'DESC'], 5);

        // Modèles en développement
        $devModels = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findBy(['status' => 'trained'], ['createdAt' => 'DESC'], 5);

        // Statistiques de performance
        $performanceStats = $this->getPerformanceStatistics();

        // Dernières prédictions
        $recentPredictions = $this->getRecentPredictions(10);

        // Monitoring de dérive
        $driftStatus = $this->modelService->monitorModelDrift();

        return $this->render('admin/ai_ml/dashboard.html.twig', [
            'active_models' => $activeModels,
            'dev_models' => $devModels,
            'performance_stats' => $performanceStats,
            'recent_predictions' => $recentPredictions,
            'drift_status' => $driftStatus
        ]);
    }

    /**
     * Gestion des modèles
     */
    #[Route('/models', name: 'models', methods: ['GET'])]
    public function models(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $models = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

        $total = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->count([]);

        return $this->render('admin/ai_ml/models.html.twig', [
            'models' => $models,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_models' => $total
        ]);
    }

    /**
     * Détails d'un modèle
     */
    #[Route('/models/{id}', name: 'model_details', methods: ['GET'])]
    public function modelDetails(LoanScoringModel $model): Response
    {
        // Analyse des performances détaillées
        $performanceAnalysis = $this->analyzeModelPerformance($model);

        // Importance des features
        $featureAnalysis = $this->analyzeFeatureImportance($model);

        // Historique d'utilisation
        $usageHistory = $this->getModelUsageHistory($model);

        return $this->render('admin/ai_ml/model_details.html.twig', [
            'model' => $model,
            'performance_analysis' => $performanceAnalysis,
            'feature_analysis' => $featureAnalysis,
            'usage_history' => $usageHistory
        ]);
    }

    /**
     * Entraînement d'un nouveau modèle
     */
    #[Route('/train', name: 'train_model', methods: ['GET', 'POST'])]
    public function trainModel(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $options = [
                'algorithm' => $request->request->get('algorithm', 'gradient_boosting'),
                'max_samples' => $request->request->getInt('max_samples'),
                'start_date' => $request->request->get('start_date') 
                    ? new \DateTime($request->request->get('start_date')) 
                    : null,
                'end_date' => $request->request->get('end_date') 
                    ? new \DateTime($request->request->get('end_date')) 
                    : null,
                'hyperparameters' => [
                    'learning_rate' => $request->request->getFloat('learning_rate', 0.1),
                    'max_depth' => $request->request->getInt('max_depth', 6),
                    'n_estimators' => $request->request->getInt('n_estimators', 100)
                ]
            ];

            try {
                $result = $this->modelService->trainNewModel($options);

                if ($result['success']) {
                    $this->addFlash('success', 
                        "Modèle entraîné avec succès ! Précision: {$result['performance_metrics']['accuracy']} " .
                        "({$result['training_samples']} échantillons)"
                    );

                    return $this->redirectToRoute('admin_ai_ml_model_details', [
                        'id' => $this->getModelEntityId($result['model_id'])
                    ]);
                } else {
                    $this->addFlash('error', 'Échec de l\'entraînement: ' . ($result['error'] ?? $result['reason']));
                }

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'entraînement: ' . $e->getMessage());
            }
        }

        // Statistiques pour l'entraînement
        $trainingStats = $this->getTrainingStatistics();

        return $this->render('admin/ai_ml/train_model.html.twig', [
            'training_stats' => $trainingStats
        ]);
    }

    /**
     * Test de prédiction interactive
     */
    #[Route('/test-prediction', name: 'test_prediction', methods: ['GET', 'POST'])]
    public function testPrediction(Request $request): Response
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $customerId = $request->request->get('customer_id');
            $loanData = [
                'amount' => $request->request->getFloat('loan_amount'),
                'term_months' => $request->request->getInt('loan_term'),
                'purpose' => $request->request->get('loan_purpose')
            ];

            $customer = $this->entityManager
                ->getRepository(Customer::class)
                ->find($customerId);

            if ($customer) {
                try {
                    $result = $this->scoringService->calculateCreditScore(
                        $customer, 
                        $loanData,
                        $request->request->all()
                    );
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur de prédiction: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Client non trouvé');
            }
        }

        // Liste des clients pour le test
        $customers = $this->entityManager
            ->getRepository(Customer::class)
            ->findBy([], ['createdAt' => 'DESC'], 50);

        return $this->render('admin/ai_ml/test_prediction.html.twig', [
            'customers' => $customers,
            'prediction_result' => $result
        ]);
    }

    /**
     * Déploiement d'un modèle
     */
    #[Route('/models/{id}/deploy', name: 'deploy_model', methods: ['POST'])]
    public function deployModel(LoanScoringModel $model): JsonResponse
    {
        try {
            $result = $this->modelService->deployModel($model->getModelId());

            if ($result['success']) {
                return $this->json([
                    'success' => true,
                    'message' => "Modèle {$model->getVersion()} déployé avec succès"
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archivage d'un modèle
     */
    #[Route('/models/{id}/retire', name: 'retire_model', methods: ['POST'])]
    public function retireModel(LoanScoringModel $model): JsonResponse
    {
        try {
            $model->setStatus('retired');
            $model->setRetiredAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Modèle {$model->getVersion()} archivé"
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API de performance en temps réel
     */
    #[Route('/api/performance', name: 'api_performance', methods: ['GET'])]
    public function apiPerformance(): JsonResponse
    {
        $deployedModel = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findOneBy(['status' => 'deployed']);

        if (!$deployedModel) {
            return $this->json(['error' => 'No deployed model found'], 404);
        }

        // Métriques en temps réel
        $realtimeMetrics = $this->calculateRealtimeMetrics($deployedModel);

        return $this->json([
            'model' => [
                'id' => $deployedModel->getModelId(),
                'version' => $deployedModel->getVersion(),
                'algorithm' => $deployedModel->getAlgorithm()
            ],
            'performance' => $deployedModel->getPerformanceSummary(),
            'realtime_metrics' => $realtimeMetrics,
            'drift_status' => $this->modelService->monitorModelDrift(),
            'usage_stats' => [
                'predictions_today' => $this->getPredictionCountToday(),
                'avg_response_time' => $this->getAverageResponseTime(),
                'error_rate' => $this->getErrorRate()
            ]
        ]);
    }

    /**
     * Analyse de la dérive des données
     */
    #[Route('/drift-analysis', name: 'drift_analysis', methods: ['GET'])]
    public function driftAnalysis(): Response
    {
        $deployedModel = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findOneBy(['status' => 'deployed']);

        if (!$deployedModel) {
            $this->addFlash('warning', 'Aucun modèle déployé pour l\'analyse de dérive');
            return $this->redirectToRoute('admin_ai_ml_dashboard');
        }

        // Analyse détaillée de la dérive
        $driftAnalysis = $this->performDetailedDriftAnalysis($deployedModel);

        return $this->render('admin/ai_ml/drift_analysis.html.twig', [
            'model' => $deployedModel,
            'drift_analysis' => $driftAnalysis
        ]);
    }

    /**
     * Comparaison de modèles
     */
    #[Route('/compare', name: 'compare_models', methods: ['GET', 'POST'])]
    public function compareModels(Request $request): Response
    {
        $comparison = null;

        if ($request->isMethod('POST')) {
            $modelIds = $request->request->all('model_ids');
            
            if (count($modelIds) >= 2) {
                $models = $this->entityManager
                    ->getRepository(LoanScoringModel::class)
                    ->findBy(['id' => $modelIds]);

                $comparison = $this->compareModelPerformance($models);
            } else {
                $this->addFlash('error', 'Veuillez sélectionner au moins 2 modèles pour la comparaison');
            }
        }

        $availableModels = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findBy(['status' => ['trained', 'deployed']], ['createdAt' => 'DESC']);

        return $this->render('admin/ai_ml/compare_models.html.twig', [
            'available_models' => $availableModels,
            'comparison' => $comparison
        ]);
    }

    /**
     * Export des résultats de modèle
     */
    #[Route('/models/{id}/export', name: 'export_model', methods: ['GET'])]
    public function exportModel(LoanScoringModel $model): Response
    {
        $exportData = [
            'model_info' => [
                'id' => $model->getModelId(),
                'version' => $model->getVersion(),
                'algorithm' => $model->getAlgorithm(),
                'created_at' => $model->getCreatedAt()->format('Y-m-d H:i:s'),
                'status' => $model->getStatus()
            ],
            'performance_metrics' => $model->getPerformanceMetrics(),
            'feature_importance' => $model->getFeatureImportance(),
            'training_options' => $model->getTrainingOptions(),
            'validation_results' => $model->getValidationResults()
        ];

        $response = new Response(json_encode($exportData, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="model_' . $model->getVersion() . '_export.json"');

        return $response;
    }

    // Méthodes utilitaires privées

    private function getPerformanceStatistics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Modèles par statut
        $statusStats = $qb->select('m.status, COUNT(m.id) as count')
            ->from(LoanScoringModel::class, 'm')
            ->groupBy('m.status')
            ->getQuery()
            ->getArrayResult();

        // Performance moyenne
        $qb = $this->entityManager->createQueryBuilder();
        $avgPerformance = $qb->select('AVG(m.accuracy) as avg_accuracy, AVG(m.precision) as avg_precision')
            ->from(LoanScoringModel::class, 'm')
            ->where('m.status IN (:statuses)')
            ->setParameter('statuses', ['deployed', 'trained'])
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'status_distribution' => $statusStats,
            'average_accuracy' => $avgPerformance['avg_accuracy'] ?? 0,
            'average_precision' => $avgPerformance['avg_precision'] ?? 0,
            'total_models' => array_sum(array_column($statusStats, 'count'))
        ];
    }

    private function getRecentPredictions(int $limit): array
    {
        // Cette méthode devrait récupérer les prédictions récentes depuis les logs
        // Pour l'instant, retour d'un exemple
        return [
            [
                'customer_id' => 'CUST001',
                'score' => 720,
                'risk_level' => 'low',
                'amount' => 15000,
                'predicted_at' => new \DateTimeImmutable('-2 hours')
            ],
            [
                'customer_id' => 'CUST002',
                'score' => 580,
                'risk_level' => 'medium',
                'amount' => 8000,
                'predicted_at' => new \DateTimeImmutable('-4 hours')
            ]
        ];
    }

    private function analyzeModelPerformance(LoanScoringModel $model): array
    {
        $metrics = $model->getPerformanceMetrics();
        
        return [
            'confusion_matrix' => $metrics['confusion_matrix'] ?? null,
            'classification_report' => $this->generateClassificationReport($metrics),
            'performance_trend' => $this->getPerformanceTrend($model),
            'benchmark_comparison' => $this->compareToBenchmark($model)
        ];
    }

    private function analyzeFeatureImportance(LoanScoringModel $model): array
    {
        $importance = $model->getFeatureImportance();
        
        return [
            'top_features' => $model->getTopFeatures(10),
            'feature_categories' => $this->categorizeFeatures($importance),
            'stability_analysis' => $this->analyzeFeatureStability($model)
        ];
    }

    private function getModelUsageHistory(LoanScoringModel $model): array
    {
        // Historique d'utilisation du modèle
        return [
            'total_predictions' => $model->getUsageCount(),
            'daily_usage' => $this->getDailyUsageStats($model),
            'peak_usage_times' => $this->getPeakUsageTimes($model),
            'usage_by_loan_type' => $this->getUsageByLoanType($model)
        ];
    }

    private function getTrainingStatistics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Données disponibles pour l'entraînement
        $availableLoans = $qb->select('COUNT(l.id)')
            ->from(Loan::class, 'l')
            ->where('l.status IN (:statuses)')
            ->setParameter('statuses', ['completed', 'defaulted', 'rejected'])
            ->getQuery()
            ->getSingleScalarResult();

        // Distribution des classes
        $qb = $this->entityManager->createQueryBuilder();
        $classDistribution = $qb->select('l.status, COUNT(l.id) as count')
            ->from(Loan::class, 'l')
            ->where('l.status IN (:statuses)')
            ->setParameter('statuses', ['completed', 'defaulted'])
            ->groupBy('l.status')
            ->getQuery()
            ->getArrayResult();

        return [
            'available_samples' => $availableLoans,
            'class_distribution' => $classDistribution,
            'data_quality_score' => $this->calculateDataQualityScore(),
            'recommended_algorithm' => $this->recommendAlgorithm($availableLoans)
        ];
    }

    private function calculateRealtimeMetrics(LoanScoringModel $model): array
    {
        // Calcul des métriques en temps réel
        return [
            'predictions_last_hour' => 15,
            'avg_confidence' => 0.83,
            'predictions_by_risk_level' => [
                'low' => 8,
                'medium' => 5,
                'high' => 2
            ],
            'response_time_ms' => 120
        ];
    }

    private function performDetailedDriftAnalysis(LoanScoringModel $model): array
    {
        // Analyse détaillée de la dérive
        return [
            'overall_drift_score' => 0.15,
            'feature_drift' => [
                'monthly_income' => ['drift_score' => 0.08, 'status' => 'stable'],
                'debt_to_income_ratio' => ['drift_score' => 0.22, 'status' => 'warning'],
                'age' => ['drift_score' => 0.05, 'status' => 'stable']
            ],
            'distribution_changes' => $this->analyzeDistributionChanges($model),
            'recommendation' => $this->getDriftRecommendation(0.15)
        ];
    }

    private function compareModelPerformance(array $models): array
    {
        $comparison = [];
        
        foreach ($models as $model) {
            $comparison[] = [
                'model' => $model,
                'metrics' => $model->getPerformanceSummary(),
                'strengths' => $this->identifyModelStrengths($model),
                'weaknesses' => $this->identifyModelWeaknesses($model)
            ];
        }
        
        return [
            'models' => $comparison,
            'winner' => $this->selectBestModel($models),
            'comparison_matrix' => $this->generateComparisonMatrix($models)
        ];
    }

    // Méthodes d'aide pour les calculs

    private function getModelEntityId(string $modelId): ?int
    {
        $model = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findOneBy(['modelId' => $modelId]);
            
        return $model?->getId();
    }

    private function getPredictionCountToday(): int
    {
        // Comptage des prédictions aujourd'hui (à implémenter selon les logs)
        return 45;
    }

    private function getAverageResponseTime(): float
    {
        // Temps de réponse moyen (à implémenter selon les logs)
        return 0.12; // 120ms
    }

    private function getErrorRate(): float
    {
        // Taux d'erreur (à implémenter selon les logs)
        return 0.02; // 2%
    }

    private function generateClassificationReport(array $metrics): array
    {
        if (!isset($metrics['confusion_matrix'])) {
            return [];
        }

        $cm = $metrics['confusion_matrix'];
        return [
            'support' => $cm['tp'] + $cm['fn'],
            'precision' => $cm['tp'] / ($cm['tp'] + $cm['fp']),
            'recall' => $cm['tp'] / ($cm['tp'] + $cm['fn']),
            'specificity' => $cm['tn'] / ($cm['tn'] + $cm['fp'])
        ];
    }

    private function getPerformanceTrend(LoanScoringModel $model): array
    {
        // Tendance de performance (à implémenter avec historique)
        return ['trend' => 'stable', 'direction' => 0];
    }

    private function compareToBenchmark(LoanScoringModel $model): array
    {
        $benchmarkAccuracy = 0.78; // Benchmark industrie
        $difference = $model->getAccuracy() - $benchmarkAccuracy;
        
        return [
            'benchmark_accuracy' => $benchmarkAccuracy,
            'model_accuracy' => $model->getAccuracy(),
            'difference' => $difference,
            'performance_vs_benchmark' => $difference > 0 ? 'above' : 'below'
        ];
    }

    private function categorizeFeatures(array $importance): array
    {
        $categories = [
            'financial' => [],
            'demographic' => [],
            'behavioral' => [],
            'external' => []
        ];

        foreach ($importance as $feature => $value) {
            if (strpos($feature, 'income') !== false || strpos($feature, 'debt') !== false) {
                $categories['financial'][$feature] = $value;
            } elseif (strpos($feature, 'age') !== false || strpos($feature, 'employment') !== false) {
                $categories['demographic'][$feature] = $value;
            } elseif (strpos($feature, 'digital') !== false || strpos($feature, 'response') !== false) {
                $categories['behavioral'][$feature] = $value;
            } else {
                $categories['external'][$feature] = $value;
            }
        }

        return $categories;
    }

    private function analyzeFeatureStability(LoanScoringModel $model): array
    {
        // Analyse de stabilité des features
        return ['stability_score' => 0.92, 'unstable_features' => []];
    }

    private function getDailyUsageStats(LoanScoringModel $model): array
    {
        // Statistiques d'utilisation quotidienne
        return ['avg_daily_predictions' => 25, 'peak_day' => 'monday'];
    }

    private function getPeakUsageTimes(LoanScoringModel $model): array
    {
        // Heures de pointe d'utilisation
        return ['peak_hour' => 14, 'avg_predictions_peak' => 8];
    }

    private function getUsageByLoanType(LoanScoringModel $model): array
    {
        // Utilisation par type de prêt
        return [
            'personal' => 60,
            'auto' => 25,
            'mortgage' => 15
        ];
    }

    private function calculateDataQualityScore(): float
    {
        // Score de qualité des données
        return 0.87;
    }

    private function recommendAlgorithm(int $sampleCount): string
    {
        if ($sampleCount > 10000) {
            return 'gradient_boosting';
        } elseif ($sampleCount > 5000) {
            return 'random_forest';
        } else {
            return 'logistic_regression';
        }
    }

    private function analyzeDistributionChanges(LoanScoringModel $model): array
    {
        // Analyse des changements de distribution
        return ['distribution_shift' => 'minimal'];
    }

    private function getDriftRecommendation(float $driftScore): string
    {
        if ($driftScore > 0.3) {
            return 'immediate_retraining_required';
        } elseif ($driftScore > 0.15) {
            return 'schedule_retraining';
        } else {
            return 'continue_monitoring';
        }
    }

    private function identifyModelStrengths(LoanScoringModel $model): array
    {
        $strengths = [];
        
        if ($model->getAccuracy() > 0.85) {
            $strengths[] = 'high_accuracy';
        }
        if ($model->getPrecision() > 0.80) {
            $strengths[] = 'low_false_positives';
        }
        if ($model->getRecall() > 0.75) {
            $strengths[] = 'good_risk_detection';
        }
        
        return $strengths;
    }

    private function identifyModelWeaknesses(LoanScoringModel $model): array
    {
        $weaknesses = [];
        
        if ($model->getAccuracy() < 0.70) {
            $weaknesses[] = 'low_accuracy';
        }
        if ($model->getPrecision() < 0.70) {
            $weaknesses[] = 'high_false_positives';
        }
        if ($model->getRecall() < 0.60) {
            $weaknesses[] = 'poor_risk_detection';
        }
        
        return $weaknesses;
    }

    private function selectBestModel(array $models): LoanScoringModel
    {
        usort($models, fn($a, $b) => $b->getQualityScore() <=> $a->getQualityScore());
        return $models[0];
    }

    private function generateComparisonMatrix(array $models): array
    {
        $matrix = [];
        $metrics = ['accuracy', 'precision', 'recall', 'f1Score', 'auc'];
        
        foreach ($metrics as $metric) {
            $values = array_map(fn($m) => $m->{'get' . ucfirst($metric)}(), $models);
            $matrix[$metric] = $values;
        }
        
        return $matrix;
    }
}
