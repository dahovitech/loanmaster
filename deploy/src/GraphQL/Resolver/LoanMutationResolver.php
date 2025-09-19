<?php

namespace App\GraphQL\Resolver;

use App\Application\Command\Loan\CreateLoanApplicationCommand;
use App\Infrastructure\EventSourcing\CommandBus;
use App\Infrastructure\EventSourcing\Repository\LoanEventSourcedRepository;
use Ramsey\Uuid\Uuid;
use GraphQL\Error\UserError;
use Exception;

/**
 * Résolveur GraphQL pour les mutations sur les prêts
 * Gestion complète des commandes avec validation
 */
class LoanMutationResolver
{
    private CommandBus $commandBus;
    private LoanEventSourcedRepository $loanRepository;

    public function __construct(
        CommandBus $commandBus,
        LoanEventSourcedRepository $loanRepository
    ) {
        $this->commandBus = $commandBus;
        $this->loanRepository = $loanRepository;
    }

    /**
     * Crée une nouvelle demande de prêt
     */
    public function createApplication($root, array $args, $context, $info): array
    {
        try {
            $input = $args['input'];
            
            // Validation des données d'entrée
            $errors = $this->validateCreateLoanInput($input);
            if (!empty($errors)) {
                return [
                    'loan' => null,
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            $loanId = Uuid::uuid4();
            $customerId = Uuid::fromString($input['customerId']);
            
            $command = new CreateLoanApplicationCommand(
                $loanId,
                $customerId,
                (float) $input['requestedAmount'],
                (int) $input['durationMonths'],
                $input['purpose'],
                $input['customerData'] ?? [],
                $input['financialData'] ?? [],
                $this->getCurrentUserId($context),
                $this->getClientIp($context),
                $this->getUserAgent($context)
            );
            
            $loan = $this->commandBus->dispatch($command);
            
            return [
                'loan' => $this->transformLoanAggregate($loan),
                'success' => true,
                'errors' => []
            ];
            
        } catch (Exception $e) {
            return [
                'loan' => null,
                'success' => false,
                'errors' => [
                    [
                        'field' => 'general',
                        'message' => $e->getMessage(),
                        'code' => 'CREATION_FAILED'
                    ]
                ]
            ];
        }
    }

    /**
     * Change le statut d'un prêt
     */
    public function changeStatus($root, array $args, $context, $info): array
    {
        try {
            $input = $args['input'];
            
            $loanId = Uuid::fromString($input['loanId']);
            $loan = $this->loanRepository->loadLoan($loanId);
            
            if (!$loan) {
                return [
                    'loan' => null,
                    'success' => false,
                    'errors' => [
                        [
                            'field' => 'loanId',
                            'message' => 'Loan not found',
                            'code' => 'NOT_FOUND'
                        ]
                    ]
                ];
            }
            
            $loan->changeStatus(
                strtolower($input['newStatus']),
                $input['reason'],
                $this->getCurrentUserId($context) ? Uuid::fromString($this->getCurrentUserId($context)) : null,
                $input['comments'] ?? null,
                $input['additionalData'] ?? []
            );
            
            $this->loanRepository->saveLoan($loan);
            
            return [
                'loan' => $this->transformLoanAggregate($loan),
                'success' => true,
                'errors' => []
            ];
            
        } catch (Exception $e) {
            return [
                'loan' => null,
                'success' => false,
                'errors' => [
                    [
                        'field' => 'status',
                        'message' => $e->getMessage(),
                        'code' => 'STATUS_CHANGE_FAILED'
                    ]
                ]
            ];
        }
    }

    /**
     * Évalue le risque d'un prêt
     */
    public function assessRisk($root, array $args, $context, $info): array
    {
        try {
            $input = $args['input'];
            
            $loanId = Uuid::fromString($input['loanId']);
            $loan = $this->loanRepository->loadLoan($loanId);
            
            if (!$loan) {
                return [
                    'loan' => null,
                    'assessment' => null,
                    'success' => false,
                    'errors' => [
                        [
                            'field' => 'loanId',
                            'message' => 'Loan not found',
                            'code' => 'NOT_FOUND'
                        ]
                    ]
                ];
            }
            
            // Calcul du score de risque basé sur les facteurs
            $riskScore = $this->calculateRiskScore($input['factors']);
            $riskLevel = $this->determineRiskLevel($riskScore);
            
            $loan->assessRisk(
                $riskScore,
                $riskLevel,
                $input['factors'],
                $input['assessmentMethod'],
                $this->getCurrentUserId($context) ? Uuid::fromString($this->getCurrentUserId($context)) : null
            );
            
            $this->loanRepository->saveLoan($loan);
            
            $assessment = [
                'id' => Uuid::uuid4()->toString(),
                'loanId' => $loanId->toString(),
                'score' => $riskScore,
                'level' => strtoupper($riskLevel),
                'factors' => $this->transformRiskFactors($input['factors']),
                'method' => $input['assessmentMethod'],
                'assessedBy' => $this->getCurrentUserId($context),
                'assessedAt' => new \DateTimeImmutable(),
                'approvalRecommendation' => $this->getApprovalRecommendation($riskScore),
                'requiredDocuments' => $this->getRequiredDocuments($riskLevel),
                'interestRateAdjustment' => $this->calculateRateAdjustment($riskScore)
            ];
            
            return [
                'loan' => $this->transformLoanAggregate($loan),
                'assessment' => $assessment,
                'success' => true,
                'errors' => []
            ];
            
        } catch (Exception $e) {
            return [
                'loan' => null,
                'assessment' => null,
                'success' => false,
                'errors' => [
                    [
                        'field' => 'assessment',
                        'message' => $e->getMessage(),
                        'code' => 'ASSESSMENT_FAILED'
                    ]
                ]
            ];
        }
    }

    /**
     * Valide les données d'entrée pour la création d'un prêt
     */
    private function validateCreateLoanInput(array $input): array
    {
        $errors = [];
        
        if (empty($input['customerId']) || !Uuid::isValid($input['customerId'])) {
            $errors[] = [
                'field' => 'customerId',
                'message' => 'Valid customer ID is required',
                'code' => 'INVALID_CUSTOMER_ID'
            ];
        }
        
        if (empty($input['requestedAmount']) || $input['requestedAmount'] <= 0) {
            $errors[] = [
                'field' => 'requestedAmount',
                'message' => 'Requested amount must be positive',
                'code' => 'INVALID_AMOUNT'
            ];
        }
        
        if ($input['requestedAmount'] > 1000000) {
            $errors[] = [
                'field' => 'requestedAmount',
                'message' => 'Requested amount exceeds maximum limit',
                'code' => 'AMOUNT_TOO_HIGH'
            ];
        }
        
        if (empty($input['durationMonths']) || $input['durationMonths'] <= 0 || $input['durationMonths'] > 360) {
            $errors[] = [
                'field' => 'durationMonths',
                'message' => 'Duration must be between 1 and 360 months',
                'code' => 'INVALID_DURATION'
            ];
        }
        
        if (empty($input['purpose']) || strlen($input['purpose']) < 3) {
            $errors[] = [
                'field' => 'purpose',
                'message' => 'Purpose must be at least 3 characters',
                'code' => 'INVALID_PURPOSE'
            ];
        }
        
        return $errors;
    }

    /**
     * Calcule le score de risque basé sur les facteurs
     */
    private function calculateRiskScore(array $factors): int
    {
        $score = 500; // Score de base
        
        // Facteurs positifs
        if (isset($factors['creditHistory'])) {
            $score += $factors['creditHistory'] * 2;
        }
        
        if (isset($factors['incomeStability'])) {
            $score += $factors['incomeStability'] * 1.5;
        }
        
        if (isset($factors['employment'])) {
            $score += $factors['employment'] * 1.2;
        }
        
        // Facteurs négatifs
        if (isset($factors['debtToIncome'])) {
            $score -= $factors['debtToIncome'] * 3;
        }
        
        return max(300, min(850, (int) $score));
    }

    /**
     * Détermine le niveau de risque basé sur le score
     */
    private function determineRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 750 => 'low',
            $score >= 650 => 'medium',
            $score >= 500 => 'high',
            default => 'critical'
        };
    }

    /**
     * Transforme un agrégat Loan en format GraphQL
     */
    private function transformLoanAggregate($loan): array
    {
        return [
            'id' => $loan->getId()->toString(),
            'customerId' => $loan->getCustomerId()->toString(),
            'requestedAmount' => $loan->getRequestedAmount(),
            'approvedAmount' => $loan->getApprovedAmount(),
            'currentBalance' => $loan->getCurrentBalance(),
            'status' => strtoupper($loan->getStatus()),
            'interestRate' => $loan->getInterestRate(),
            'riskScore' => $loan->getRiskScore(),
            'version' => $loan->getVersion()
        ];
    }

    /**
     * Récupère l'ID de l'utilisateur actuel depuis le contexte
     */
    private function getCurrentUserId($context): ?string
    {
        return $context['user']['id'] ?? null;
    }

    /**
     * Récupère l'adresse IP du client
     */
    private function getClientIp($context): ?string
    {
        return $context['request']['ip'] ?? null;
    }

    /**
     * Récupère le user agent
     */
    private function getUserAgent($context): ?string
    {
        return $context['request']['userAgent'] ?? null;
    }

    /**
     * Transforme les facteurs de risque
     */
    private function transformRiskFactors(array $factors): array
    {
        return array_map(function($key, $value) {
            return [
                'factor' => $key,
                'value' => $value,
                'weight' => $this->getFactorWeight($key)
            ];
        }, array_keys($factors), array_values($factors));
    }

    /**
     * Récupère le poids d'un facteur de risque
     */
    private function getFactorWeight(string $factor): float
    {
        return match ($factor) {
            'creditHistory' => 0.3,
            'incomeStability' => 0.25,
            'employment' => 0.2,
            'debtToIncome' => 0.25,
            default => 0.0
        };
    }

    /**
     * Génère une recommandation d'approbation
     */
    private function getApprovalRecommendation(int $score): string
    {
        return match (true) {
            $score >= 700 => 'APPROVE',
            $score >= 600 => 'CONDITIONAL',
            $score >= 500 => 'MANUAL_REVIEW',
            default => 'REJECT'
        };
    }

    /**
     * Récupère les documents requis selon le niveau de risque
     */
    private function getRequiredDocuments(string $riskLevel): array
    {
        return match ($riskLevel) {
            'low' => ['IDENTITY', 'INCOME_PROOF'],
            'medium' => ['IDENTITY', 'INCOME_PROOF', 'BANK_STATEMENTS'],
            'high' => ['IDENTITY', 'INCOME_PROOF', 'BANK_STATEMENTS', 'EMPLOYMENT_VERIFICATION'],
            'critical' => ['IDENTITY', 'INCOME_PROOF', 'BANK_STATEMENTS', 'EMPLOYMENT_VERIFICATION', 'CREDIT_REPORT'],
            default => ['IDENTITY']
        };
    }

    /**
     * Calcule l'ajustement du taux d'intérêt
     */
    private function calculateRateAdjustment(int $score): float
    {
        return match (true) {
            $score >= 800 => -0.5,
            $score >= 700 => 0.0,
            $score >= 600 => 1.0,
            $score >= 500 => 2.0,
            default => 3.0
        };
    }
}
