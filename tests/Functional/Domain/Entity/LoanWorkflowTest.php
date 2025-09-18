<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\Entity;

use App\Domain\Entity\Loan;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanType;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Tests fonctionnels pour vérifier les scénarios métier complets
 */
final class LoanWorkflowTest extends TestCase
{
    public function testCompletePersonalLoanWorkflow(): void
    {
        // Création d'un prêt personnel
        $loan = Loan::create(
            LoanId::generate(),
            UserId::generate(),
            'DOC000123456',
            LoanType::PERSONAL,
            Amount::fromFloat(15000.0),
            Duration::fromMonths(36),
            'Achat d\'une voiture'
        );
        
        // Vérification de l'état initial
        $this->assertEquals('En attente', $loan->getStatus()->getLabel());
        $this->assertCount(1, $loan->getUncommittedEvents());
        
        // Workflow d'approbation
        $loan->approve();
        $this->assertEquals('Approuvé', $loan->getStatus()->getLabel());
        $this->assertNotNull($loan->getApprovedAt());
        
        // Financement
        $loan->fund();
        $this->assertEquals('Financé', $loan->getStatus()->getLabel());
        $this->assertNotNull($loan->getFundedAt());
        
        // Activation
        $loan->activate();
        $this->assertEquals('Actif', $loan->getStatus()->getLabel());
        
        // Calculs financiers
        $monthlyPayment = $loan->calculateMonthlyPayment();
        $totalAmount = $loan->calculateTotalAmount();
        $totalInterest = $loan->calculateTotalInterest();
        
        $this->assertGreaterThan(400.0, $monthlyPayment->getValue());
        $this->assertLessThan(500.0, $monthlyPayment->getValue());
        $this->assertGreaterThan(15000.0, $totalAmount->getValue());
        $this->assertGreaterThan(0.0, $totalInterest->getValue());
        
        // Complétion
        $loan->complete();
        $this->assertEquals('Terminé', $loan->getStatus()->getLabel());
        $this->assertTrue($loan->getStatus()->isFinal());
        
        // Vérification des événements générés
        $events = $loan->getUncommittedEvents();
        $this->assertCount(6, $events); // Creation + 5 status changes
    }
    
    public function testMortgageLoanWorkflow(): void
    {
        // Création d'un prêt immobilier
        $loan = Loan::create(
            LoanId::generate(),
            UserId::generate(),
            'DOC000789012',
            LoanType::MORTGAGE,
            Amount::fromFloat(250000.0),
            Duration::fromMonths(300), // 25 ans
            'Achat résidence principale'
        );
        
        $this->assertEquals(LoanType::MORTGAGE, $loan->getType());
        $this->assertEquals(250000.0, $loan->getAmount()->getValue());
        $this->assertEquals(300, $loan->getDuration()->getMonths());
        
        // Les calculs pour un mortgage devraient être différents
        $monthlyPayment = $loan->calculateMonthlyPayment();
        $this->assertGreaterThan(1000.0, $monthlyPayment->getValue());
        $this->assertLessThan(1500.0, $monthlyPayment->getValue());
    }
    
    public function testLoanRejectionWorkflow(): void
    {
        $loan = Loan::create(
            LoanId::generate(),
            UserId::generate(),
            'DOC000345678',
            LoanType::BUSINESS,
            Amount::fromFloat(50000.0),
            Duration::fromMonths(60),
            'Développement entreprise'
        );
        
        // Rejet du prêt
        $loan->reject('Revenus insuffisants');
        
        $this->assertEquals('Rejeté', $loan->getStatus()->getLabel());
        $this->assertTrue($loan->getStatus()->isFinal());
        $this->assertFalse($loan->getStatus()->isActive());
        
        // Un prêt rejeté ne peut plus changer d'état
        $this->assertFalse($loan->getStatus()->canTransitionTo($loan->getStatus()));
    }
    
    public function testLoanDefaultWorkflow(): void
    {
        $loan = Loan::create(
            LoanId::generate(),
            UserId::generate(),
            'DOC000456789',
            LoanType::AUTO,
            Amount::fromFloat(25000.0),
            Duration::fromMonths(48)
        );
        
        // Workflow complet jusqu'au défaut
        $loan->approve();
        $loan->fund();
        $loan->activate();
        
        // Défaut de paiement
        $loan->markAsDefault();
        
        $this->assertEquals('En défaut', $loan->getStatus()->getLabel());
        $this->assertTrue($loan->getStatus()->isFinal());
        $this->assertFalse($loan->getStatus()->isActive());
    }
    
    public function testLoanCancellationWorkflow(): void
    {
        $loan = Loan::create(
            LoanId::generate(),
            UserId::generate(),
            'DOC000567890',
            LoanType::STUDENT,
            Amount::fromFloat(20000.0),
            Duration::fromMonths(72)
        );
        
        // Annulation en attente
        $loan->cancel();
        
        $this->assertEquals('Annulé', $loan->getStatus()->getLabel());
        $this->assertTrue($loan->getStatus()->isFinal());
        $this->assertFalse($loan->getStatus()->isActive());
    }
}
