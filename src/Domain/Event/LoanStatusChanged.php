<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use DateTimeImmutable;
use JsonSerializable;

final readonly class LoanStatusChanged implements DomainEventInterface, JsonSerializable
{
    public function __construct(
        private LoanId $loanId,
        private LoanStatus $previousStatus,
        private LoanStatus $newStatus,
        private DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {}
    
    public function getLoanId(): LoanId
    {
        return $this->loanId;
    }
    
    public function getPreviousStatus(): LoanStatus
    {
        return $this->previousStatus;
    }
    
    public function getNewStatus(): LoanStatus
    {
        return $this->newStatus;
    }
    
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
    
    public function getEventName(): string
    {
        return 'loan.status.changed';
    }
    
    public function getPayload(): array
    {
        return [
            'loanId' => $this->loanId->toString(),
            'previousStatus' => $this->previousStatus->value,
            'newStatus' => $this->newStatus->value,
            'occurredOn' => $this->occurredOn->format('c'),
        ];
    }

    public function getAggregateId(): string
    {
        return $this->loanId->toString();
    }

    public function getVersion(): int
    {
        return 1;
    }

    public function jsonSerialize(): array
    {
        return [
            'eventName' => $this->getEventName(),
            'aggregateId' => $this->getAggregateId(),
            'version' => $this->getVersion(),
            'occurredOn' => $this->occurredOn->format('c'),
            'payload' => $this->getPayload()
        ];
    }
}
