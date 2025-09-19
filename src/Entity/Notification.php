<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * Entité pour l'historique des notifications
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notification_type', columns: ['type'])]
#[ORM\Index(name: 'idx_notification_status', columns: ['status'])]
#[ORM\Index(name: 'idx_notification_created', columns: ['created_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $notificationId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $type;

    #[ORM\Column(type: 'json')]
    private array $recipients = [];

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending';

    #[ORM\Column(type: 'integer')]
    private int $deliveredCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $failedCount = 0;

    #[ORM\Column(type: 'json')]
    private array $channelResults = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $errors = [];

    #[ORM\Column(type: 'float')]
    private float $executionTime = 0.0;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function setNotificationId(string $notificationId): self
    {
        $this->notificationId = $notificationId;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): self
    {
        $this->recipients = $recipients;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function setDeliveredCount(int $deliveredCount): self
    {
        $this->deliveredCount = $deliveredCount;
        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): self
    {
        $this->failedCount = $failedCount;
        return $this;
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

    public function setChannelResults(array $channelResults): self
    {
        $this->channelResults = $channelResults;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(float $executionTime): self
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'sent' && $this->deliveredCount > 0;
    }

    public function markAsSent(): self
    {
        $this->status = 'sent';
        $this->sentAt = new DateTimeImmutable();
        return $this;
    }

    public function markAsFailed(array $errors = []): self
    {
        $this->status = 'failed';
        $this->errors = $errors;
        return $this;
    }
}
