<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\LoanStatus;
use PHPUnit\Framework\TestCase;

final class LoanStatusTest extends TestCase
{
    public function testAllStatusLabels(): void
    {
        $expectedLabels = [
            LoanStatus::PENDING => 'En attente',
            LoanStatus::UNDER_REVIEW => 'En cours d\'examen',
            LoanStatus::APPROVED => 'Approuvé',
            LoanStatus::REJECTED => 'Rejeté',
            LoanStatus::FUNDED => 'Financé',
            LoanStatus::ACTIVE => 'Actif',
            LoanStatus::COMPLETED => 'Terminé',
            LoanStatus::DEFAULTED => 'En défaut',
            LoanStatus::CANCELLED => 'Annulé',
        ];
        
        foreach ($expectedLabels as $status => $expectedLabel) {
            $this->assertEquals($expectedLabel, $status->getLabel());
        }
    }
    
    public function testValidTransitions(): void
    {
        $validTransitions = [
            [LoanStatus::PENDING, LoanStatus::UNDER_REVIEW],
            [LoanStatus::UNDER_REVIEW, LoanStatus::APPROVED],
            [LoanStatus::UNDER_REVIEW, LoanStatus::REJECTED],
            [LoanStatus::APPROVED, LoanStatus::FUNDED],
            [LoanStatus::FUNDED, LoanStatus::ACTIVE],
            [LoanStatus::ACTIVE, LoanStatus::COMPLETED],
            [LoanStatus::ACTIVE, LoanStatus::DEFAULTED],
            [LoanStatus::PENDING, LoanStatus::CANCELLED],
            [LoanStatus::UNDER_REVIEW, LoanStatus::CANCELLED],
        ];
        
        foreach ($validTransitions as [$from, $to]) {
            $this->assertTrue(
                $from->canTransitionTo($to),
                sprintf('Should be able to transition from %s to %s', $from->value, $to->value)
            );
        }
    }
    
    public function testInvalidTransitions(): void
    {
        $invalidTransitions = [
            [LoanStatus::PENDING, LoanStatus::APPROVED],
            [LoanStatus::APPROVED, LoanStatus::UNDER_REVIEW],
            [LoanStatus::COMPLETED, LoanStatus::ACTIVE],
            [LoanStatus::REJECTED, LoanStatus::APPROVED],
            [LoanStatus::DEFAULTED, LoanStatus::COMPLETED],
        ];
        
        foreach ($invalidTransitions as [$from, $to]) {
            $this->assertFalse(
                $from->canTransitionTo($to),
                sprintf('Should NOT be able to transition from %s to %s', $from->value, $to->value)
            );
        }
    }
    
    public function testActiveStatuses(): void
    {
        $activeStatuses = [
            LoanStatus::PENDING,
            LoanStatus::UNDER_REVIEW,
            LoanStatus::APPROVED,
            LoanStatus::FUNDED,
            LoanStatus::ACTIVE,
        ];
        
        foreach ($activeStatuses as $status) {
            $this->assertTrue($status->isActive(), $status->value . ' should be active');
        }
    }
    
    public function testInactiveStatuses(): void
    {
        $inactiveStatuses = [
            LoanStatus::COMPLETED,
            LoanStatus::REJECTED,
            LoanStatus::DEFAULTED,
            LoanStatus::CANCELLED,
        ];
        
        foreach ($inactiveStatuses as $status) {
            $this->assertFalse($status->isActive(), $status->value . ' should not be active');
        }
    }
    
    public function testFinalStatuses(): void
    {
        $finalStatuses = [
            LoanStatus::COMPLETED,
            LoanStatus::REJECTED,
            LoanStatus::DEFAULTED,
            LoanStatus::CANCELLED,
        ];
        
        foreach ($finalStatuses as $status) {
            $this->assertTrue($status->isFinal(), $status->value . ' should be final');
        }
    }
    
    public function testNonFinalStatuses(): void
    {
        $nonFinalStatuses = [
            LoanStatus::PENDING,
            LoanStatus::UNDER_REVIEW,
            LoanStatus::APPROVED,
            LoanStatus::FUNDED,
            LoanStatus::ACTIVE,
        ];
        
        foreach ($nonFinalStatuses as $status) {
            $this->assertFalse($status->isFinal(), $status->value . ' should not be final');
        }
    }
}
