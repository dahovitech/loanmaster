<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\InterestRate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    public function testCreateValidAmount(): void
    {
        $amount = Amount::fromFloat(10000.0);
        
        $this->assertEquals(10000.0, $amount->getValue());
        $this->assertEquals('10 000,00 €', $amount->__toString());
    }
    
    public function testCannotCreateNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        
        Amount::fromFloat(-1000.0);
    }
    
    public function testCannotCreateZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');
        
        Amount::fromFloat(0.0);
    }
    
    public function testCannotCreateAmountExceedingLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount exceeds maximum limit of 1,000,000');
        
        Amount::fromFloat(1000001.0);
    }
    
    public function testAddAmounts(): void
    {
        $amount1 = Amount::fromFloat(5000.0);
        $amount2 = Amount::fromFloat(3000.0);
        
        $result = $amount1->add($amount2);
        
        $this->assertEquals(8000.0, $result->getValue());
    }
    
    public function testSubtractAmounts(): void
    {
        $amount1 = Amount::fromFloat(5000.0);
        $amount2 = Amount::fromFloat(3000.0);
        
        $result = $amount1->subtract($amount2);
        
        $this->assertEquals(2000.0, $result->getValue());
    }
    
    public function testMultiplyAmount(): void
    {
        $amount = Amount::fromFloat(1000.0);
        
        $result = $amount->multiply(1.5);
        
        $this->assertEquals(1500.0, $result->getValue());
    }
    
    public function testCalculateInterest(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $rate = InterestRate::fromPercentage(3.0);
        $duration = Duration::fromMonths(12);
        
        $monthlyPayment = $amount->calculateInterest($rate, $duration);
        
        // Vérification que le paiement mensuel est raisonnable
        $this->assertGreaterThan(800.0, $monthlyPayment->getValue());
        $this->assertLessThan(900.0, $monthlyPayment->getValue());
    }
    
    public function testCalculateInterestWithZeroRate(): void
    {
        $amount = Amount::fromFloat(12000.0);
        $rate = InterestRate::fromPercentage(0.0);
        $duration = Duration::fromMonths(12);
        
        $monthlyPayment = $amount->calculateInterest($rate, $duration);
        
        $this->assertEquals(1000.0, $monthlyPayment->getValue());
    }
    
    public function testEqualsMethod(): void
    {
        $amount1 = Amount::fromFloat(1000.0);
        $amount2 = Amount::fromFloat(1000.0);
        $amount3 = Amount::fromFloat(1001.0);
        
        $this->assertTrue($amount1->equals($amount2));
        $this->assertFalse($amount1->equals($amount3));
    }
    
    public function testEqualsWithSmallDifference(): void
    {
        $amount1 = Amount::fromFloat(1000.0);
        $amount2 = Amount::fromFloat(1000.005); // Différence de 0.005
        
        $this->assertTrue($amount1->equals($amount2));
    }
}
