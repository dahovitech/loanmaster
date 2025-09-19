<?php

namespace App\Entity;

use App\Repository\UserConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserConsentRepository::class)]
#[ORM\Table(name: 'user_consents')]
#[ORM\Index(columns: ['user_id'], name: 'idx_consent_user_id')]
#[ORM\Index(columns: ['consent_type'], name: 'idx_consent_type')]
#[ORM\Index(columns: ['status'], name: 'idx_consent_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_consent_created_at')]
#[ORM\UniqueConstraint(name: 'unique_user_consent_type', columns: ['user_id', 'consent_type'])]
class UserConsent
{
    // Types de consentement RGPD
    public const TYPE_DATA_PROCESSING = 'data_processing';
    public const TYPE_MARKETING = 'marketing';
    public const TYPE_ANALYTICS = 'analytics';
    public const TYPE_COOKIES = 'cookies';
    public const TYPE_PROFILING = 'profiling';
    public const TYPE_DATA_SHARING = 'data_sharing';
    public const TYPE_AUTOMATED_DECISION = 'automated_decision';

    // Statuts de consentement
    public const STATUS_GRANTED = 'granted';
    public const STATUS_DENIED = 'denied';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consent:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['consent:read'])]
    private ?int $userId = null;

    #[ORM\Column(length: 50)]
    #[Groups(['consent:read'])]
    private ?string $consentType = null;

    #[ORM\Column(length: 20)]
    #[Groups(['consent:read'])]
    private ?string $status = null;

    #[ORM\Column]
    #[Groups(['consent:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['consent:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['consent:read'])]
    private ?\DateTimeImmutable $withdrawnAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['consent:read'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $consentText = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['consent:read'])]
    private ?array $metadata = null;

    #[ORM\Column(length: 10)]
    #[Groups(['consent:read'])]
    private ?string $version = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $locale = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $withdrawalReason = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['consent:read'])]
    private ?string $legalBasis = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->version = '1.0';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getConsentType(): ?string
    {
        return $this->consentType;
    }

    public function setConsentType(string $consentType): static
    {
        $this->consentType = $consentType;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($status === self::STATUS_WITHDRAWN) {
            $this->withdrawnAt = new \DateTimeImmutable();
        }
        
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getWithdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?\DateTimeImmutable $withdrawnAt): static
    {
        $this->withdrawnAt = $withdrawnAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    public function getConsentText(): ?string
    {
        return $this->consentText;
    }

    public function setConsentText(?string $consentText): static
    {
        $this->consentText = $consentText;
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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getWithdrawalReason(): ?string
    {
        return $this->withdrawalReason;
    }

    public function setWithdrawalReason(?string $withdrawalReason): static
    {
        $this->withdrawalReason = $withdrawalReason;
        return $this;
    }

    public function getLegalBasis(): ?string
    {
        return $this->legalBasis;
    }

    public function setLegalBasis(?string $legalBasis): static
    {
        $this->legalBasis = $legalBasis;
        return $this;
    }

    // Méthodes utilitaires

    public function isGranted(): bool
    {
        return $this->status === self::STATUS_GRANTED;
    }

    public function isDenied(): bool
    {
        return $this->status === self::STATUS_DENIED;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return $this->isGranted() && !$this->isExpired();
    }

    public function grant(string $ipAddress = null, string $userAgent = null): static
    {
        $this->setStatus(self::STATUS_GRANTED);
        
        if ($ipAddress) {
            $this->setIpAddress($ipAddress);
        }
        
        if ($userAgent) {
            $this->setUserAgent($userAgent);
        }
        
        return $this;
    }

    public function deny(string $ipAddress = null, string $userAgent = null): static
    {
        $this->setStatus(self::STATUS_DENIED);
        
        if ($ipAddress) {
            $this->setIpAddress($ipAddress);
        }
        
        if ($userAgent) {
            $this->setUserAgent($userAgent);
        }
        
        return $this;
    }

    public function withdraw(string $reason = null, string $ipAddress = null, string $userAgent = null): static
    {
        $this->setStatus(self::STATUS_WITHDRAWN);
        
        if ($reason) {
            $this->setWithdrawalReason($reason);
        }
        
        if ($ipAddress) {
            $this->setIpAddress($ipAddress);
        }
        
        if ($userAgent) {
            $this->setUserAgent($userAgent);
        }
        
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

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $interval = $now->diff($this->expiresAt);
        
        return $this->expiresAt > $now ? $interval->days : -$interval->days;
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_DATA_PROCESSING,
            self::TYPE_MARKETING,
            self::TYPE_ANALYTICS,
            self::TYPE_COOKIES,
            self::TYPE_PROFILING,
            self::TYPE_DATA_SHARING,
            self::TYPE_AUTOMATED_DECISION,
        ];
    }

    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_GRANTED,
            self::STATUS_DENIED,
            self::STATUS_WITHDRAWN,
            self::STATUS_PENDING,
        ];
    }

    public static function getTypeDescription(string $type, string $locale = 'fr'): string
    {
        $descriptions = [
            'fr' => [
                self::TYPE_DATA_PROCESSING => 'Traitement des données personnelles',
                self::TYPE_MARKETING => 'Communications marketing et publicitaires',
                self::TYPE_ANALYTICS => 'Analyses et statistiques d\'utilisation',
                self::TYPE_COOKIES => 'Utilisation des cookies non-essentiels',
                self::TYPE_PROFILING => 'Profilage et personnalisation',
                self::TYPE_DATA_SHARING => 'Partage des données avec des tiers',
                self::TYPE_AUTOMATED_DECISION => 'Prise de décision automatisée',
            ],
            'en' => [
                self::TYPE_DATA_PROCESSING => 'Personal data processing',
                self::TYPE_MARKETING => 'Marketing and advertising communications',
                self::TYPE_ANALYTICS => 'Usage analytics and statistics',
                self::TYPE_COOKIES => 'Non-essential cookies usage',
                self::TYPE_PROFILING => 'Profiling and personalization',
                self::TYPE_DATA_SHARING => 'Data sharing with third parties',
                self::TYPE_AUTOMATED_DECISION => 'Automated decision making',
            ],
        ];

        return $descriptions[$locale][$type] ?? $type;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'consentType' => $this->consentType,
            'status' => $this->status,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'withdrawnAt' => $this->withdrawnAt?->format('Y-m-d H:i:s'),
            'expiresAt' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'version' => $this->version,
            'locale' => $this->locale,
            'isValid' => $this->isValid(),
            'isExpired' => $this->isExpired(),
            'daysUntilExpiry' => $this->getDaysUntilExpiry(),
        ];
    }
}
