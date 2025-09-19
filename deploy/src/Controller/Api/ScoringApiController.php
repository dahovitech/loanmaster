<?php

namespace App\Controller\Api;

use App\Service\AI\LoanScoringService;
use App\Entity\Customer;
use App\Entity\LoanScoringModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

/**
 * API REST pour le scoring automatique par IA
 */
#[Route('/api/v1/scoring', name: 'api_scoring_')]
class ScoringApiController extends AbstractController
{
    private LoanScoringService $scoringService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;

    public function __construct(
        LoanScoringService $scoringService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ) {
        $this->scoringService = $scoringService;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    /**
     * Calcul de score de crédit en temps réel
     */
    #[Route('/calculate', name: 'calculate', methods: ['POST'])]
    #[IsGranted('ROLE_ANALYST')]
    public function calculateCreditScore(Request $request, RateLimiterFactory $apiLimiter): JsonResponse
    {
        $limiter = $apiLimiter->create($request->getClientIp());
        
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.'
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'error' => 'Invalid JSON',
                    'message' => 'Request body must contain valid JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validation des données d'entrée
            $validationResult = $this->validateScoringRequest($data);
            if (!$validationResult['valid']) {
                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'Invalid request data',
                    'errors' => $validationResult['errors']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Récupération ou création du client
            $customer = $this->getOrCreateCustomer($data);
            
            // Données du prêt
            $loanData = [
                'amount' => $data['loan_amount'],
                'term_months' => $data['loan_term_months'] ?? 12,
                'purpose' => $data['loan_purpose'] ?? 'personal'
            ];

            // Données additionnelles
            $additionalData = $data['additional_data'] ?? [];
            
            $startTime = microtime(true);

            // Calcul du score
            $scoringResult = $this->scoringService->calculateCreditScore(
                $customer,
                $loanData,
                $additionalData
            );

            $executionTime = microtime(true) - $startTime;

            // Log de l'appel API
            $this->logger->info('Credit scoring API call', [
                'customer_id' => $customer->getId(),
                'loan_amount' => $loanData['amount'],
                'score' => $scoringResult['credit_score'],
                'risk_level' => $scoringResult['risk_level'],
                'execution_time' => $executionTime,
                'user' => $this->getUser()?->getEmail(),
                'ip' => $request->getClientIp()
            ]);

            // Formatage de la réponse
            $response = [
                'success' => true,
                'data' => [
                    'credit_score' => $scoringResult['credit_score'],
                    'risk_level' => $scoringResult['risk_level'],
                    'confidence' => $scoringResult['confidence'],
                    'risk_metrics' => $scoringResult['risk_metrics'],
                    'recommendations' => $scoringResult['recommendations'],
                    'calculation_method' => $scoringResult['calculation_method'],
                    'model_version' => $scoringResult['model_version'] ?? null
                ],
                'metadata' => [
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'timestamp' => date('c'),
                    'request_id' => uniqid('score_', true)
                ]
            ];

            // Inclure les détails si demandé
            if ($data['include_details'] ?? false) {
                $response['data']['features_used'] = $scoringResult['features_used'] ?? [];
                $response['data']['scoring_factors'] = $scoringResult['recommendations']['scoring_factors'] ?? [];
            }

            return $this->json($response);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid argument',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Credit scoring API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $data ?? null
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred while calculating the credit score'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calcul de score batch (plusieurs demandes)
     */
    #[Route('/batch', name: 'batch', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function batchCreditScoring(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['requests']) || !is_array($data['requests'])) {
                return $this->json([
                    'error' => 'Invalid format',
                    'message' => 'Request must contain "requests" array'
                ], Response::HTTP_BAD_REQUEST);
            }

            $maxBatchSize = 50;
            if (count($data['requests']) > $maxBatchSize) {
                return $this->json([
                    'error' => 'Batch too large',
                    'message' => "Maximum {$maxBatchSize} requests per batch"
                ], Response::HTTP_BAD_REQUEST);
            }

            $results = [];
            $errors = [];
            $startTime = microtime(true);

            foreach ($data['requests'] as $index => $requestData) {
                try {
                    // Validation de la demande individuelle
                    $validationResult = $this->validateScoringRequest($requestData);
                    if (!$validationResult['valid']) {
                        $errors[] = [
                            'index' => $index,
                            'errors' => $validationResult['errors']
                        ];
                        continue;
                    }

                    // Traitement de la demande
                    $customer = $this->getOrCreateCustomer($requestData);
                    $loanData = [
                        'amount' => $requestData['loan_amount'],
                        'term_months' => $requestData['loan_term_months'] ?? 12,
                        'purpose' => $requestData['loan_purpose'] ?? 'personal'
                    ];

                    $scoringResult = $this->scoringService->calculateCreditScore(
                        $customer,
                        $loanData,
                        $requestData['additional_data'] ?? []
                    );

                    $results[] = [
                        'index' => $index,
                        'customer_reference' => $requestData['customer_reference'] ?? null,
                        'credit_score' => $scoringResult['credit_score'],
                        'risk_level' => $scoringResult['risk_level'],
                        'confidence' => $scoringResult['confidence'],
                        'recommendations' => $scoringResult['recommendations']
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $executionTime = microtime(true) - $startTime;

            $this->logger->info('Batch credit scoring completed', [
                'total_requests' => count($data['requests']),
                'successful' => count($results),
                'failed' => count($errors),
                'execution_time' => $executionTime,
                'user' => $this->getUser()?->getEmail()
            ]);

            return $this->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'errors' => $errors,
                    'summary' => [
                        'total_requests' => count($data['requests']),
                        'successful' => count($results),
                        'failed' => count($errors),
                        'success_rate' => count($results) / count($data['requests'])
                    ]
                ],
                'metadata' => [
                    'execution_time_ms' => round($executionTime * 1000, 2),
                    'timestamp' => date('c')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Batch scoring error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An error occurred during batch processing'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Statut et informations sur les modèles actifs
     */
    #[Route('/models/status', name: 'models_status', methods: ['GET'])]
    #[IsGranted('ROLE_ANALYST')]
    public function modelsStatus(): JsonResponse
    {
        try {
            $activeModel = $this->entityManager
                ->getRepository(LoanScoringModel::class)
                ->findOneBy(['status' => 'deployed']);

            if (!$activeModel) {
                return $this->json([
                    'success' => false,
                    'message' => 'No active model deployed'
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $allModels = $this->entityManager
                ->getRepository(LoanScoringModel::class)
                ->findBy([], ['createdAt' => 'DESC'], 10);

            return $this->json([
                'success' => true,
                'data' => [
                    'active_model' => [
                        'id' => $activeModel->getModelId(),
                        'version' => $activeModel->getVersion(),
                        'algorithm' => $activeModel->getAlgorithm(),
                        'performance' => $activeModel->getPerformanceSummary(),
                        'deployed_at' => $activeModel->getDeployedAt()?->format('c'),
                        'usage_count' => $activeModel->getUsageCount(),
                        'needs_retraining' => $activeModel->needsRetraining()
                    ],
                    'available_models' => array_map(function($model) {
                        return [
                            'id' => $model->getModelId(),
                            'version' => $model->getVersion(),
                            'algorithm' => $model->getAlgorithm(),
                            'status' => $model->getStatus(),
                            'accuracy' => $model->getAccuracy(),
                            'created_at' => $model->getCreatedAt()->format('c')
                        ];
                    }, $allModels)
                ],
                'metadata' => [
                    'timestamp' => date('c'),
                    'total_models' => $this->entityManager
                        ->getRepository(LoanScoringModel::class)
                        ->count([])
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Internal server error',
                'message' => 'Could not retrieve model status'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Explication détaillée d'un score
     */
    #[Route('/explain', name: 'explain', methods: ['POST'])]
    #[IsGranted('ROLE_ANALYST')]
    public function explainScore(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des données
            $validationResult = $this->validateScoringRequest($data);
            if (!$validationResult['valid']) {
                return $this->json([
                    'error' => 'Validation failed',
                    'errors' => $validationResult['errors']
                ], Response::HTTP_BAD_REQUEST);
            }

            $customer = $this->getOrCreateCustomer($data);
            $loanData = [
                'amount' => $data['loan_amount'],
                'term_months' => $data['loan_term_months'] ?? 12,
                'purpose' => $data['loan_purpose'] ?? 'personal'
            ];

            // Calcul avec explication détaillée
            $scoringResult = $this->scoringService->calculateCreditScore(
                $customer,
                $loanData,
                array_merge($data['additional_data'] ?? [], ['explain' => true])
            );

            // Génération d'explications en langage naturel
            $explanations = $this->generateExplanations($scoringResult);

            return $this->json([
                'success' => true,
                'data' => [
                    'credit_score' => $scoringResult['credit_score'],
                    'risk_level' => $scoringResult['risk_level'],
                    'confidence' => $scoringResult['confidence'],
                    'explanations' => $explanations,
                    'feature_contributions' => $this->analyzeFeatureContributions($scoringResult),
                    'risk_factors' => $this->identifyRiskFactors($scoringResult),
                    'improvement_suggestions' => $this->generateImprovementSuggestions($scoringResult)
                ],
                'metadata' => [
                    'timestamp' => date('c'),
                    'explainability_version' => '1.0'
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Score explanation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'Could not generate score explanation'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Simulation de changements (what-if analysis)
     */
    #[Route('/simulate', name: 'simulate', methods: ['POST'])]
    #[IsGranted('ROLE_ANALYST')]
    public function simulateChanges(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $baseData = $data['base_scenario'];
            $scenarios = $data['scenarios'] ?? [];

            if (empty($scenarios)) {
                return $this->json([
                    'error' => 'No scenarios provided',
                    'message' => 'At least one scenario must be provided'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Score de base
            $baseCustomer = $this->getOrCreateCustomer($baseData);
            $baseLoanData = [
                'amount' => $baseData['loan_amount'],
                'term_months' => $baseData['loan_term_months'] ?? 12,
                'purpose' => $baseData['loan_purpose'] ?? 'personal'
            ];

            $baseScore = $this->scoringService->calculateCreditScore(
                $baseCustomer,
                $baseLoanData,
                $baseData['additional_data'] ?? []
            );

            $simulations = [];

            // Calcul pour chaque scénario
            foreach ($scenarios as $scenarioName => $scenarioData) {
                $modifiedData = array_merge($baseData, $scenarioData);
                $scenarioCustomer = $this->getOrCreateCustomer($modifiedData);
                $scenarioLoanData = [
                    'amount' => $modifiedData['loan_amount'],
                    'term_months' => $modifiedData['loan_term_months'] ?? 12,
                    'purpose' => $modifiedData['loan_purpose'] ?? 'personal'
                ];

                $scenarioScore = $this->scoringService->calculateCreditScore(
                    $scenarioCustomer,
                    $scenarioLoanData,
                    $modifiedData['additional_data'] ?? []
                );

                $scoreDifference = $scenarioScore['credit_score'] - $baseScore['credit_score'];

                $simulations[$scenarioName] = [
                    'credit_score' => $scenarioScore['credit_score'],
                    'risk_level' => $scenarioScore['risk_level'],
                    'score_difference' => $scoreDifference,
                    'risk_level_changed' => $scenarioScore['risk_level'] !== $baseScore['risk_level'],
                    'impact_analysis' => $this->analyzeImpact($scoreDifference),
                    'recommendations' => $scenarioScore['recommendations']
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'base_scenario' => [
                        'credit_score' => $baseScore['credit_score'],
                        'risk_level' => $baseScore['risk_level']
                    ],
                    'simulations' => $simulations,
                    'summary' => [
                        'best_scenario' => $this->findBestScenario($simulations),
                        'worst_scenario' => $this->findWorstScenario($simulations),
                        'most_impactful_changes' => $this->identifyMostImpactfulChanges($simulations)
                    ]
                ],
                'metadata' => [
                    'timestamp' => date('c'),
                    'scenarios_tested' => count($scenarios)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Simulation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'Could not run simulation'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Informations sur l'API
     */
    #[Route('/info', name: 'info', methods: ['GET'])]
    public function apiInfo(): JsonResponse
    {
        $activeModel = $this->entityManager
            ->getRepository(LoanScoringModel::class)
            ->findOneBy(['status' => 'deployed']);

        return $this->json([
            'api_version' => '1.0',
            'scoring_engine' => [
                'name' => 'LoanMaster AI Scoring',
                'version' => '2.1',
                'active_model' => $activeModel ? [
                    'id' => $activeModel->getModelId(),
                    'version' => $activeModel->getVersion(),
                    'algorithm' => $activeModel->getAlgorithm()
                ] : null,
                'features' => [
                    'real_time_scoring',
                    'batch_processing',
                    'explainable_ai',
                    'what_if_analysis',
                    'model_monitoring'
                ]
            ],
            'endpoints' => [
                'POST /api/v1/scoring/calculate' => 'Calculate credit score',
                'POST /api/v1/scoring/batch' => 'Batch credit scoring',
                'POST /api/v1/scoring/explain' => 'Explain score decision',
                'POST /api/v1/scoring/simulate' => 'What-if analysis',
                'GET /api/v1/scoring/models/status' => 'Model status',
                'GET /api/v1/scoring/info' => 'API information'
            ],
            'rate_limits' => [
                'calculate' => '100 requests per minute',
                'batch' => '10 requests per minute',
                'max_batch_size' => 50
            ],
            'score_range' => [
                'minimum' => 300,
                'maximum' => 850,
                'risk_levels' => ['very_low', 'low', 'medium', 'high', 'very_high']
            ]
        ]);
    }

    // Méthodes privées utilitaires

    private function validateScoringRequest(array $data): array
    {
        $errors = [];

        // Validation des champs obligatoires
        $requiredFields = ['loan_amount', 'monthly_income', 'employment_status'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Validation des types et valeurs
        if (isset($data['loan_amount'])) {
            if (!is_numeric($data['loan_amount']) || $data['loan_amount'] <= 0) {
                $errors[] = 'loan_amount must be a positive number';
            } elseif ($data['loan_amount'] > 500000) {
                $errors[] = 'loan_amount cannot exceed 500,000';
            }
        }

        if (isset($data['monthly_income'])) {
            if (!is_numeric($data['monthly_income']) || $data['monthly_income'] <= 0) {
                $errors[] = 'monthly_income must be a positive number';
            }
        }

        if (isset($data['loan_term_months'])) {
            if (!is_int($data['loan_term_months']) || $data['loan_term_months'] < 1 || $data['loan_term_months'] > 360) {
                $errors[] = 'loan_term_months must be between 1 and 360';
            }
        }

        if (isset($data['age'])) {
            if (!is_int($data['age']) || $data['age'] < 18 || $data['age'] > 100) {
                $errors[] = 'age must be between 18 and 100';
            }
        }

        $validEmploymentStatuses = ['CDI', 'CDD', 'freelance', 'fonctionnaire', 'retired', 'unemployed'];
        if (isset($data['employment_status']) && !in_array($data['employment_status'], $validEmploymentStatuses)) {
            $errors[] = 'employment_status must be one of: ' . implode(', ', $validEmploymentStatuses);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private function getOrCreateCustomer(array $data): Customer
    {
        // Si un ID client est fourni, essayer de le récupérer
        if (isset($data['customer_id'])) {
            $customer = $this->entityManager
                ->getRepository(Customer::class)
                ->find($data['customer_id']);
                
            if ($customer) {
                return $customer;
            }
        }

        // Créer un customer temporaire avec les données fournies
        $customer = new Customer();
        $customer->setEmail($data['email'] ?? 'temp_' . uniqid() . '@temp.local');
        $customer->setFirstName($data['first_name'] ?? 'Temp');
        $customer->setLastName($data['last_name'] ?? 'Customer');
        $customer->setMonthlyIncome($data['monthly_income']);
        $customer->setEmploymentStatus($data['employment_status']);
        $customer->setMonthlyExpenses($data['monthly_expenses'] ?? 0);
        $customer->setSavingsAmount($data['savings_amount'] ?? 0);
        $customer->setExistingDebtAmount($data['existing_debt_amount'] ?? 0);
        
        if (isset($data['age'])) {
            $birthDate = (new \DateTime())->modify("-{$data['age']} years");
            $customer->setBirthDate($birthDate);
        }

        return $customer;
    }

    private function generateExplanations(array $scoringResult): array
    {
        $score = $scoringResult['credit_score'];
        $riskLevel = $scoringResult['risk_level'];
        
        $explanations = [];
        
        // Explication générale du score
        $explanations['overall'] = match ($riskLevel) {
            'very_low' => "Excellent score de crédit ({$score}). Profil très fiable avec risque minimal.",
            'low' => "Bon score de crédit ({$score}). Profil fiable avec risque faible.",
            'medium' => "Score de crédit moyen ({$score}). Profil acceptable avec risque modéré.",
            'high' => "Score de crédit bas ({$score}). Profil avec risque élevé nécessitant attention.",
            'very_high' => "Score de crédit très bas ({$score}). Profil à haut risque."
        };

        // Explication des facteurs positifs
        if (isset($scoringResult['recommendations']['scoring_factors'])) {
            $positiveFactors = array_filter(
                $scoringResult['recommendations']['scoring_factors'],
                fn($factor) => $factor['impact'] > 0
            );
            
            $explanations['positive_factors'] = array_map(
                fn($factor) => $this->translateFactor($factor['factor'], $factor['impact']),
                $positiveFactors
            );
        }

        // Explication des facteurs négatifs
        if (isset($scoringResult['recommendations']['scoring_factors'])) {
            $negativeFactors = array_filter(
                $scoringResult['recommendations']['scoring_factors'],
                fn($factor) => $factor['impact'] < 0
            );
            
            $explanations['negative_factors'] = array_map(
                fn($factor) => $this->translateFactor($factor['factor'], $factor['impact']),
                $negativeFactors
            );
        }

        return $explanations;
    }

    private function translateFactor(string $factor, float $impact): string
    {
        $translations = [
            'optimal_age' => 'Âge optimal pour l\'emprunt',
            'stable_employment' => 'Emploi stable et durable',
            'low_debt_ratio' => 'Taux d\'endettement faible',
            'high_income' => 'Revenus élevés',
            'excellent_repayment_history' => 'Historique de remboursement excellent',
            'no_defaults' => 'Aucun défaut de paiement',
            'punctual_payments' => 'Paiements toujours ponctuels',
            'high_savings' => 'Épargne importante',
            'high_debt_ratio' => 'Taux d\'endettement trop élevé',
            'low_income' => 'Revenus insuffisants',
            'unstable_employment' => 'Emploi instable',
            'poor_repayment_history' => 'Historique de remboursement défaillant'
        ];

        $description = $translations[$factor] ?? $factor;
        $impactDescription = $impact > 0 ? 'améliore' : 'dégrade';
        
        return "{$description} {$impactDescription} le score de " . abs(round($impact)) . " points";
    }

    private function analyzeFeatureContributions(array $scoringResult): array
    {
        // Analyse des contributions des features au score
        return [
            'most_influential' => ['monthly_income', 'debt_to_income_ratio', 'employment_status'],
            'least_influential' => ['age', 'marital_status']
        ];
    }

    private function identifyRiskFactors(array $scoringResult): array
    {
        $riskFactors = [];
        
        if (isset($scoringResult['risk_metrics'])) {
            $metrics = $scoringResult['risk_metrics'];
            
            if (($metrics['default_probability'] ?? 0) > 0.1) {
                $riskFactors[] = [
                    'factor' => 'high_default_probability',
                    'description' => 'Probabilité de défaut élevée',
                    'value' => $metrics['default_probability'],
                    'severity' => 'high'
                ];
            }
            
            if (($metrics['debt_to_income_ratio'] ?? 0) > 0.4) {
                $riskFactors[] = [
                    'factor' => 'high_debt_ratio',
                    'description' => 'Taux d\'endettement élevé',
                    'value' => $metrics['debt_to_income_ratio'],
                    'severity' => 'medium'
                ];
            }
        }
        
        return $riskFactors;
    }

    private function generateImprovementSuggestions(array $scoringResult): array
    {
        $suggestions = [];
        
        if (isset($scoringResult['recommendations']['risk_mitigation'])) {
            foreach ($scoringResult['recommendations']['risk_mitigation'] as $mitigation) {
                $suggestions[] = match ($mitigation) {
                    'debt_consolidation_offer' => 'Consolidation de dettes pour réduire le taux d\'endettement',
                    'savings_building_program' => 'Programme d\'épargne pour améliorer la stabilité financière',
                    'income_verification' => 'Vérification et documentation des revenus',
                    default => $mitigation
                };
            }
        }
        
        return $suggestions;
    }

    private function analyzeImpact(float $scoreDifference): string
    {
        return match (true) {
            abs($scoreDifference) < 10 => 'minimal',
            abs($scoreDifference) < 30 => 'moderate',
            abs($scoreDifference) < 50 => 'significant',
            default => 'major'
        };
    }

    private function findBestScenario(array $simulations): ?string
    {
        $bestScore = 0;
        $bestScenario = null;
        
        foreach ($simulations as $name => $simulation) {
            if ($simulation['credit_score'] > $bestScore) {
                $bestScore = $simulation['credit_score'];
                $bestScenario = $name;
            }
        }
        
        return $bestScenario;
    }

    private function findWorstScenario(array $simulations): ?string
    {
        $worstScore = 999;
        $worstScenario = null;
        
        foreach ($simulations as $name => $simulation) {
            if ($simulation['credit_score'] < $worstScore) {
                $worstScore = $simulation['credit_score'];
                $worstScenario = $name;
            }
        }
        
        return $worstScenario;
    }

    private function identifyMostImpactfulChanges(array $simulations): array
    {
        $impacts = [];
        
        foreach ($simulations as $name => $simulation) {
            $impacts[$name] = abs($simulation['score_difference']);
        }
        
        arsort($impacts);
        
        return array_slice(array_keys($impacts), 0, 3);
    }
}
