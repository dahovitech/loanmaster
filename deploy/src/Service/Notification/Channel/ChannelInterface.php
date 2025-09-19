<?php

namespace App\Service\Notification\Channel;

use App\Service\Notification\NotificationResult;

/**
 * Interface pour tous les canaux de notification
 */
interface ChannelInterface
{
    /**
     * Envoie une notification via ce canal
     */
    public function send(string $type, array $recipients, array $data, array $options = []): NotificationResult;

    /**
     * Vérifie si le canal est disponible
     */
    public function isAvailable(): bool;

    /**
     * Récupère le nom du canal
     */
    public function getName(): string;

    /**
     * Récupère la configuration du canal
     */
    public function getConfiguration(): array;

    /**
     * Valide les données pour ce canal
     */
    public function validateData(array $data): bool;

    /**
     * Formate les données pour ce canal
     */
    public function formatData(array $data, array $options = []): array;
}
