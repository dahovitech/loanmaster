<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\InterestRate;
use App\Domain\ValueObject\LoanType;

final class LoanCalculatorService
{
    public function calculateMonthlyPayment(
        Amount $amount,
        InterestRate $rate,
        Duration $duration
    ): Amount {
        return $amount->calculateInterest($rate, $duration);
    }
    
    public function calculateTotalAmount(
        Amount $amount,
        InterestRate $rate,
        Duration $duration
    ): Amount {
        $monthlyPayment = $this->calculateMonthlyPayment($amount, $rate, $duration);
        return $monthlyPayment->multiply($duration->getMonths());
    }
    
    public function calculateTotalInterest(
        Amount $amount,
        InterestRate $rate,
        Duration $duration
    ): Amount {
        $totalAmount = $this->calculateTotalAmount($amount, $rate, $duration);
        return $totalAmount->subtract($amount);
    }
    
    public function getEligibilityScore(
        LoanType $type,
        Amount $amount,
        Duration $duration,
        float $userIncome
    ): int {
        $score = 0;
        
        // Score basé sur le ratio montant/revenus
        $incomeRatio = $amount->getValue() / max($userIncome, 1);
        if ($incomeRatio <= 3) {
            $score += 40;
        } elseif ($incomeRatio <= 5) {
            $score += 25;
        } elseif ($incomeRatio <= 8) {
            $score += 10;
        }
        
        // Score basé sur la durée
        $maxDuration = $type->getMaxDurationMonths();
        $durationRatio = $duration->getMonths() / $maxDuration;
        if ($durationRatio <= 0.5) {
            $score += 30;
        } elseif ($durationRatio <= 0.75) {
            $score += 20;
        } else {
            $score += 10;
        }
        
        // Score basé sur le type de prêt
        $score += match ($type) {
            LoanType::MORTGAGE => 20,
            LoanType::AUTO => 15,
            LoanType::STUDENT => 10,
            LoanType::RENOVATION => 15,
            LoanType::BUSINESS => 10,
            LoanType::PERSONAL => 5,
        };
        
        return min(100, max(0, $score));
    }
}
