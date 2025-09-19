<?php

namespace App\Service\Notification;

/**
 * Résultat d'envoi de notification
 * Encapsule les informations de succès/échec
 */
class NotificationResult
{
    private string $notificationId;
    private bool $success;
    private int $deliveredCount;
    private int $failedCount;
    private array $channelResults;
    private float $executionTime;
    private array $errors;
    private array $metadata;

    public function __construct(
        string $notificationId,
        bool $success,
        int $deliveredCount,
        int $failedCount,
        array $channelResults = [],
        float $executionTime = 0.0,
        array $errors = [],
        array $metadata = []
    ) {
        $this->notificationId = $notificationId;
        $this->success = $success;
        $this->deliveredCount = $deliveredCount;
        $this->failedCount = $failedCount;
        $this->channelResults = $channelResults;
        $this->executionTime = $executionTime;
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getTotalCount(): int
    {
        return $this->deliveredCount + $this->failedCount;
    }

    public function getSuccessRate(): float
    {
        $total = $this->getTotalCount();
        return $total > 0 ? ($this->deliveredCount / $total) * 100 : 0;
    }

    public function getChannelResults(): array
    {
        return $this->channelResults;
    }

    public function getChannelResult(string $channel): ?array
    {
        return $this->channelResults[$channel] ?? null;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'success' => $this->success,
            'delivered_count' => $this->deliveredCount,
            'failed_count' => $this->failedCount,
            'total_count' => $this->getTotalCount(),
            'success_rate' => $this->getSuccessRate(),
            'execution_time' => $this->executionTime,
            'channel_results' => $this->channelResults,
            'errors' => $this->errors,
            'metadata' => $this->metadata
        ];
    }
}
