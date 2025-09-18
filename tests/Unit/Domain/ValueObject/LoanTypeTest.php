<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\LoanType;
use PHPUnit\Framework\TestCase;

final class LoanTypeTest extends TestCase
{
    public function testAllTypeLabels(): void
    {
        $expectedLabels = [
            LoanType::PERSONAL => 'Prêt personnel',
            LoanType::AUTO => 'Crédit auto',
            LoanType::MORTGAGE => 'Crédit immobilier',
            LoanType::BUSINESS => 'Crédit professionnel',
            LoanType::STUDENT => 'Prêt étudiant',
            LoanType::RENOVATION => 'Crédit travaux',
        ];
        
        foreach ($expectedLabels as $type => $expectedLabel) {
            $this->assertEquals($expectedLabel, $type->getLabel());
        }
    }
    
    public function testMaxAmountLimits(): void
    {
        $expectedLimits = [
            LoanType::PERSONAL => 75000.0,
            LoanType::AUTO => 80000.0,
            LoanType::MORTGAGE => 1000000.0,
            LoanType::BUSINESS => 500000.0,
            LoanType::STUDENT => 50000.0,
            LoanType::RENOVATION => 100000.0,
        ];
        
        foreach ($expectedLimits as $type => $expectedLimit) {
            $this->assertEquals($expectedLimit, $type->getMaxAmount());
        }
    }
    
    public function testMaxDurationLimits(): void
    {
        $expectedDurations = [
            LoanType::PERSONAL => 96,    // 8 ans
            LoanType::AUTO => 84,        // 7 ans
            LoanType::MORTGAGE => 360,   // 30 ans
            LoanType::BUSINESS => 120,   // 10 ans
            LoanType::STUDENT => 120,    // 10 ans
            LoanType::RENOVATION => 144, // 12 ans
        ];
        
        foreach ($expectedDurations as $type => $expectedDuration) {
            $this->assertEquals($expectedDuration, $type->getMaxDurationMonths());
        }
    }
    
    public function testBaseInterestRates(): void
    {
        $expectedRates = [
            LoanType::PERSONAL => 0.035,   // 3.5%
            LoanType::AUTO => 0.025,       // 2.5%
            LoanType::MORTGAGE => 0.015,   // 1.5%
            LoanType::BUSINESS => 0.04,    // 4%
            LoanType::STUDENT => 0.01,     // 1%
            LoanType::RENOVATION => 0.03,  // 3%
        ];
        
        foreach ($expectedRates as $type => $expectedRate) {
            $this->assertEquals($expectedRate, $type->getBaseInterestRate());
        }
    }
    
    public function testMortgageHasHighestLimit(): void
    {
        $mortgageLimit = LoanType::MORTGAGE->getMaxAmount();
        
        foreach (LoanType::cases() as $type) {
            if ($type !== LoanType::MORTGAGE) {
                $this->assertLessThan($mortgageLimit, $type->getMaxAmount());
            }
        }
    }
    
    public function testMortgageHasLongestDuration(): void
    {
        $mortgageDuration = LoanType::MORTGAGE->getMaxDurationMonths();
        
        foreach (LoanType::cases() as $type) {
            if ($type !== LoanType::MORTGAGE) {
                $this->assertLessThanOrEqual($mortgageDuration, $type->getMaxDurationMonths());
            }
        }
    }
    
    public function testStudentHasLowestRate(): void
    {
        $studentRate = LoanType::STUDENT->getBaseInterestRate();
        
        foreach (LoanType::cases() as $type) {
            if ($type !== LoanType::STUDENT) {
                $this->assertGreaterThanOrEqual($studentRate, $type->getBaseInterestRate());
            }
        }
    }
}
