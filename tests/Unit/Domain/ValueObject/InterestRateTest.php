<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\InterestRate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InterestRateTest extends TestCase
{
    public function testCreateFromPercentage(): void
    {
        $rate = InterestRate::fromPercentage(3.5);
        
        $this->assertEquals(0.035, $rate->getAnnualRate());
        $this->assertEquals(3.5, $rate->getPercentage());
        $this->assertEquals(0.035 / 12, $rate->getMonthlyRate());
        $this->assertEquals('3,50%', $rate->__toString());
    }
    
    public function testCreateFromDecimal(): void
    {
        $rate = InterestRate::fromDecimal(0.025);
        
        $this->assertEquals(0.025, $rate->getAnnualRate());
        $this->assertEquals(2.5, $rate->getPercentage());
        $this->assertEquals(0.025 / 12, $rate->getMonthlyRate());
    }
    
    public function testCannotCreateNegativeRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Interest rate cannot be negative');
        
        InterestRate::fromPercentage(-1.0);
    }
    
    public function testCannotCreateExcessiveRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Interest rate cannot exceed 50%');
        
        InterestRate::fromPercentage(51.0);
    }
    
    public function testZeroRate(): void
    {
        $rate = InterestRate::fromPercentage(0.0);
        
        $this->assertEquals(0.0, $rate->getAnnualRate());
        $this->assertEquals(0.0, $rate->getPercentage());
        $this->assertEquals(0.0, $rate->getMonthlyRate());
        $this->assertEquals('0,00%', $rate->__toString());
    }
    
    public function testMaximumValidRate(): void
    {
        $rate = InterestRate::fromPercentage(50.0);
        
        $this->assertEquals(0.5, $rate->getAnnualRate());
        $this->assertEquals(50.0, $rate->getPercentage());
    }
    
    public function testEqualsMethod(): void
    {
        $rate1 = InterestRate::fromPercentage(3.5);
        $rate2 = InterestRate::fromDecimal(0.035);
        $rate3 = InterestRate::fromPercentage(4.0);
        
        $this->assertTrue($rate1->equals($rate2));
        $this->assertFalse($rate1->equals($rate3));
    }
    
    public function testEqualsWithSmallDifference(): void
    {
        $rate1 = InterestRate::fromPercentage(3.5);
        $rate2 = InterestRate::fromDecimal(0.03500001); // Très petite différence
        
        $this->assertTrue($rate1->equals($rate2));
    }
}
