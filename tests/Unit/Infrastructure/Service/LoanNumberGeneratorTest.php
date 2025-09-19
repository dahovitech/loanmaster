<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Service;

use App\Infrastructure\Service\LoanNumberGenerator;
use PHPUnit\Framework\TestCase;

final class LoanNumberGeneratorTest extends TestCase
{
    private LoanNumberGenerator $generator;
    
    protected function setUp(): void
    {
        $this->generator = new LoanNumberGenerator();
    }
    
    public function testGenerateReturnsValidFormat(): void
    {
        $number = $this->generator->generate();
        
        $this->assertStringStartsWith('DOC', $number);
        $this->assertEquals(17, strlen($number)); // DOC + 10 digits timestamp + 4 digits random
        $this->assertMatchesRegularExpression('/^DOC\d{14}$/', $number);
    }
    
    public function testGenerateReturnsUniqueNumbers(): void
    {
        $numbers = [];
        
        for ($i = 0; $i < 100; $i++) {
            $number = $this->generator->generate();
            $this->assertNotContains($number, $numbers, 'Generated number should be unique');
            $numbers[] = $number;
        }
    }
    
    public function testGeneratedNumbersAreSequential(): void
    {
        $number1 = $this->generator->generate();
        usleep(1000); // Attendre 1ms
        $number2 = $this->generator->generate();
        
        // Les timestamps devraient être égaux ou le second légèrement supérieur
        $timestamp1 = substr($number1, 3, 10);
        $timestamp2 = substr($number2, 3, 10);
        
        $this->assertGreaterThanOrEqual($timestamp1, $timestamp2);
    }
}
