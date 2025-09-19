<?php

namespace App\Infrastructure\EventSourcing;

use App\Domain\Event\DomainEventInterface;

/**
 * Interface pour les événements qui peuvent être stockés et reconstitués
 */
interface StoredDomainEvent extends DomainEventInterface
{
    /**
     * Crée un événement depuis un tableau de données
     */
    public static function fromArray(array $data): self;

    /**
     * Définit les métadonnées de stockage
     */
    public function setStoredMetadata(array $metadata): void;

    /**
     * Récupère les métadonnées de stockage
     */
    public function getStoredMetadata(): array;
}
