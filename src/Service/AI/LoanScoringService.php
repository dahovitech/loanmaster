<?php

namespace App\Service\AI;

use App\Entity\Loan;
use App\Entity\Customer;
use App\Repository\LoanRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de scoring automatique par Intelligence Artificielle
 * Analyse prédictive des risques de crédit
 */
class LoanScoringService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private array $configuration;
    private array $modelWeights;
    private array $industryBenchmarks;

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
            'ml_api_endpoint' => $_ENV['ML_API_ENDPOINT'] ?? null,
            'ml_api_key' => $_ENV['ML_API_KEY'] ?? null,
            'scoring_model_version' => $_ENV['SCORING_MODEL_VERSION'] ?? 'v2.1',
            'min_data_points' => 10,
            'confidence_threshold' => 0.75,
            'enable_ml_api' => $_ENV['ENABLE_ML_API'] ?? false,
            'fallback_to_rules' => true
        ], $configuration);

        $this->initializeModelWeights();
        $this->loadIndustryBenchmarks();
    }

    /**
     * Calcule le score de crédit pour une demande de prêt
     */
    public function calculateCreditScore(
        Customer $customer,
        array $loanData,
        array $additionalData = []
    ): array {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Starting credit scoring calculation', [
                'customer_id' => $customer->getId(),
                'loan_amount' => $loanData['amount'] ?? null,
                'model_version' => $this->configuration['scoring_model_version']
            ]);

            // 1. Collecte et préparation des données
            $features = $this->extractFeatures($customer, $loanData, $additionalData);
            
            // 2. Validation des données d'entrée
            $validationResult = $this->validateFeatures($features);
            if (!$validationResult['valid']) {
                throw new \InvalidArgumentException(
                    'Invalid features for scoring: ' . implode(', ', $validationResult['errors'])
                );
            }

            // 3. Calcul du score via ML ou règles de fallback
            if ($this->configuration['enable_ml_api'] && $this->isMLApiAvailable()) {
                $scoringResult = $this->calculateMLScore($features);
            } else {
                $scoringResult = $this->calculateRuleBasedScore($features);
            }

            // 4. Post-traitement et ajustements
            $finalScore = $this->applyBusinessRules($scoringResult, $features);
            
            // 5. Calcul des métriques de risque
            $riskMetrics = $this->calculateRiskMetrics($finalScore, $features);
            
            // 6. Recommandations et actions
            $recommendations = $this->generateRecommendations($finalScore, $riskMetrics, $features);

            $executionTime = microtime(true) - $startTime;

            $result = [
                'credit_score' => $finalScore['score'],
                'risk_level' => $finalScore['risk_level'],
                'confidence' => $finalScore['confidence'],
                'risk_metrics' => $riskMetrics,
                'recommendations' => $recommendations,
                'features_used' => $features,
                'model_version' => $this->configuration['scoring_model_version'],
                'calculation_method' => $finalScore['method'],
                'execution_time' => $executionTime,
                'timestamp' => new \DateTimeImmutable()
            ];

            $this->logger->info('Credit scoring completed successfully', [
                'customer_id' => $customer->getId(),
                'score' => $finalScore['score'],
                'risk_level' => $finalScore['risk_level'],
                'confidence' => $finalScore['confidence'],
                'execution_time' => $executionTime
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Credit scoring calculation failed', [
                'customer_id' => $customer->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Score de sécurité en cas d'erreur
            return [
                'credit_score' => 300, // Score très conservateur
                'risk_level' => 'high',
                'confidence' => 0.0,
                'risk_metrics' => ['error' => true],
                'recommendations' => ['manual_review_required' => true],
                'error' => $e->getMessage(),
                'calculation_method' => 'error_fallback',
                'timestamp' => new \DateTimeImmutable()
            ];
        }
    }

    /**
     * Extraction des caractéristiques (features) pour le modèle
     */
    private function extractFeatures(Customer $customer, array $loanData, array $additionalData): array
    {
        // Données démographiques
        $demographics = $this->extractDemographics($customer);
        
        // Données financières
        $financial = $this->extractFinancialData($customer, $loanData);
        
        // Historique de crédit
        $creditHistory = $this->extractCreditHistory($customer);
        
        // Données comportementales
        $behavioral = $this->extractBehavioralData($customer);
        
        // Données externes (si disponibles)
        $external = $this->extractExternalData($customer, $additionalData);
        
        // Agrégation des caractéristiques
        return array_merge($demographics, $financial, $creditHistory, $behavioral, $external);
    }

    /**
     * Extraction des données démographiques
     */
    private function extractDemographics(Customer $customer): array
    {
        $age = $customer->getBirthDate() 
            ? (new \DateTime())->diff($customer->getBirthDate())->y 
            : null;

        return [
            'age' => $age,
            'age_group' => $this->categorizeAge($age),
            'employment_status' => $customer->getEmploymentStatus(),
            'employment_duration_months' => $customer->getEmploymentDurationMonths(),
            'employment_type' => $customer->getEmploymentType(),
            'education_level' => $customer->getEducationLevel(),
            'marital_status' => $customer->getMaritalStatus(),
            'number_of_dependents' => $customer->getNumberOfDependents(),
            'housing_status' => $customer->getHousingStatus(),
            'region' => $customer->getAddress()?->getRegion(),
            'city_population' => $this->getCityPopulation($customer->getAddress()?->getCity())
        ];
    }

    /**
     * Extraction des données financières
     */
    private function extractFinancialData(Customer $customer, array $loanData): array
    {
        $monthlyIncome = $customer->getMonthlyIncome();
        $monthlyExpenses = $customer->getMonthlyExpenses();
        $requestedAmount = $loanData['amount'] ?? 0;
        $loanTerm = $loanData['term_months'] ?? 12;

        // Calcul des ratios financiers
        $debtToIncome = $monthlyIncome > 0 ? ($monthlyExpenses / $monthlyIncome) : 1.0;
        $loanToIncome = $monthlyIncome > 0 ? (($requestedAmount / $loanTerm) / $monthlyIncome) : 1.0;
        $disposableIncome = $monthlyIncome - $monthlyExpenses;

        return [
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'disposable_income' => $disposableIncome,
            'requested_amount' => $requestedAmount,
            'loan_term_months' => $loanTerm,
            'loan_purpose' => $loanData['purpose'] ?? 'other',
            'debt_to_income_ratio' => $debtToIncome,
            'loan_to_income_ratio' => $loanToIncome,
            'income_stability' => $this->calculateIncomeStability($customer),
            'savings_amount' => $customer->getSavingsAmount(),
            'existing_debt_amount' => $customer->getExistingDebtAmount(),
            'credit_card_limit' => $customer->getCreditCardLimit(),
            'credit_card_utilization' => $this->calculateCreditCardUtilization($customer),
            'financial_assets_value' => $customer->getFinancialAssetsValue()
        ];
    }

    /**
     * Extraction de l'historique de crédit
     */
    private function extractCreditHistory(Customer $customer): array
    {
        $loanRepository = $this->entityManager->getRepository(Loan::class);
        $previousLoans = $loanRepository->findBy(['customer' => $customer]);

        $totalLoans = count($previousLoans);
        $completedLoans = 0;
        $defaultedLoans = 0;
        $totalAmountBorrowed = 0;
        $averagePaymentDelay = 0;
        $lastLoanDate = null;

        foreach ($previousLoans as $loan) {
            $totalAmountBorrowed += $loan->getAmount();
            
            if ($loan->getStatus() === 'completed') {
                $completedLoans++;
            } elseif ($loan->getStatus() === 'defaulted') {
                $defaultedLoans++;
            }

            if (!$lastLoanDate || $loan->getCreatedAt() > $lastLoanDate) {
                $lastLoanDate = $loan->getCreatedAt();
            }

            // Calcul du retard moyen de paiement
            $averagePaymentDelay += $this->calculateAveragePaymentDelay($loan);
        }

        $averagePaymentDelay = $totalLoans > 0 ? $averagePaymentDelay / $totalLoans : 0;
        $completionRate = $totalLoans > 0 ? $completedLoans / $totalLoans : 0;
        $defaultRate = $totalLoans > 0 ? $defaultedLoans / $totalLoans : 0;

        return [
            'total_previous_loans' => $totalLoans,
            'completed_loans' => $completedLoans,
            'defaulted_loans' => $defaultedLoans,
            'loan_completion_rate' => $completionRate,
            'loan_default_rate' => $defaultRate,
            'total_amount_borrowed' => $totalAmountBorrowed,
            'average_payment_delay_days' => $averagePaymentDelay,
            'months_since_last_loan' => $lastLoanDate 
                ? (new \DateTime())->diff($lastLoanDate)->m 
                : null,
            'credit_score_external' => $customer->getExternalCreditScore(),
            'credit_history_length_years' => $this->calculateCreditHistoryLength($customer),
            'number_of_credit_inquiries' => $customer->getNumberOfCreditInquiries(),
            'has_bankruptcy_history' => $customer->hasBankruptcyHistory(),
            'has_litigation_history' => $customer->hasLitigationHistory()
        ];
    }

    /**
     * Extraction des données comportementales
     */
    private function extractBehavioralData(Customer $customer): array
    {
        return [
            'application_channel' => $customer->getApplicationChannel(),
            'application_completion_time_minutes' => $customer->getApplicationCompletionTime(),
            'number_of_application_modifications' => $customer->getNumberOfApplicationModifications(),
            'response_time_to_requests_hours' => $customer->getAverageResponseTimeToRequests(),
            'documents_submission_promptness' => $this->calculateDocumentSubmissionPromptness($customer),
            'communication_preference' => $customer->getCommunicationPreference(),
            'digital_engagement_score' => $this->calculateDigitalEngagementScore($customer),
            'customer_since_months' => $this->calculateCustomerTenure($customer),
            'number_of_support_contacts' => $customer->getNumberOfSupportContacts(),
            'has_opted_for_auto_payment' => $customer->hasOptedForAutoPayment(),
            'prefers_paperless_communication' => $customer->prefersPaperlessCommunication()
        ];
    }

    /**
     * Extraction des données externes
     */
    private function extractExternalData(Customer $customer, array $additionalData): array
    {
        return [
            'economic_indicator_regional' => $this->getRegionalEconomicIndicator($customer),
            'unemployment_rate_regional' => $this->getRegionalUnemploymentRate($customer),
            'inflation_rate_current' => $this->getCurrentInflationRate(),
            'interest_rate_environment' => $this->getCurrentInterestRateEnvironment(),
            'seasonal_factor' => $this->getSeasonalFactor(),
            'industry_risk_score' => $this->getIndustryRiskScore($customer->getEmploymentSector()),
            'company_size_score' => $this->getCompanySizeScore($customer->getEmployerSize()),
            'social_media_sentiment' => $additionalData['social_media_sentiment'] ?? null,
            'fraud_indicators' => $this->detectFraudIndicators($customer, $additionalData),
            'device_fingerprint_risk' => $additionalData['device_risk_score'] ?? null
        ];
    }

    /**
     * Calcul du score via API ML externe
     */
    private function calculateMLScore(array $features): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->configuration['ml_api_endpoint'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->configuration['ml_api_key'],
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'features' => $features,
                    'model_version' => $this->configuration['scoring_model_version'],
                    'return_explanation' => true
                ],
                'timeout' => 10
            ]);

            $data = $response->toArray();
            
            return [
                'score' => $data['predicted_score'],
                'confidence' => $data['confidence'],
                'risk_level' => $this->convertScoreToRiskLevel($data['predicted_score']),
                'method' => 'machine_learning',
                'model_explanation' => $data['explanation'] ?? null,
                'feature_importance' => $data['feature_importance'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->warning('ML API call failed, falling back to rule-based scoring', [
                'error' => $e->getMessage()
            ]);

            return $this->calculateRuleBasedScore($features);
        }
    }

    /**
     * Calcul du score via règles métier
     */
    private function calculateRuleBasedScore(array $features): array
    {
        $score = 500; // Score de base
        $factors = [];

        // Facteurs démographiques (15% du score)
        $score += $this->applyDemographicFactors($features, $factors) * 0.15;
        
        // Facteurs financiers (35% du score)
        $score += $this->applyFinancialFactors($features, $factors) * 0.35;
        
        // Historique de crédit (30% du score)
        $score += $this->applyCreditHistoryFactors($features, $factors) * 0.30;
        
        // Facteurs comportementaux (10% du score)
        $score += $this->applyBehavioralFactors($features, $factors) * 0.10;
        
        // Facteurs externes (10% du score)
        $score += $this->applyExternalFactors($features, $factors) * 0.10;

        // Normalisation du score entre 300 et 850
        $score = max(300, min(850, $score));
        
        return [
            'score' => round($score),
            'confidence' => $this->calculateRuleBasedConfidence($factors),
            'risk_level' => $this->convertScoreToRiskLevel($score),
            'method' => 'rule_based',
            'scoring_factors' => $factors
        ];
    }

    /**
     * Application des facteurs démographiques
     */
    private function applyDemographicFactors(array $features, array &$factors): float
    {
        $score = 0;
        
        // Âge (sweet spot entre 25-45 ans)
        if ($features['age']) {
            if ($features['age'] >= 25 && $features['age'] <= 45) {
                $score += 50;
                $factors[] = ['factor' => 'optimal_age', 'impact' => 50];
            } elseif ($features['age'] >= 18 && $features['age'] <= 65) {
                $score += 20;
                $factors[] = ['factor' => 'acceptable_age', 'impact' => 20];
            } else {
                $score -= 30;
                $factors[] = ['factor' => 'suboptimal_age', 'impact' => -30];
            }
        }

        // Statut d'emploi
        $employmentScore = match ($features['employment_status']) {
            'CDI' => 50,
            'CDD' => 20,
            'freelance' => 10,
            'fonctionnaire' => 60,
            'retired' => 30,
            'unemployed' => -50,
            default => 0
        };
        $score += $employmentScore;
        $factors[] = ['factor' => 'employment_status', 'impact' => $employmentScore];

        // Durée d'emploi
        if ($features['employment_duration_months'] >= 24) {
            $score += 30;
            $factors[] = ['factor' => 'stable_employment', 'impact' => 30];
        } elseif ($features['employment_duration_months'] >= 6) {
            $score += 10;
            $factors[] = ['factor' => 'recent_employment', 'impact' => 10];
        } else {
            $score -= 20;
            $factors[] = ['factor' => 'unstable_employment', 'impact' => -20];
        }

        return $score;
    }

    /**
     * Application des facteurs financiers
     */
    private function applyFinancialFactors(array $features, array &$factors): float
    {
        $score = 0;

        // Ratio d'endettement
        $debtRatio = $features['debt_to_income_ratio'] ?? 1.0;
        if ($debtRatio <= 0.30) {
            $score += 60;
            $factors[] = ['factor' => 'low_debt_ratio', 'impact' => 60];
        } elseif ($debtRatio <= 0.50) {
            $score += 20;
            $factors[] = ['factor' => 'moderate_debt_ratio', 'impact' => 20];
        } else {
            $score -= 40;
            $factors[] = ['factor' => 'high_debt_ratio', 'impact' => -40];
        }

        // Revenus
        $monthlyIncome = $features['monthly_income'] ?? 0;
        if ($monthlyIncome >= 4000) {
            $score += 40;
            $factors[] = ['factor' => 'high_income', 'impact' => 40];
        } elseif ($monthlyIncome >= 2000) {
            $score += 20;
            $factors[] = ['factor' => 'moderate_income', 'impact' => 20];
        } elseif ($monthlyIncome >= 1200) {
            $score += 0;
            $factors[] = ['factor' => 'basic_income', 'impact' => 0];
        } else {
            $score -= 30;
            $factors[] = ['factor' => 'low_income', 'impact' => -30];
        }

        // Épargne
        $savings = $features['savings_amount'] ?? 0;
        if ($savings >= $monthlyIncome * 3) {
            $score += 30;
            $factors[] = ['factor' => 'high_savings', 'impact' => 30];
        } elseif ($savings >= $monthlyIncome) {
            $score += 15;
            $factors[] = ['factor' => 'moderate_savings', 'impact' => 15];
        }

        return $score;
    }

    /**
     * Application des facteurs d'historique de crédit
     */
    private function applyCreditHistoryFactors(array $features, array &$factors): float
    {
        $score = 0;

        // Taux de remboursement
        $completionRate = $features['loan_completion_rate'] ?? 0;
        if ($completionRate >= 0.95) {
            $score += 70;
            $factors[] = ['factor' => 'excellent_repayment_history', 'impact' => 70];
        } elseif ($completionRate >= 0.80) {
            $score += 40;
            $factors[] = ['factor' => 'good_repayment_history', 'impact' => 40];
        } elseif ($completionRate >= 0.60) {
            $score += 10;
            $factors[] = ['factor' => 'fair_repayment_history', 'impact' => 10];
        } else {
            $score -= 50;
            $factors[] = ['factor' => 'poor_repayment_history', 'impact' => -50];
        }

        // Taux de défaut
        $defaultRate = $features['loan_default_rate'] ?? 0;
        if ($defaultRate === 0.0) {
            $score += 40;
            $factors[] = ['factor' => 'no_defaults', 'impact' => 40];
        } elseif ($defaultRate <= 0.05) {
            $score += 10;
            $factors[] = ['factor' => 'minimal_defaults', 'impact' => 10];
        } else {
            $score -= 60;
            $factors[] = ['factor' => 'high_default_rate', 'impact' => -60];
        }

        // Ponctualité des paiements
        $avgDelay = $features['average_payment_delay_days'] ?? 0;
        if ($avgDelay <= 5) {
            $score += 30;
            $factors[] = ['factor' => 'punctual_payments', 'impact' => 30];
        } elseif ($avgDelay <= 15) {
            $score += 10;
            $factors[] = ['factor' => 'mostly_punctual', 'impact' => 10];
        } else {
            $score -= 25;
            $factors[] = ['factor' => 'frequent_delays', 'impact' => -25];
        }

        return $score;
    }

    /**
     * Application des facteurs comportementaux
     */
    private function applyBehavioralFactors(array $features, array &$factors): float
    {
        $score = 0;

        // Engagement digital
        $digitalScore = $features['digital_engagement_score'] ?? 0;
        if ($digitalScore >= 80) {
            $score += 20;
            $factors[] = ['factor' => 'high_digital_engagement', 'impact' => 20];
        } elseif ($digitalScore >= 60) {
            $score += 10;
            $factors[] = ['factor' => 'moderate_digital_engagement', 'impact' => 10];
        }

        // Prélèvement automatique
        if ($features['has_opted_for_auto_payment']) {
            $score += 15;
            $factors[] = ['factor' => 'auto_payment_opted', 'impact' => 15];
        }

        // Rapidité de soumission des documents
        $docPromptness = $features['documents_submission_promptness'] ?? 0;
        if ($docPromptness >= 80) {
            $score += 10;
            $factors[] = ['factor' => 'prompt_document_submission', 'impact' => 10];
        }

        return $score;
    }

    /**
     * Application des facteurs externes
     */
    private function applyExternalFactors(array $features, array &$factors): float
    {
        $score = 0;

        // Indicateurs économiques régionaux
        $economicIndicator = $features['economic_indicator_regional'] ?? 0;
        if ($economicIndicator > 0.8) {
            $score += 20;
            $factors[] = ['factor' => 'positive_economic_environment', 'impact' => 20];
        } elseif ($economicIndicator < 0.4) {
            $score -= 15;
            $factors[] = ['factor' => 'negative_economic_environment', 'impact' => -15];
        }

        // Secteur d'activité
        $industryRisk = $features['industry_risk_score'] ?? 0.5;
        if ($industryRisk <= 0.3) {
            $score += 15;
            $factors[] = ['factor' => 'low_risk_industry', 'impact' => 15];
        } elseif ($industryRisk >= 0.7) {
            $score -= 20;
            $factors[] = ['factor' => 'high_risk_industry', 'impact' => -20];
        }

        // Indicateurs de fraude
        $fraudIndicators = $features['fraud_indicators'] ?? [];
        if (count($fraudIndicators) > 0) {
            $fraudPenalty = -10 * count($fraudIndicators);
            $score += $fraudPenalty;
            $factors[] = ['factor' => 'fraud_indicators', 'impact' => $fraudPenalty];
        }

        return $score;
    }

    /**
     * Conversion du score en niveau de risque
     */
    private function convertScoreToRiskLevel(float $score): string
    {
        return match (true) {
            $score >= 750 => 'very_low',
            $score >= 650 => 'low',
            $score >= 550 => 'medium',
            $score >= 450 => 'high',
            default => 'very_high'
        };
    }

    /**
     * Application des règles métier post-traitement
     */
    private function applyBusinessRules(array $scoringResult, array $features): array
    {
        $score = $scoringResult['score'];
        $adjustments = [];

        // Règle : Montant trop élevé par rapport aux revenus
        $loanToIncomeRatio = $features['loan_to_income_ratio'] ?? 0;
        if ($loanToIncomeRatio > 0.5) {
            $penalty = min(100, ($loanToIncomeRatio - 0.5) * 200);
            $score -= $penalty;
            $adjustments[] = ['rule' => 'high_loan_to_income', 'adjustment' => -$penalty];
        }

        // Règle : Premier emprunt bonus (si aucun historique négatif)
        if (($features['total_previous_loans'] ?? 0) === 0 && 
            ($features['external_credit_score'] ?? 0) === 0) {
            $score += 25;
            $adjustments[] = ['rule' => 'first_time_borrower_bonus', 'adjustment' => 25];
        }

        // Règle : Période économique difficile
        if ($this->isEconomicCrisisMode()) {
            $score -= 50;
            $adjustments[] = ['rule' => 'economic_crisis_penalty', 'adjustment' => -50];
        }

        // Normalisation finale
        $score = max(300, min(850, $score));

        return array_merge($scoringResult, [
            'score' => round($score),
            'business_rule_adjustments' => $adjustments,
            'risk_level' => $this->convertScoreToRiskLevel($score)
        ]);
    }

    /**
     * Calcul des métriques de risque détaillées
     */
    private function calculateRiskMetrics(array $scoringResult, array $features): array
    {
        $score = $scoringResult['score'];
        
        // Probabilité de défaut basée sur le score
        $defaultProbability = $this->calculateDefaultProbability($score);
        
        // Perte attendue
        $lossGivenDefault = 0.45; // 45% de perte en cas de défaut (benchmark industrie)
        $exposureAtDefault = $features['requested_amount'] ?? 0;
        $expectedLoss = $defaultProbability * $lossGivenDefault * $exposureAtDefault;
        
        // Profitabilité attendue
        $expectedProfit = $this->calculateExpectedProfit($features, $defaultProbability);
        
        return [
            'default_probability' => $defaultProbability,
            'loss_given_default' => $lossGivenDefault,
            'exposure_at_default' => $exposureAtDefault,
            'expected_loss' => $expectedLoss,
            'expected_profit' => $expectedProfit,
            'profit_margin' => $expectedProfit > 0 ? (($expectedProfit - $expectedLoss) / $expectedProfit) * 100 : 0,
            'risk_adjusted_return' => $expectedProfit - $expectedLoss,
            'capital_requirement' => $expectedLoss * 1.2, // Buffer de sécurité
            'pricing_recommendation' => $this->calculatePricingRecommendation($defaultProbability, $features)
        ];
    }

    /**
     * Génération des recommandations
     */
    private function generateRecommendations(array $scoringResult, array $riskMetrics, array $features): array
    {
        $score = $scoringResult['score'];
        $riskLevel = $scoringResult['risk_level'];
        $recommendations = [];

        // Recommandations selon le score
        switch ($riskLevel) {
            case 'very_low':
                $recommendations['decision'] = 'auto_approve';
                $recommendations['max_amount'] = $features['requested_amount'];
                $recommendations['interest_rate_adjustment'] = -0.5; // Réduction de 0.5%
                $recommendations['conditions'] = ['standard_terms'];
                break;
                
            case 'low':
                $recommendations['decision'] = 'approve';
                $recommendations['max_amount'] = $features['requested_amount'];
                $recommendations['interest_rate_adjustment'] = 0.0;
                $recommendations['conditions'] = ['standard_terms'];
                break;
                
            case 'medium':
                $recommendations['decision'] = 'conditional_approve';
                $recommendations['max_amount'] = $features['requested_amount'] * 0.8;
                $recommendations['interest_rate_adjustment'] = 1.0;
                $recommendations['conditions'] = [
                    'additional_guarantor',
                    'reduced_loan_term',
                    'monthly_income_verification'
                ];
                break;
                
            case 'high':
                $recommendations['decision'] = 'manual_review';
                $recommendations['max_amount'] = $features['requested_amount'] * 0.6;
                $recommendations['interest_rate_adjustment'] = 2.5;
                $recommendations['conditions'] = [
                    'collateral_required',
                    'guarantor_required',
                    'strict_monitoring',
                    'quarterly_review'
                ];
                break;
                
            case 'very_high':
                $recommendations['decision'] = 'reject';
                $recommendations['rejection_reason'] = 'high_risk_profile';
                $recommendations['alternative_products'] = ['secured_loan', 'credit_building_program'];
                break;
        }

        // Recommandations spécifiques selon les facteurs de risque
        $recommendations['risk_mitigation'] = $this->generateRiskMitigationRecommendations($features);
        
        // Recommandations de pricing
        $recommendations['pricing'] = $riskMetrics['pricing_recommendation'];
        
        // Recommandations de monitoring
        $recommendations['monitoring'] = $this->generateMonitoringRecommendations($riskLevel, $features);

        return $recommendations;
    }

    // Méthodes utilitaires et de calcul...
    private function initializeModelWeights(): void
    {
        $this->modelWeights = [
            'demographic' => 0.15,
            'financial' => 0.35,
            'credit_history' => 0.30,
            'behavioral' => 0.10,
            'external' => 0.10
        ];
    }

    private function loadIndustryBenchmarks(): void
    {
        $this->industryBenchmarks = [
            'technology' => ['risk_score' => 0.2, 'stability' => 0.8],
            'healthcare' => ['risk_score' => 0.15, 'stability' => 0.9],
            'finance' => ['risk_score' => 0.25, 'stability' => 0.75],
            'retail' => ['risk_score' => 0.4, 'stability' => 0.6],
            'construction' => ['risk_score' => 0.6, 'stability' => 0.5],
            'hospitality' => ['risk_score' => 0.7, 'stability' => 0.4],
            'default' => ['risk_score' => 0.5, 'stability' => 0.6]
        ];
    }

    private function validateFeatures(array $features): array
    {
        $errors = [];
        $required = ['monthly_income', 'requested_amount', 'employment_status'];
        
        foreach ($required as $field) {
            if (!isset($features[$field]) || $features[$field] === null) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private function isMLApiAvailable(): bool
    {
        if (!$this->configuration['ml_api_endpoint']) {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', $this->configuration['ml_api_endpoint'] . '/health', [
                'timeout' => 3
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function calculateDefaultProbability(float $score): float
    {
        // Courbe logistique basée sur les données historiques
        return 1 / (1 + exp(($score - 400) / 100));
    }

    private function calculateExpectedProfit(array $features, float $defaultProbability): float
    {
        $loanAmount = $features['requested_amount'] ?? 0;
        $loanTerm = $features['loan_term_months'] ?? 12;
        $baseInterestRate = 0.08; // 8% de base
        
        $totalInterest = $loanAmount * $baseInterestRate * ($loanTerm / 12);
        $probability_of_payment = 1 - $defaultProbability;
        
        return $totalInterest * $probability_of_payment;
    }

    private function calculatePricingRecommendation(float $defaultProbability, array $features): array
    {
        $riskPremium = $defaultProbability * 5; // Max 5% de prime de risque
        $baseRate = 0.08; // 8% de base
        $recommendedRate = $baseRate + $riskPremium;
        
        return [
            'base_rate' => $baseRate,
            'risk_premium' => $riskPremium,
            'recommended_rate' => min($recommendedRate, 0.18), // Cap à 18%
            'competitive_analysis' => $this->getCompetitiveRateAnalysis($features)
        ];
    }

    // Méthodes de calcul spécifiques (à implémenter selon les besoins)
    private function categorizeAge(?int $age): ?string
    {
        if (!$age) return null;
        return match (true) {
            $age < 25 => 'young',
            $age <= 35 => 'young_adult',
            $age <= 50 => 'middle_aged',
            $age <= 65 => 'senior',
            default => 'elderly'
        };
    }

    private function getCityPopulation(?string $city): ?int
    {
        // Base de données des populations par ville (simplifié)
        $populations = [
            'Paris' => 2200000,
            'Lyon' => 515000,
            'Marseille' => 860000,
            // ... autres villes
        ];
        
        return $populations[$city] ?? null;
    }

    private function calculateIncomeStability(Customer $customer): float
    {
        // Analyse de la stabilité des revenus basée sur l'historique
        return 0.8; // Placeholder
    }

    private function calculateCreditCardUtilization(Customer $customer): float
    {
        $limit = $customer->getCreditCardLimit();
        $balance = $customer->getCreditCardBalance();
        
        return $limit > 0 ? $balance / $limit : 0;
    }

    private function calculateAveragePaymentDelay(Loan $loan): float
    {
        // Calcul basé sur l'historique des paiements
        return 0; // Placeholder
    }

    private function calculateCreditHistoryLength(Customer $customer): int
    {
        // Calcul de l'ancienneté du crédit
        return 5; // Placeholder
    }

    private function calculateDocumentSubmissionPromptness(Customer $customer): float
    {
        // Score de rapidité de soumission des documents
        return 75; // Placeholder
    }

    private function calculateDigitalEngagementScore(Customer $customer): float
    {
        // Score d'engagement digital
        return 80; // Placeholder
    }

    private function calculateCustomerTenure(Customer $customer): int
    {
        return $customer->getCreatedAt() 
            ? (new \DateTime())->diff($customer->getCreatedAt())->m 
            : 0;
    }

    private function getRegionalEconomicIndicator(Customer $customer): float
    {
        // Indicateur économique régional
        return 0.7; // Placeholder
    }

    private function getRegionalUnemploymentRate(Customer $customer): float
    {
        return 0.08; // 8%
    }

    private function getCurrentInflationRate(): float
    {
        return 0.02; // 2%
    }

    private function getCurrentInterestRateEnvironment(): string
    {
        return 'moderate'; // low, moderate, high
    }

    private function getSeasonalFactor(): float
    {
        $month = (int) date('n');
        return match ($month) {
            12, 1, 2 => 1.1, // Fin d'année, plus de demandes
            6, 7, 8 => 0.9,  // Été, moins de demandes
            default => 1.0
        };
    }

    private function getIndustryRiskScore(string $sector): float
    {
        return $this->industryBenchmarks[$sector]['risk_score'] ?? 
               $this->industryBenchmarks['default']['risk_score'];
    }

    private function getCompanySizeScore(?string $employerSize): float
    {
        return match ($employerSize) {
            'large' => 0.2,
            'medium' => 0.4,
            'small' => 0.6,
            'micro' => 0.8,
            default => 0.5
        };
    }

    private function detectFraudIndicators(Customer $customer, array $additionalData): array
    {
        $indicators = [];
        
        // Vérifications de base
        if (isset($additionalData['multiple_applications_same_day'])) {
            $indicators[] = 'multiple_applications';
        }
        
        if (isset($additionalData['suspicious_document_patterns'])) {
            $indicators[] = 'document_anomalies';
        }
        
        return $indicators;
    }

    private function calculateRuleBasedConfidence(array $factors): float
    {
        // Calcul de la confiance basé sur le nombre et la qualité des facteurs
        $positiveFactors = count(array_filter($factors, fn($f) => $f['impact'] > 0));
        $totalFactors = count($factors);
        
        return $totalFactors > 0 ? min(0.95, 0.5 + ($positiveFactors / $totalFactors) * 0.4) : 0.5;
    }

    private function isEconomicCrisisMode(): bool
    {
        // Vérification si nous sommes en mode crise économique
        return false; // Placeholder
    }

    private function generateRiskMitigationRecommendations(array $features): array
    {
        $recommendations = [];
        
        if (($features['debt_to_income_ratio'] ?? 0) > 0.4) {
            $recommendations[] = 'debt_consolidation_offer';
        }
        
        if (($features['savings_amount'] ?? 0) < ($features['monthly_income'] ?? 0)) {
            $recommendations[] = 'savings_building_program';
        }
        
        return $recommendations;
    }

    private function generateMonitoringRecommendations(string $riskLevel, array $features): array
    {
        return match ($riskLevel) {
            'very_high', 'high' => [
                'frequency' => 'monthly',
                'alerts' => ['payment_delays', 'income_changes', 'employment_changes'],
                'automated_actions' => ['payment_reminder_escalation']
            ],
            'medium' => [
                'frequency' => 'quarterly',
                'alerts' => ['payment_delays', 'major_life_changes'],
                'automated_actions' => ['standard_reminders']
            ],
            default => [
                'frequency' => 'annual',
                'alerts' => ['significant_defaults'],
                'automated_actions' => ['minimal']
            ]
        };
    }

    private function getCompetitiveRateAnalysis(array $features): array
    {
        // Analyse des taux concurrents
        return [
            'market_average' => 0.09,
            'our_position' => 'competitive',
            'recommendation' => 'match_market'
        ];
    }
}
