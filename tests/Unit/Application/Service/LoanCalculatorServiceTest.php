<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\LoanCalculatorService;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\InterestRate;
use App\Domain\ValueObject\LoanType;
use PHPUnit\Framework\TestCase;

final class LoanCalculatorServiceTest extends TestCase
{
    private LoanCalculatorService $calculator;
    
    protected function setUp(): void
    {
        $this->calculator = new LoanCalculatorService();
    }
    
    public function testCalculateMonthlyPayment(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $rate = InterestRate::fromPercentage(3.0);
        $duration = Duration::fromMonths(12);
        
        $monthlyPayment = $this->calculator->calculateMonthlyPayment($amount, $rate, $duration);
        
        $this->assertInstanceOf(Amount::class, $monthlyPayment);
        $this->assertGreaterThan(800.0, $monthlyPayment->getValue());
        $this->assertLessThan(900.0, $monthlyPayment->getValue());
    }
    
    public function testCalculateTotalAmount(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $rate = InterestRate::fromPercentage(3.0);
        $duration = Duration::fromMonths(12);
        
        $totalAmount = $this->calculator->calculateTotalAmount($amount, $rate, $duration);
        
        $this->assertInstanceOf(Amount::class, $totalAmount);
        $this->assertGreaterThan($amount->getValue(), $totalAmount->getValue());
        $this->assertLessThan(11000.0, $totalAmount->getValue());
    }
    
    public function testCalculateTotalInterest(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $rate = InterestRate::fromPercentage(3.0);
        $duration = Duration::fromMonths(12);
        
        $totalInterest = $this->calculator->calculateTotalInterest($amount, $rate, $duration);
        
        $this->assertInstanceOf(Amount::class, $totalInterest);
        $this->assertGreaterThan(0.0, $totalInterest->getValue());
        $this->assertLessThan(500.0, $totalInterest->getValue());
    }
    
    public function testGetEligibilityScoreWithGoodProfile(): void
    {
        $type = LoanType::PERSONAL;
        $amount = Amount::fromFloat(15000.0);
        $duration = Duration::fromMonths(24);
        $userIncome = 60000.0; // Bon revenu
        
        $score = $this->calculator->getEligibilityScore($type, $amount, $duration, $userIncome);
        
        $this->assertGreaterThanOrEqual(70, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
    
    public function testGetEligibilityScoreWithPoorProfile(): void
    {
        $type = LoanType::PERSONAL;
        $amount = Amount::fromFloat(50000.0);
        $duration = Duration::fromMonths(96); // Durée maximale
        $userIncome = 25000.0; // Revenu faible
        
        $score = $this->calculator->getEligibilityScore($type, $amount, $duration, $userIncome);
        
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThan(50, $score);
    }
    
    public function testGetEligibilityScoreForMortgage(): void
    {
        $type = LoanType::MORTGAGE;
        $amount = Amount::fromFloat(200000.0);
        $duration = Duration::fromMonths(240);
        $userIncome = 80000.0;
        
        $score = $this->calculator->getEligibilityScore($type, $amount, $duration, $userIncome);
        
        // Mortgage devrait avoir un bonus de points
        $this->assertGreaterThanOrEqual(60, $score);
    }
    
    public function testGetEligibilityScoreWithZeroIncome(): void
    {
        $type = LoanType::PERSONAL;
        $amount = Amount::fromFloat(10000.0);
        $duration = Duration::fromMonths(24);
        $userIncome = 0.0;
        
        $score = $this->calculator->getEligibilityScore($type, $amount, $duration, $userIncome);
        
        // Score très bas avec revenu zéro
        $this->assertLessThan(40, $score);
    }
    
    public function testScoreIsAlwaysBetween0And100(): void
    {
        $scenarios = [
            [LoanType::PERSONAL, 1000.0, 6, 100000.0],
            [LoanType::BUSINESS, 500000.0, 120, 30000.0],
            [LoanType::STUDENT, 50000.0, 120, 15000.0],
            [LoanType::MORTGAGE, 800000.0, 360, 120000.0],
        ];
        
        foreach ($scenarios as [$type, $amountValue, $months, $income]) {
            $amount = Amount::fromFloat($amountValue);
            $duration = Duration::fromMonths($months);
            
            $score = $this->calculator->getEligibilityScore($type, $amount, $duration, $income);
            
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }
}
