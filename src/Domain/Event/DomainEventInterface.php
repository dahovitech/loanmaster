<?php

declare(strict_types=1);

namespace App\Domain\Event;

use DateTimeImmutable;

interface DomainEventInterface
{
    public function getOccurredOn(): DateTimeImmutable;
    
    public function getEventName(): string;
    
    public function getPayload(): array;
}
