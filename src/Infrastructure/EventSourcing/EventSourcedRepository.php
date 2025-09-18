<?php

namespace App\Infrastructure\EventSourcing;

use App\Domain\Event\DomainEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Repository de base pour les agrégats avec Event Sourcing
 */
abstract class EventSourcedRepository
{
    protected EventStore $eventStore;
    protected string $aggregateClass;

    public function __construct(EventStore $eventStore, string $aggregateClass)
    {
        $this->eventStore = $eventStore;
        $this->aggregateClass = $aggregateClass;
    }

    /**
     * Sauvegarde un agrégat
     */
    public function save(AggregateRoot $aggregate): void
    {
        $expectedVersion = $aggregate->getVersion();
        
        foreach ($aggregate->getUncommittedEvents() as $event) {
            $this->eventStore->append(
                $aggregate->getId()->toString(),
                $event,
                $expectedVersion
            );
            $expectedVersion++;
        }
        
        $aggregate->markEventsAsCommitted();
    }

    /**
     * Charge un agrégat par son ID
     */
    public function load(UuidInterface $id): ?AggregateRoot
    {
        $events = $this->eventStore->getAggregateEvents($id->toString());
        
        if (empty($events)) {
            return null;
        }
        
        $aggregateClass = $this->aggregateClass;
        return $aggregateClass::reconstituteFromHistory($id, $events);
    }

    /**
     * Vérifie si un agrégat existe
     */
    public function exists(UuidInterface $id): bool
    {
        return $this->eventStore->getAggregateVersion($id->toString()) > 0;
    }
}
