<?php

namespace App\Infrastructure\EventSourcing\Command;

/**
 * Interface pour toutes les commandes Event Sourcing
 */
interface CommandInterface
{
    /**
     * ID unique de la commande
     */
    public function getCommandId(): string;
    
    /**
     * ID de l'utilisateur qui exécute la commande
     */
    public function getUserId(): ?string;
    
    /**
     * Adresse IP de l'utilisateur
     */
    public function getIpAddress(): ?string;
    
    /**
     * ID de corrélation pour tracer les opérations
     */
    public function getCorrelationId(): ?string;
    
    /**
     * Timestamp de création de la commande
     */
    public function getCreatedAt(): \DateTimeImmutable;
}
