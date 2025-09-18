<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Event\DomainEventInterface;
use App\Domain\Service\EventBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBusInterface
{
    public function __construct(
        private MessageBusInterface $eventBus
    ) {}

    public function dispatch(DomainEventInterface $event): void
    {
        $this->eventBus->dispatch($event);
    }

    public function dispatchEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}
