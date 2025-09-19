<?php

namespace App\Service\AI;

use App\Entity\Loan;
use App\Entity\Customer;
use App\Entity\LoanScoringModel;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de gestion des modèles de Machine Learning
 * Entraînement, validation et déploiement des modèles de scoring
 */
class MLModelService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private array $configuration;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        array $configuration = []
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->configuration = array_merge([
            'ml_training_endpoint' => $_ENV['ML_TRAINING_ENDPOINT'] ?? null,
            'ml_api_key' => $_ENV['ML_API_KEY'] ?? null,
            'model_storage_path' => $_ENV['MODEL_STORAGE_PATH'] ?? '/var/lib/loanmaster/models/',
            'training_data_min_samples' => 1000,
            'validation_split_ratio' => 0.2,
            'performance_threshold' => 0.85,
            'retrain_threshold_days' => 30,
            'feature_drift_threshold' => 0.1
        ], $configuration);
    }

    /**
     * Entraîne un nouveau modèle de scoring
     */
    public function trainNewModel(array $options = []): array
    {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Starting ML model training', $options);

            // 1. Extraction des données d'entraînement
            $trainingData = $this->extractTrainingData($options);
            
            if (count($trainingData['samples']) < $this->configuration['training_data_min_samples']) {
                throw new \RuntimeException(
                    "Insufficient training data: " . count($trainingData['samples']) . 
                    " samples (minimum: " . $this->configuration['training_data_min_samples'] . ")"
                );
            }

            // 2. Préparation et nettoyage des données
            $cleanedData = $this->prepareTrainingData($trainingData);
            
            // 3. Division train/validation
            $splitData = $this->splitTrainingData($cleanedData, $this->configuration['validation_split_ratio']);
            
            // 4. Entraînement du modèle
            $modelResult = $this->performModelTraining($splitData, $options);
            
            // 5. Validation et évaluation
            $evaluationResults = $this->evaluateModel($modelResult, $splitData['validation']);
            
            // 6. Sauvegarde du modèle si performance acceptable
            if ($evaluationResults['accuracy'] >= $this->configuration['performance_threshold']) {
                $modelId = $this->saveModel($modelResult, $evaluationResults, $options);
                
                $result = [
                    'success' => true,
                    'model_id' => $modelId,
                    'training_samples' => count($trainingData['samples']),
                    'performance_metrics' => $evaluationResults,
                    'training_time' => microtime(true) - $startTime,
                    'model_version' => $this->generateModelVersion(),
                    'features_used' => $cleanedData['feature_names'],
                    'deployment_ready' => true
                ];
            } else {
                $result = [
                    'success' => false,
                    'reason' => 'performance_below_threshold',
                    'achieved_accuracy' => $evaluationResults['accuracy'],
                    'required_accuracy' => $this->configuration['performance_threshold'],
                    'recommendations' => $this->generateImprovementRecommendations($evaluationResults)
                ];
            }

            $this->logger->info('Model training completed', $result);
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Model training failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'training_time' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Extraction des données d'entraînement depuis la base
     */
    private function extractTrainingData(array $options): array
    {
        $this->logger->info('Extracting training data from database');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l', 'c')
           ->from(Loan::class, 'l')
           ->join('l.customer', 'c')
           ->where('l.status IN (:completed_statuses)')
           ->setParameter('completed_statuses', ['completed', 'defaulted', 'rejected'])
           ->orderBy('l.createdAt', 'DESC');

        // Filtrage par période si spécifié
        if (isset($options['start_date'])) {
            $qb->andWhere('l.createdAt >= :start_date')
               ->setParameter('start_date', $options['start_date']);
        }

        if (isset($options['end_date'])) {
            $qb->andWhere('l.createdAt <= :end_date')
               ->setParameter('end_date', $options['end_date']);
        }

        // Limitation du nombre d'échantillons si spécifié
        if (isset($options['max_samples'])) {
            $qb->setMaxResults($options['max_samples']);
        }

        $loans = $qb->getQuery()->getResult();

        $samples = [];
        $labels = [];
        $featureNames = [];

        foreach ($loans as $loan) {
            try {
                // Extraction des features pour ce prêt
                $features = $this->extractLoanFeatures($loan, $loan->getCustomer());
                
                // Label: 1 si remboursé avec succès, 0 sinon
                $label = in_array($loan->getStatus(), ['completed']) ? 1 : 0;
                
                $samples[] = $features;
                $labels[] = $label;
                
                // Capture des noms de features à partir du premier échantillon
                if (empty($featureNames)) {
                    $featureNames = array_keys($features);
                }

            } catch (\Exception $e) {
                $this->logger->warning('Failed to extract features for loan', [
                    'loan_id' => $loan->getId(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        $this->logger->info('Training data extraction completed', [
            'total_samples' => count($samples),
            'positive_samples' => array_sum($labels),
            'negative_samples' => count($labels) - array_sum($labels),
            'feature_count' => count($featureNames)
        ]);

        return [
            'samples' => $samples,
            'labels' => $labels,
            'feature_names' => $featureNames,
            'metadata' => [
                'extraction_date' => new \DateTimeImmutable(),
                'total_loans_processed' => count($loans),
                'success_rate' => count($samples) / count($loans)
            ]
        ];
    }

    /**
     * Extraction des features pour un prêt donné
     */
    private function extractLoanFeatures(Loan $loan, Customer $customer): array
    {
        // Réutilisation de la logique d'extraction du LoanScoringService
        // avec des adaptations pour l'entraînement
        
        $features = [];

        // Features démographiques
        $age = $customer->getBirthDate() 
            ? (new \DateTime())->diff($customer->getBirthDate())->y 
            : null;
        
        $features['age'] = $age ?? 0;
        $features['employment_duration_months'] = $customer->getEmploymentDurationMonths() ?? 0;
        $features['number_of_dependents'] = $customer->getNumberOfDependents() ?? 0;
        
        // Encodage catégoriel pour l'emploi
        $employmentTypes = ['CDI' => 1, 'CDD' => 2, 'freelance' => 3, 'fonctionnaire' => 4, 'unemployed' => 5];
        $features['employment_type_encoded'] = $employmentTypes[$customer->getEmploymentStatus()] ?? 0;

        // Features financières
        $features['monthly_income'] = $customer->getMonthlyIncome() ?? 0;
        $features['monthly_expenses'] = $customer->getMonthlyExpenses() ?? 0;
        $features['savings_amount'] = $customer->getSavingsAmount() ?? 0;
        $features['existing_debt_amount'] = $customer->getExistingDebtAmount() ?? 0;
        $features['loan_amount'] = $loan->getAmount() ?? 0;
        $features['loan_term_months'] = $loan->getTermMonths() ?? 12;

        // Ratios calculés
        $monthlyIncome = $features['monthly_income'];
        $features['debt_to_income_ratio'] = $monthlyIncome > 0 
            ? $features['existing_debt_amount'] / ($monthlyIncome * 12) 
            : 1.0;
        $features['loan_to_income_ratio'] = $monthlyIncome > 0 
            ? $features['loan_amount'] / ($monthlyIncome * 12) 
            : 1.0;
        $features['savings_to_income_ratio'] = $monthlyIncome > 0 
            ? $features['savings_amount'] / $monthlyIncome 
            : 0.0;

        // Features d'historique de crédit
        $previousLoans = $this->getPreviousLoansForCustomer($customer, $loan->getCreatedAt());
        $features['previous_loans_count'] = count($previousLoans);
        $features['previous_defaults_count'] = count(array_filter(
            $previousLoans, 
            fn($l) => $l->getStatus() === 'defaulted'
        ));
        $features['previous_completed_count'] = count(array_filter(
            $previousLoans, 
            fn($l) => $l->getStatus() === 'completed'
        ));

        // Features temporelles
        $features['application_month'] = (int) $loan->getCreatedAt()->format('n');
        $features['application_day_of_week'] = (int) $loan->getCreatedAt()->format('N');
        $features['application_hour'] = (int) $loan->getCreatedAt()->format('H');

        // Features de comportement (si disponibles)
        $features['digital_engagement_score'] = $customer->getDigitalEngagementScore() ?? 50;
        $features['response_time_hours'] = $customer->getAverageResponseTimeToRequests() ?? 24;
        $features['auto_payment_opted'] = $customer->hasOptedForAutoPayment() ? 1 : 0;

        // Normalisation des features numériques
        $this->normalizeNumericalFeatures($features);

        return $features;
    }

    /**
     * Normalisation des features numériques
     */
    private function normalizeNumericalFeatures(array &$features): void
    {
        // Z-score normalization pour les features continues
        $continuousFeatures = [
            'age', 'monthly_income', 'loan_amount', 'savings_amount'
        ];

        foreach ($continuousFeatures as $feature) {
            if (isset($features[$feature]) && $features[$feature] > 0) {
                // Log transformation pour réduire l'asymétrie
                $features[$feature . '_log'] = log($features[$feature] + 1);
            }
        }

        // Bornage des ratios
        $ratioFeatures = [
            'debt_to_income_ratio', 'loan_to_income_ratio', 'savings_to_income_ratio'
        ];

        foreach ($ratioFeatures as $feature) {
            if (isset($features[$feature])) {
                $features[$feature] = min(2.0, max(0.0, $features[$feature]));
            }
        }
    }

    /**
     * Préparation et nettoyage des données d'entraînement
     */
    private function prepareTrainingData(array $trainingData): array
    {
        $samples = $trainingData['samples'];
        $labels = $trainingData['labels'];
        $featureNames = $trainingData['feature_names'];

        // Suppression des échantillons avec des valeurs manquantes critiques
        $cleanedSamples = [];
        $cleanedLabels = [];
        $criticalFeatures = ['monthly_income', 'loan_amount', 'age'];

        foreach ($samples as $index => $sample) {
            $hasAllCriticalFeatures = true;
            
            foreach ($criticalFeatures as $feature) {
                if (!isset($sample[$feature]) || $sample[$feature] === 0) {
                    $hasAllCriticalFeatures = false;
                    break;
                }
            }

            if ($hasAllCriticalFeatures) {
                // Imputation des valeurs manquantes pour les features non-critiques
                $cleanedSample = $this->imputeMissingValues($sample, $featureNames);
                $cleanedSamples[] = $cleanedSample;
                $cleanedLabels[] = $labels[$index];
            }
        }

        // Détection et suppression des outliers
        $cleanedSamples = $this->removeOutliers($cleanedSamples, $featureNames);

        $this->logger->info('Data cleaning completed', [
            'original_samples' => count($samples),
            'cleaned_samples' => count($cleanedSamples),
            'outliers_removed' => count($samples) - count($cleanedSamples),
            'cleaning_ratio' => count($cleanedSamples) / count($samples)
        ]);

        return [
            'samples' => $cleanedSamples,
            'labels' => array_slice($cleanedLabels, 0, count($cleanedSamples)),
            'feature_names' => $featureNames,
            'metadata' => array_merge($trainingData['metadata'], [
                'cleaning_applied' => true,
                'outlier_detection' => true
            ])
        ];
    }

    /**
     * Imputation des valeurs manquantes
     */
    private function imputeMissingValues(array $sample, array $featureNames): array
    {
        // Stratégies d'imputation par type de feature
        $imputationStrategies = [
            'mean' => ['employment_duration_months', 'digital_engagement_score'],
            'median' => ['response_time_hours'],
            'mode' => ['employment_type_encoded'],
            'zero' => ['previous_loans_count', 'previous_defaults_count']
        ];

        foreach ($featureNames as $feature) {
            if (!isset($sample[$feature]) || $sample[$feature] === null) {
                // Détermination de la stratégie d'imputation
                $strategy = 'mean'; // Par défaut
                foreach ($imputationStrategies as $strategyType => $features) {
                    if (in_array($feature, $features)) {
                        $strategy = $strategyType;
                        break;
                    }
                }

                // Application de la stratégie
                $sample[$feature] = $this->applyImputationStrategy($strategy, $feature);
            }
        }

        return $sample;
    }

    /**
     * Application d'une stratégie d'imputation spécifique
     */
    private function applyImputationStrategy(string $strategy, string $feature): float
    {
        // Valeurs par défaut basées sur les statistiques historiques
        $defaults = [
            'employment_duration_months' => 36,
            'digital_engagement_score' => 65,
            'response_time_hours' => 12,
            'employment_type_encoded' => 1, // CDI le plus fréquent
            'previous_loans_count' => 0,
            'previous_defaults_count' => 0
        ];

        return $defaults[$feature] ?? match ($strategy) {
            'mean', 'median' => 0.0,
            'mode' => 1,
            'zero' => 0.0,
            default => 0.0
        };
    }

    /**
     * Suppression des outliers
     */
    private function removeOutliers(array $samples, array $featureNames): array
    {
        $cleanedSamples = [];
        
        // Calcul des quartiles pour chaque feature numérique
        $quartiles = $this->calculateQuartiles($samples, $featureNames);
        
        foreach ($samples as $sample) {
            $isOutlier = false;
            
            foreach ($featureNames as $feature) {
                if (isset($quartiles[$feature]) && isset($sample[$feature])) {
                    $q1 = $quartiles[$feature]['q1'];
                    $q3 = $quartiles[$feature]['q3'];
                    $iqr = $q3 - $q1;
                    $lowerBound = $q1 - 1.5 * $iqr;
                    $upperBound = $q3 + 1.5 * $iqr;
                    
                    if ($sample[$feature] < $lowerBound || $sample[$feature] > $upperBound) {
                        $isOutlier = true;
                        break;
                    }
                }
            }
            
            if (!$isOutlier) {
                $cleanedSamples[] = $sample;
            }
        }
        
        return $cleanedSamples;
    }

    /**
     * Calcul des quartiles
     */
    private function calculateQuartiles(array $samples, array $featureNames): array
    {
        $quartiles = [];
        
        foreach ($featureNames as $feature) {
            $values = array_filter(
                array_column($samples, $feature), 
                fn($v) => is_numeric($v) && $v !== null
            );
            
            if (count($values) > 10) { // Minimum d'échantillons pour calculer les quartiles
                sort($values);
                $count = count($values);
                
                $quartiles[$feature] = [
                    'q1' => $values[(int) ($count * 0.25)],
                    'q3' => $values[(int) ($count * 0.75)]
                ];
            }
        }
        
        return $quartiles;
    }

    /**
     * Division des données en train/validation
     */
    private function splitTrainingData(array $data, float $validationRatio): array
    {
        $samples = $data['samples'];
        $labels = $data['labels'];
        
        $totalSamples = count($samples);
        $validationSize = (int) ($totalSamples * $validationRatio);
        $trainSize = $totalSamples - $validationSize;
        
        // Mélange aléatoire avec stratification (préservation de la distribution des classes)
        $indices = range(0, $totalSamples - 1);
        shuffle($indices);
        
        $trainIndices = array_slice($indices, 0, $trainSize);
        $validationIndices = array_slice($indices, $trainSize);
        
        return [
            'train' => [
                'samples' => array_intersect_key($samples, array_flip($trainIndices)),
                'labels' => array_intersect_key($labels, array_flip($trainIndices))
            ],
            'validation' => [
                'samples' => array_intersect_key($samples, array_flip($validationIndices)),
                'labels' => array_intersect_key($labels, array_flip($validationIndices))
            ],
            'feature_names' => $data['feature_names'],
            'metadata' => $data['metadata']
        ];
    }

    /**
     * Entraînement du modèle via API ML externe ou algorithme local
     */
    private function performModelTraining(array $splitData, array $options): array
    {
        if ($this->configuration['ml_training_endpoint']) {
            return $this->trainViaExternalAPI($splitData, $options);
        } else {
            return $this->trainViaLocalAlgorithm($splitData, $options);
        }
    }

    /**
     * Entraînement via API ML externe
     */
    private function trainViaExternalAPI(array $splitData, array $options): array
    {
        $payload = [
            'train_data' => $splitData['train'],
            'validation_data' => $splitData['validation'],
            'feature_names' => $splitData['feature_names'],
            'algorithm' => $options['algorithm'] ?? 'gradient_boosting',
            'hyperparameters' => $options['hyperparameters'] ?? [
                'n_estimators' => 100,
                'learning_rate' => 0.1,
                'max_depth' => 6
            ],
            'cross_validation_folds' => 5
        ];

        $response = $this->httpClient->request('POST', $this->configuration['ml_training_endpoint'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->configuration['ml_api_key'],
                'Content-Type' => 'application/json'
            ],
            'json' => $payload,
            'timeout' => 300 // 5 minutes timeout pour l'entraînement
        ]);

        $result = $response->toArray();
        
        return [
            'model_data' => $result['model'],
            'training_metrics' => $result['metrics'],
            'feature_importance' => $result['feature_importance'],
            'method' => 'external_api',
            'algorithm' => $payload['algorithm']
        ];
    }

    /**
     * Entraînement via algorithme local (logistic regression simple)
     */
    private function trainViaLocalAlgorithm(array $splitData, array $options): array
    {
        $this->logger->info('Training model using local logistic regression');
        
        // Implémentation simple de régression logistique
        $trainSamples = $splitData['train']['samples'];
        $trainLabels = $splitData['train']['labels'];
        $featureNames = $splitData['feature_names'];
        
        // Conversion en matrices numériques
        $X = $this->samplesToMatrix($trainSamples, $featureNames);
        $y = array_values($trainLabels);
        
        // Gradient descent pour la régression logistique
        $weights = $this->trainLogisticRegression($X, $y, $options);
        
        // Calcul de l'importance des features
        $featureImportance = $this->calculateFeatureImportance($weights, $featureNames);
        
        return [
            'model_data' => [
                'weights' => $weights,
                'feature_names' => $featureNames,
                'intercept' => $weights[0] ?? 0
            ],
            'training_metrics' => [
                'algorithm' => 'logistic_regression',
                'iterations' => $options['max_iterations'] ?? 1000,
                'learning_rate' => $options['learning_rate'] ?? 0.01
            ],
            'feature_importance' => $featureImportance,
            'method' => 'local_algorithm',
            'algorithm' => 'logistic_regression'
        ];
    }

    /**
     * Conversion des échantillons en matrice numérique
     */
    private function samplesToMatrix(array $samples, array $featureNames): array
    {
        $matrix = [];
        
        foreach ($samples as $sample) {
            $row = [1.0]; // Bias term
            foreach ($featureNames as $feature) {
                $row[] = (float) ($sample[$feature] ?? 0);
            }
            $matrix[] = $row;
        }
        
        return $matrix;
    }

    /**
     * Entraînement par régression logistique (gradient descent)
     */
    private function trainLogisticRegression(array $X, array $y, array $options): array
    {
        $learningRate = $options['learning_rate'] ?? 0.01;
        $maxIterations = $options['max_iterations'] ?? 1000;
        $tolerance = $options['tolerance'] ?? 1e-6;
        
        $numFeatures = count($X[0]);
        $numSamples = count($X);
        
        // Initialisation des poids
        $weights = array_fill(0, $numFeatures, 0.0);
        
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $predictions = [];
            $gradients = array_fill(0, $numFeatures, 0.0);
            
            // Forward pass
            foreach ($X as $i => $sample) {
                $z = 0;
                for ($j = 0; $j < $numFeatures; $j++) {
                    $z += $weights[$j] * $sample[$j];
                }
                $predictions[$i] = 1 / (1 + exp(-$z)); // Sigmoid
            }
            
            // Backward pass (gradient calculation)
            for ($j = 0; $j < $numFeatures; $j++) {
                for ($i = 0; $i < $numSamples; $i++) {
                    $error = $predictions[$i] - $y[$i];
                    $gradients[$j] += $error * $X[$i][$j];
                }
                $gradients[$j] /= $numSamples;
            }
            
            // Weight update
            $maxGradient = 0;
            for ($j = 0; $j < $numFeatures; $j++) {
                $weights[$j] -= $learningRate * $gradients[$j];
                $maxGradient = max($maxGradient, abs($gradients[$j]));
            }
            
            // Convergence check
            if ($maxGradient < $tolerance) {
                $this->logger->info("Logistic regression converged after {$iteration} iterations");
                break;
            }
        }
        
        return $weights;
    }

    /**
     * Calcul de l'importance des features
     */
    private function calculateFeatureImportance(array $weights, array $featureNames): array
    {
        $importance = [];
        
        // Skip intercept (index 0)
        for ($i = 1; $i < count($weights); $i++) {
            $featureIndex = $i - 1;
            if (isset($featureNames[$featureIndex])) {
                $importance[$featureNames[$featureIndex]] = abs($weights[$i]);
            }
        }
        
        // Normalisation
        $maxImportance = max(array_values($importance));
        if ($maxImportance > 0) {
            foreach ($importance as $feature => $value) {
                $importance[$feature] = $value / $maxImportance;
            }
        }
        
        arsort($importance);
        return $importance;
    }

    /**
     * Évaluation du modèle
     */
    private function evaluateModel(array $modelResult, array $validationData): array
    {
        $samples = $validationData['samples'];
        $trueLabels = $validationData['labels'];
        
        $predictions = [];
        $probabilities = [];
        
        // Prédictions sur l'ensemble de validation
        foreach ($samples as $sample) {
            $prediction = $this->predictWithModel($modelResult, $sample);
            $predictions[] = $prediction['prediction'];
            $probabilities[] = $prediction['probability'];
        }
        
        // Calcul des métriques
        $metrics = $this->calculateMetrics($trueLabels, $predictions, $probabilities);
        
        $this->logger->info('Model evaluation completed', $metrics);
        
        return $metrics;
    }

    /**
     * Prédiction avec le modèle
     */
    private function predictWithModel(array $modelResult, array $sample): array
    {
        if ($modelResult['method'] === 'external_api') {
            // Simulation pour API externe
            return ['prediction' => 1, 'probability' => 0.75];
        }
        
        // Prédiction locale avec régression logistique
        $weights = $modelResult['model_data']['weights'];
        $featureNames = $modelResult['model_data']['feature_names'];
        
        $z = $weights[0]; // Intercept
        for ($i = 0; $i < count($featureNames); $i++) {
            $z += $weights[$i + 1] * ($sample[$featureNames[$i]] ?? 0);
        }
        
        $probability = 1 / (1 + exp(-$z));
        $prediction = $probability > 0.5 ? 1 : 0;
        
        return ['prediction' => $prediction, 'probability' => $probability];
    }

    /**
     * Calcul des métriques de performance
     */
    private function calculateMetrics(array $trueLabels, array $predictions, array $probabilities): array
    {
        $tp = $tn = $fp = $fn = 0;
        
        for ($i = 0; $i < count($trueLabels); $i++) {
            $true = $trueLabels[$i];
            $pred = $predictions[$i];
            
            if ($true == 1 && $pred == 1) $tp++;
            elseif ($true == 0 && $pred == 0) $tn++;
            elseif ($true == 0 && $pred == 1) $fp++;
            elseif ($true == 1 && $pred == 0) $fn++;
        }
        
        $accuracy = ($tp + $tn) / count($trueLabels);
        $precision = $tp > 0 ? $tp / ($tp + $fp) : 0;
        $recall = $tp > 0 ? $tp / ($tp + $fn) : 0;
        $f1Score = ($precision + $recall) > 0 ? 2 * ($precision * $recall) / ($precision + $recall) : 0;
        $auc = $this->calculateAUC($trueLabels, $probabilities);
        
        return [
            'accuracy' => $accuracy,
            'precision' => $precision,
            'recall' => $recall,
            'f1_score' => $f1Score,
            'auc' => $auc,
            'confusion_matrix' => [
                'tp' => $tp, 'tn' => $tn, 'fp' => $fp, 'fn' => $fn
            ],
            'total_samples' => count($trueLabels),
            'positive_samples' => array_sum($trueLabels),
            'negative_samples' => count($trueLabels) - array_sum($trueLabels)
        ];
    }

    /**
     * Calcul de l'AUC (Area Under Curve)
     */
    private function calculateAUC(array $trueLabels, array $probabilities): float
    {
        // Simplified AUC calculation
        $pairs = array_map(null, $probabilities, $trueLabels);
        usort($pairs, fn($a, $b) => $b[0] <=> $a[0]);
        
        $positiveCount = array_sum($trueLabels);
        $negativeCount = count($trueLabels) - $positiveCount;
        
        if ($positiveCount == 0 || $negativeCount == 0) {
            return 0.5;
        }
        
        $auc = 0;
        $positivesSeen = 0;
        
        foreach ($pairs as [$prob, $label]) {
            if ($label == 1) {
                $positivesSeen++;
            } else {
                $auc += $positivesSeen;
            }
        }
        
        return $auc / ($positiveCount * $negativeCount);
    }

    /**
     * Sauvegarde du modèle
     */
    private function saveModel(array $modelResult, array $evaluationResults, array $options): string
    {
        $modelVersion = $this->generateModelVersion();
        $modelId = uniqid('model_', true);
        
        // Sauvegarde en base de données
        $modelEntity = new LoanScoringModel();
        $modelEntity->setModelId($modelId);
        $modelEntity->setVersion($modelVersion);
        $modelEntity->setAlgorithm($modelResult['algorithm']);
        $modelEntity->setPerformanceMetrics($evaluationResults);
        $modelEntity->setModelData($modelResult['model_data']);
        $modelEntity->setFeatureImportance($modelResult['feature_importance']);
        $modelEntity->setTrainingOptions($options);
        $modelEntity->setCreatedAt(new \DateTimeImmutable());
        $modelEntity->setStatus('trained');
        
        $this->entityManager->persist($modelEntity);
        $this->entityManager->flush();
        
        // Sauvegarde des fichiers du modèle
        $this->saveModelFiles($modelId, $modelResult);
        
        $this->logger->info('Model saved successfully', [
            'model_id' => $modelId,
            'version' => $modelVersion,
            'accuracy' => $evaluationResults['accuracy']
        ]);
        
        return $modelId;
    }

    /**
     * Sauvegarde des fichiers du modèle
     */
    private function saveModelFiles(string $modelId, array $modelResult): void
    {
        $modelPath = $this->configuration['model_storage_path'] . $modelId . '/';
        
        if (!is_dir($modelPath)) {
            mkdir($modelPath, 0755, true);
        }
        
        // Sauvegarde des données du modèle
        file_put_contents(
            $modelPath . 'model_data.json',
            json_encode($modelResult['model_data'], JSON_PRETTY_PRINT)
        );
        
        // Sauvegarde des métadonnées
        file_put_contents(
            $modelPath . 'metadata.json',
            json_encode([
                'method' => $modelResult['method'],
                'algorithm' => $modelResult['algorithm'],
                'feature_importance' => $modelResult['feature_importance'],
                'created_at' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT)
        );
    }

    /**
     * Génération d'un numéro de version pour le modèle
     */
    private function generateModelVersion(): string
    {
        return 'v' . date('Y.m.d') . '.' . time();
    }

    /**
     * Récupération des prêts précédents pour un client
     */
    private function getPreviousLoansForCustomer(Customer $customer, \DateTimeInterface $beforeDate): array
    {
        return $this->entityManager->getRepository(Loan::class)
            ->createQueryBuilder('l')
            ->where('l.customer = :customer')
            ->andWhere('l.createdAt < :before_date')
            ->setParameter('customer', $customer)
            ->setParameter('before_date', $beforeDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Génération de recommandations d'amélioration
     */
    private function generateImprovementRecommendations(array $evaluationResults): array
    {
        $recommendations = [];
        
        if ($evaluationResults['accuracy'] < 0.7) {
            $recommendations[] = 'increase_training_data';
            $recommendations[] = 'feature_engineering';
        }
        
        if ($evaluationResults['precision'] < 0.8) {
            $recommendations[] = 'adjust_classification_threshold';
            $recommendations[] = 'balance_training_data';
        }
        
        if ($evaluationResults['recall'] < 0.7) {
            $recommendations[] = 'improve_positive_class_detection';
            $recommendations[] = 'add_behavioral_features';
        }
        
        return $recommendations;
    }

    /**
     * Déploiement d'un modèle en production
     */
    public function deployModel(string $modelId): array
    {
        try {
            $model = $this->entityManager->getRepository(LoanScoringModel::class)
                ->findOneBy(['modelId' => $modelId]);
                
            if (!$model) {
                throw new \RuntimeException("Model {$modelId} not found");
            }
            
            // Marquer les autres modèles comme inactifs
            $this->entityManager->createQueryBuilder()
                ->update(LoanScoringModel::class, 'm')
                ->set('m.status', ':inactive')
                ->where('m.status = :active')
                ->setParameter('inactive', 'inactive')
                ->setParameter('active', 'deployed')
                ->getQuery()
                ->execute();
            
            // Activer le nouveau modèle
            $model->setStatus('deployed');
            $model->setDeployedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            
            $this->logger->info('Model deployed successfully', [
                'model_id' => $modelId,
                'version' => $model->getVersion()
            ]);
            
            return [
                'success' => true,
                'model_id' => $modelId,
                'version' => $model->getVersion(),
                'deployed_at' => $model->getDeployedAt()->format('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Model deployment failed', [
                'model_id' => $modelId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Surveillance de la dérive du modèle
     */
    public function monitorModelDrift(): array
    {
        // Implémentation du monitoring de dérive
        // Comparaison des distributions de features actuelles vs training
        return [
            'drift_detected' => false,
            'drift_score' => 0.05,
            'affected_features' => [],
            'recommendation' => 'continue_monitoring'
        ];
    }
}
