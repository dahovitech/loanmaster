<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use JsonSerializable;

final readonly class LoanApplicationCreated implements DomainEventInterface, JsonSerializable
{
    public function __construct(
        private LoanId $loanId,
        private UserId $userId,
        private string $loanNumber,
        private DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {}
    
    public function getLoanId(): LoanId
    {
        return $this->loanId;
    }
    
    public function getUserId(): UserId
    {
        return $this->userId;
    }
    
    public function getLoanNumber(): string
    {
        return $this->loanNumber;
    }
    
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
    
    public function getEventName(): string
    {
        return 'loan.application.created';
    }
    
    public function getPayload(): array
    {
        return [
            'loanId' => $this->loanId->toString(),
            'userId' => $this->userId->toString(),
            'loanNumber' => $this->loanNumber,
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
