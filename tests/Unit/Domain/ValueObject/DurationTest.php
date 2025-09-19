<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Duration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    public function testCreateValidDuration(): void
    {
        $duration = Duration::fromMonths(24);
        
        $this->assertEquals(24, $duration->getMonths());
        $this->assertEquals(2.0, $duration->getYears());
        $this->assertEquals('2 ans', $duration->__toString());
    }
    
    public function testCreateDurationFromYears(): void
    {
        $duration = Duration::fromYears(3);
        
        $this->assertEquals(36, $duration->getMonths());
        $this->assertEquals(3.0, $duration->getYears());
        $this->assertEquals('3 ans', $duration->__toString());
    }
    
    public function testCreateDurationWithNonExactYears(): void
    {
        $duration = Duration::fromMonths(18);
        
        $this->assertEquals(18, $duration->getMonths());
        $this->assertEquals(1.5, $duration->getYears());
        $this->assertEquals('18 mois', $duration->__toString());
    }
    
    public function testCannotCreateTooShortDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be at least 6 months');
        
        Duration::fromMonths(3);
    }
    
    public function testCannotCreateTooLongDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration cannot exceed 360 months (30 years)');
        
        Duration::fromMonths(365);
    }
    
    public function testMinimumValidDuration(): void
    {
        $duration = Duration::fromMonths(6);
        
        $this->assertEquals(6, $duration->getMonths());
        $this->assertEquals(0.5, $duration->getYears());
    }
    
    public function testMaximumValidDuration(): void
    {
        $duration = Duration::fromMonths(360);
        
        $this->assertEquals(360, $duration->getMonths());
        $this->assertEquals(30.0, $duration->getYears());
        $this->assertEquals('30 ans', $duration->__toString());
    }
    
    public function testEqualsMethod(): void
    {
        $duration1 = Duration::fromMonths(24);
        $duration2 = Duration::fromMonths(24);
        $duration3 = Duration::fromMonths(36);
        
        $this->assertTrue($duration1->equals($duration2));
        $this->assertFalse($duration1->equals($duration3));
    }
    
    public function testSingleYearLabel(): void
    {
        $duration = Duration::fromMonths(12);
        
        $this->assertEquals('1 an', $duration->__toString());
    }
    
    public function testMultipleYearsLabel(): void
    {
        $duration = Duration::fromMonths(24);
        
        $this->assertEquals('2 ans', $duration->__toString());
    }
}
