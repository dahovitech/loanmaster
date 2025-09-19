<?php

namespace App\Infrastructure\EventSourcing;

use App\Domain\Event\DomainEventInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Classe de base pour les agrégats avec Event Sourcing
 */
abstract class AggregateRoot
{
    protected UuidInterface $id;
    protected int $version = 0;
    protected array $uncommittedEvents = [];
    protected array $storedMetadata = [];

    public function __construct(UuidInterface $id)
    {
        $this->id = $id;
    }

    /**
     * Récupère l'ID de l'agrégat
     */
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    /**
     * Récupère la version actuelle
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Récupère les événements non commités
     */
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }

    /**
     * Marque les événements comme commités
     */
    public function markEventsAsCommitted(): void
    {
        $this->uncommittedEvents = [];
    }

    /**
     * Reconstitue l'agrégat depuis une liste d'événements
     */
    public static function reconstituteFromHistory(UuidInterface $id, array $events): static
    {
        $aggregate = new static($id);
        
        foreach ($events as $event) {
            $aggregate->applyEvent($event, false);
            $aggregate->version++;
        }
        
        return $aggregate;
    }

    /**
     * Applique un événement à l'agrégat
     */
    protected function applyEvent(DomainEventInterface $event, bool $isNew = true): void
    {
        // Génère le nom de la méthode à partir du nom de l'événement
        $eventClass = get_class($event);
        $eventName = substr($eventClass, strrpos($eventClass, '\\') + 1);
        $methodName = 'apply' . $eventName;
        
        if (method_exists($this, $methodName)) {
            $this->$methodName($event);
        }
        
        if ($isNew) {
            $this->uncommittedEvents[] = $event;
        }
    }

    /**
     * Enregistre un nouvel événement
     */
    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->applyEvent($event, true);
    }

    /**
     * Prend un snapshot de l'état actuel
     */
    abstract public function takeSnapshot(): array;

    /**
     * Restaure l'état depuis un snapshot
     */
    abstract public function restoreFromSnapshot(array $snapshot): void;
}
