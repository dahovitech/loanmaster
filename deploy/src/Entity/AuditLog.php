<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['entity_type'], name: 'idx_audit_entity_type')]
#[ORM\Index(columns: ['entity_id'], name: 'idx_audit_entity_id')]
#[ORM\Index(columns: ['user_id'], name: 'idx_audit_user_id')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_created_at')]
#[ORM\Index(columns: ['ip_address'], name: 'idx_audit_ip')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['audit:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['audit:read'])]
    private ?string $action = null;

    #[ORM\Column(length: 100)]
    #[Groups(['audit:read'])]
    private ?string $entityType = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $entityId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['audit:read'])]
    private ?int $userId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $userName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $oldData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $newData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $changedFields = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $route = null;

    #[ORM\Column(length: 10)]
    #[Groups(['audit:read'])]
    private ?string $httpMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Groups(['audit:read'])]
    private ?string $severity = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $metadata = null;

    #[ORM\Column]
    #[Groups(['audit:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $gdprData = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->severity = 'info';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): static
    {
        $this->userName = $userName;
        return $this;
    }

    public function getOldData(): ?array
    {
        return $this->oldData;
    }

    public function setOldData(?array $oldData): static
    {
        $this->oldData = $oldData;
        return $this;
    }

    public function getNewData(): ?array
    {
        return $this->newData;
    }

    public function setNewData(?array $newData): static
    {
        $this->newData = $newData;
        return $this;
    }

    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }

    public function setChangedFields(?array $changedFields): static
    {
        $this->changedFields = $changedFields;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): static
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getGdprData(): ?array
    {
        return $this->gdprData;
    }

    public function setGdprData(?array $gdprData): static
    {
        $this->gdprData = $gdprData;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['critical', 'high', 'error']);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'userId' => $this->userId,
            'userName' => $this->userName,
            'ipAddress' => $this->ipAddress,
            'route' => $this->route,
            'httpMethod' => $this->httpMethod,
            'description' => $this->description,
            'severity' => $this->severity,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'sessionId' => $this->sessionId,
        ];
    }
}
