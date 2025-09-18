<?php

declare(strict_types=1);

namespace App\Domain\Event;

use DateTimeImmutable;
use JsonSerializable;

interface DomainEventInterface extends JsonSerializable
{
    public function getOccurredOn(): DateTimeImmutable;
    
    public function getEventName(): string;
    
    public function getPayload(): array;
    
    public function getAggregateId(): string;
    
    public function getVersion(): int;
}
