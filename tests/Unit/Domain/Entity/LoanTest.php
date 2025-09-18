<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Loan;
use App\Domain\Event\LoanApplicationCreated;
use App\Domain\Event\LoanStatusChanged;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use App\Domain\ValueObject\LoanType;
use App\Domain\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private LoanId $loanId;
    private UserId $userId;
    
    protected function setUp(): void
    {
        $this->loanId = LoanId::generate();
        $this->userId = UserId::generate();
    }
    
    public function testCreateLoanWithValidData(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $duration = Duration::fromMonths(24);
        
        $loan = Loan::create(
            $this->loanId,
            $this->userId,
            'DOC000123456',
            LoanType::PERSONAL,
            $amount,
            $duration,
            'Purchase a new car'
        );
        
        $this->assertEquals($this->loanId, $loan->getId());
        $this->assertEquals($this->userId, $loan->getUserId());
        $this->assertEquals('DOC000123456', $loan->getNumber());
        $this->assertEquals(LoanType::PERSONAL, $loan->getType());
        $this->assertEquals($amount, $loan->getAmount());
        $this->assertEquals($duration, $loan->getDuration());
        $this->assertEquals(LoanStatus::PENDING, $loan->getStatus());
        $this->assertEquals('Purchase a new car', $loan->getProjectDescription());
        $this->assertInstanceOf(\DateTimeImmutable::class, $loan->getCreatedAt());
        $this->assertNull($loan->getApprovedAt());
        $this->assertNull($loan->getFundedAt());
    }
    
    public function testCreateLoanGeneratesEvent(): void
    {
        $amount = Amount::fromFloat(10000.0);
        $duration = Duration::fromMonths(24);
        
        $loan = Loan::create(
            $this->loanId,
            $this->userId,
            'DOC000123456',
            LoanType::PERSONAL,
            $amount,
            $duration
        );
        
        $events = $loan->getUncommittedEvents();
        
        $this->assertCount(1, $events);
        $this->assertInstanceOf(LoanApplicationCreated::class, $events[0]);
        
        $event = $events[0];
        $this->assertEquals($this->loanId, $event->getLoanId());
        $this->assertEquals($this->userId, $event->getUserId());
        $this->assertEquals('DOC000123456', $event->getLoanNumber());
    }
    
    public function testCannotCreateLoanWithExcessiveAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount 100000.00 exceeds maximum for Prêt personnel loans (75000.00)');
        
        Loan::create(
            $this->loanId,
            $this->userId,
            'DOC000123456',
            LoanType::PERSONAL,
            Amount::fromFloat(100000.0), // Dépasse la limite pour personal (75k)
            Duration::fromMonths(24)
        );
    }
    
    public function testCannotCreateLoanWithExcessiveDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration 120 months exceeds maximum for Prêt personnel loans (96 months)');
        
        Loan::create(
            $this->loanId,
            $this->userId,
            'DOC000123456',
            LoanType::PERSONAL,
            Amount::fromFloat(50000.0),
            Duration::fromMonths(120) // Dépasse la limite pour personal (96 mois)
        );
    }
    
    public function testApproveLoan(): void
    {
        $loan = $this->createValidLoan();
        
        $loan->approve();
        
        $this->assertEquals(LoanStatus::APPROVED, $loan->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $loan->getApprovedAt());
        
        $events = $loan->getUncommittedEvents();
        $this->assertCount(2, $events); // Creation + Status change
        $this->assertInstanceOf(LoanStatusChanged::class, $events[1]);
    }
    
    public function testRejectLoan(): void
    {
        $loan = $this->createValidLoan();
        
        $loan->reject('Insufficient income');
        
        $this->assertEquals(LoanStatus::REJECTED, $loan->getStatus());
        
        $events = $loan->getUncommittedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(LoanStatusChanged::class, $events[1]);
    }
    
    public function testFundLoan(): void
    {
        $loan = $this->createValidLoan();
        $loan->approve();
        
        $loan->fund();
        
        $this->assertEquals(LoanStatus::FUNDED, $loan->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $loan->getFundedAt());
    }
    
    public function testActivateLoan(): void
    {
        $loan = $this->createValidLoan();
        $loan->approve();
        $loan->fund();
        
        $loan->activate();
        
        $this->assertEquals(LoanStatus::ACTIVE, $loan->getStatus());
    }
    
    public function testCannotActivateNonFundedLoan(): void
    {
        $loan = $this->createValidLoan();
        $loan->approve();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Loan must be funded before activation');
        
        $loan->activate();
    }
    
    public function testCompleteLoan(): void
    {
        $loan = $this->createActiveLoan();
        
        $loan->complete();
        
        $this->assertEquals(LoanStatus::COMPLETED, $loan->getStatus());
    }
    
    public function testMarkAsDefault(): void
    {
        $loan = $this->createActiveLoan();
        
        $loan->markAsDefault();
        
        $this->assertEquals(LoanStatus::DEFAULTED, $loan->getStatus());
    }
    
    public function testCancelPendingLoan(): void
    {
        $loan = $this->createValidLoan();
        
        $loan->cancel();
        
        $this->assertEquals(LoanStatus::CANCELLED, $loan->getStatus());
    }
    
    public function testCannotCancelFinalizedLoan(): void
    {
        $loan = $this->createActiveLoan();
        $loan->complete();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel a finalized loan');
        
        $loan->cancel();
    }
    
    public function testInvalidStatusTransition(): void
    {
        $loan = $this->createValidLoan();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition from En attente to Financé');
        
        $loan->fund(); // Cannot fund without approval
    }
    
    public function testCalculateMonthlyPayment(): void
    {
        $loan = $this->createValidLoan();
        
        $monthlyPayment = $loan->calculateMonthlyPayment();
        
        $this->assertInstanceOf(Amount::class, $monthlyPayment);
        $this->assertGreaterThan(400.0, $monthlyPayment->getValue());
        $this->assertLessThan(500.0, $monthlyPayment->getValue());
    }
    
    public function testCalculateTotalAmount(): void
    {
        $loan = $this->createValidLoan();
        
        $totalAmount = $loan->calculateTotalAmount();
        
        $this->assertInstanceOf(Amount::class, $totalAmount);
        $this->assertGreaterThan($loan->getAmount()->getValue(), $totalAmount->getValue());
    }
    
    public function testCalculateTotalInterest(): void
    {
        $loan = $this->createValidLoan();
        
        $totalInterest = $loan->calculateTotalInterest();
        
        $this->assertInstanceOf(Amount::class, $totalInterest);
        $this->assertGreaterThan(0.0, $totalInterest->getValue());
    }
    
    public function testMarkEventsAsCommitted(): void
    {
        $loan = $this->createValidLoan();
        
        $this->assertCount(1, $loan->getUncommittedEvents());
        
        $loan->markEventsAsCommitted();
        
        $this->assertCount(0, $loan->getUncommittedEvents());
    }
    
    private function createValidLoan(): Loan
    {
        return Loan::create(
            $this->loanId,
            $this->userId,
            'DOC000123456',
            LoanType::PERSONAL,
            Amount::fromFloat(10000.0),
            Duration::fromMonths(24),
            'Test loan'
        );
    }
    
    private function createActiveLoan(): Loan
    {
        $loan = $this->createValidLoan();
        $loan->approve();
        $loan->fund();
        $loan->activate();
        
        return $loan;
    }
}
