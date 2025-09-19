<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Infrastructure\EventSourcing\StoredDomainEvent;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

/**
 * Classe de base pour tous les événements du domaine
 * Support complet pour l'Event Sourcing
 */
abstract class AbstractDomainEvent implements StoredDomainEvent
{
    protected UuidInterface $aggregateId;
    protected DateTimeImmutable $occurredOn;
    protected int $version;
    protected array $payload;
    protected array $storedMetadata = [];

    public function __construct(UuidInterface $aggregateId, array $payload = [], int $version = 1)
    {
        $this->aggregateId = $aggregateId;
        $this->payload = $payload;
        $this->version = $version;
        $this->occurredOn = new DateTimeImmutable();
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getEventName(): string
    {
        $className = get_class($this);
        return substr($className, strrpos($className, '\\') + 1);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId->toString();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function jsonSerialize(): array
    {
        return [
            'aggregateId' => $this->aggregateId->toString(),
            'eventName' => $this->getEventName(),
            'payload' => $this->payload,
            'version' => $this->version,
            'occurredOn' => $this->occurredOn->format(DATE_ATOM)
        ];
    }

    public function setStoredMetadata(array $metadata): void
    {
        $this->storedMetadata = $metadata;
    }

    public function getStoredMetadata(): array
    {
        return $this->storedMetadata;
    }

    /**
     * Crée un événement depuis un tableau de données
     */
    public static function fromArray(array $data): static
    {
        $event = new static(
            \Ramsey\Uuid\Uuid::fromString($data['aggregateId']),
            $data['payload'] ?? [],
            $data['version'] ?? 1
        );
        
        if (isset($data['occurredOn'])) {
            $event->occurredOn = new DateTimeImmutable($data['occurredOn']);
        }
        
        return $event;
    }
}
