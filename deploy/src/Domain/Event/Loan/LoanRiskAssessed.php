<?php

declare(strict_types=1);

namespace App\Domain\Event\Loan;

use App\Domain\Event\AbstractDomainEvent;
use Ramsey\Uuid\UuidInterface;

/**
 * Événement déclenché lors de l'évaluation de risque d'un prêt
 */
class LoanRiskAssessed extends AbstractDomainEvent
{
    public function __construct(
        UuidInterface $loanId,
        int $riskScore,
        string $riskLevel,
        array $riskFactors,
        string $assessmentMethod,
        ?UuidInterface $assessedBy = null,
        int $version = 1
    ) {
        $payload = [
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'riskFactors' => $riskFactors,
            'assessmentMethod' => $assessmentMethod,
            'assessedBy' => $assessedBy?->toString(),
            'riskDetails' => [
                'creditHistoryScore' => $riskFactors['creditHistory'] ?? 0,
                'incomeStabilityScore' => $riskFactors['incomeStability'] ?? 0,
                'debtToIncomeRatio' => $riskFactors['debtToIncome'] ?? 0,
                'employmentScore' => $riskFactors['employment'] ?? 0
            ],
            'recommendations' => [
                'approvalRecommendation' => $riskScore >= 700 ? 'approve' : ($riskScore >= 500 ? 'conditional' : 'reject'),
                'requiredDocuments' => $this->getRequiredDocuments($riskLevel),
                'interestRateAdjustment' => $this->calculateRateAdjustment($riskScore)
            ]
        ];
        
        parent::__construct($loanId, $payload, $version);
    }

    public function getRiskScore(): int
    {
        return $this->payload['riskScore'];
    }

    public function getRiskLevel(): string
    {
        return $this->payload['riskLevel'];
    }

    public function getRiskFactors(): array
    {
        return $this->payload['riskFactors'];
    }

    public function getAssessmentMethod(): string
    {
        return $this->payload['assessmentMethod'];
    }

    public function getApprovalRecommendation(): string
    {
        return $this->payload['recommendations']['approvalRecommendation'];
    }

    public function getRequiredDocuments(): array
    {
        return $this->payload['recommendations']['requiredDocuments'];
    }

    public function getInterestRateAdjustment(): float
    {
        return $this->payload['recommendations']['interestRateAdjustment'];
    }

    private function getRequiredDocuments(string $riskLevel): array
    {
        return match ($riskLevel) {
            'low' => ['identity', 'income_proof'],
            'medium' => ['identity', 'income_proof', 'bank_statements'],
            'high' => ['identity', 'income_proof', 'bank_statements', 'employment_verification', 'credit_report'],
            default => ['identity']
        };
    }

    private function calculateRateAdjustment(int $riskScore): float
    {
        return match (true) {
            $riskScore >= 800 => -0.5, // Réduction de 0.5%
            $riskScore >= 700 => 0.0,  // Taux standard
            $riskScore >= 600 => 1.0,  // Majoration de 1%
            $riskScore >= 500 => 2.0,  // Majoration de 2%
            default => 3.0             // Majoration de 3%
        };
    }
}
