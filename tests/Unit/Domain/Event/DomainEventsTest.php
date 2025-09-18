<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Event;

use App\Domain\Event\LoanApplicationCreated;
use App\Domain\Event\LoanStatusChanged;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class DomainEventsTest extends TestCase
{
    public function testLoanApplicationCreatedEvent(): void
    {
        $loanId = LoanId::generate();
        $userId = UserId::generate();
        $loanNumber = 'DOC000123456';
        
        $event = new LoanApplicationCreated($loanId, $userId, $loanNumber);
        
        $this->assertEquals($loanId, $event->getLoanId());
        $this->assertEquals($userId, $event->getUserId());
        $this->assertEquals($loanNumber, $event->getLoanNumber());
        $this->assertEquals('loan.application.created', $event->getEventName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredOn());
        
        $payload = $event->getPayload();
        $this->assertArrayHasKey('loanId', $payload);
        $this->assertArrayHasKey('userId', $payload);
        $this->assertArrayHasKey('loanNumber', $payload);
        $this->assertArrayHasKey('occurredOn', $payload);
        
        $this->assertEquals($loanId->toString(), $payload['loanId']);
        $this->assertEquals($userId->toString(), $payload['userId']);
        $this->assertEquals($loanNumber, $payload['loanNumber']);
    }
    
    public function testLoanStatusChangedEvent(): void
    {
        $loanId = LoanId::generate();
        $previousStatus = LoanStatus::PENDING;
        $newStatus = LoanStatus::APPROVED;
        
        $event = new LoanStatusChanged($loanId, $previousStatus, $newStatus);
        
        $this->assertEquals($loanId, $event->getLoanId());
        $this->assertEquals($previousStatus, $event->getPreviousStatus());
        $this->assertEquals($newStatus, $event->getNewStatus());
        $this->assertEquals('loan.status.changed', $event->getEventName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredOn());
        
        $payload = $event->getPayload();
        $this->assertArrayHasKey('loanId', $payload);
        $this->assertArrayHasKey('previousStatus', $payload);
        $this->assertArrayHasKey('newStatus', $payload);
        $this->assertArrayHasKey('occurredOn', $payload);
        
        $this->assertEquals($loanId->toString(), $payload['loanId']);
        $this->assertEquals($previousStatus->value, $payload['previousStatus']);
        $this->assertEquals($newStatus->value, $payload['newStatus']);
    }
}
