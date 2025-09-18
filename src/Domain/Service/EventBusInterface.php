<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Event\DomainEventInterface;

interface EventBusInterface
{
    public function dispatch(DomainEventInterface $event): void;
    
    /**
     * @param DomainEventInterface[] $events
     */
    public function dispatchEvents(array $events): void;
}
