<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Intl\Languages;
use App\Repository\LanguageRepository;

#[ORM\Entity(repositoryClass: LanguageRepository::class)]
class Language
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'native_name', length: 255, nullable: true)]
    private ?string $nativeName = null;

    #[ORM\Column(length: 8)]
    private ?string $dir = null;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_enabled', type: 'boolean')]
    private bool $isEnabled = true;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations vers les entités de traduction
    // Ces relations seront ajoutées au fur et à mesure de la migration

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->code ?? 'N/A';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'code' => $this->getCode(),
            'name' => $this->getName(),
            'nativeName' => $this->getNativeName(),
            'dir' => $this->getDir(),
            'isActive' => $this->isIsActive(),
            'isEnabled' => $this->isIsEnabled(),
            'isDefault' => $this->isIsDefault(),
            'sortOrder' => $this->getSortOrder(),
            'createdAt' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        $this->updateTimestamp();

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->updateTimestamp();

        return $this;
    }

    public function getNativeName(): ?string
    {
        return $this->nativeName;
    }

    public function setNativeName(?string $nativeName): static
    {
        $this->nativeName = $nativeName;
        $this->updateTimestamp();

        return $this;
    }

    public function getNameFromCode(): ?string
    {
        return $this->code ? Languages::getName($this->code) : null;
    }

    public function getDir(): ?string
    {
        return $this->dir;
    }

    public function setDir(?string $dir): static
    {
        $this->dir = $dir;
        $this->updateTimestamp();

        return $this;
    }

    public function isIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->isEnabled = $isActive; // Garde les deux champs synchronisés
        $this->updateTimestamp();

        return $this;
    }

    // Méthode de compatibilité avec l'ancien système
    public function isIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    // Méthode de compatibilité avec l'ancien système
    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        $this->isActive = $isEnabled; // Garde les deux champs synchronisés
        $this->updateTimestamp();

        return $this;
    }

    public function isIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        $this->updateTimestamp();

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        $this->updateTimestamp();

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Met à jour automatiquement le timestamp
     */
    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Méthodes utilitaires pour le système Oragon
     */

    /**
     * Vérifie si la langue est compatible RTL
     */
    public function isRtl(): bool
    {
        return $this->dir === 'rtl';
    }

    /**
     * Récupère le nom d'affichage préféré
     */
    public function getDisplayName(): string
    {
        return $this->nativeName ?? $this->name ?? $this->getNameFromCode() ?? $this->code;
    }

    /**
     * Récupère la direction CSS
     */
    public function getCssDirection(): string
    {
        return $this->isRtl() ? 'rtl' : 'ltr';
    }

    /**
     * Format pour l'affichage en interface admin
     */
    public function getAdminLabel(): string
    {
        $label = $this->getDisplayName() . " ({$this->code})";
        
        if ($this->isDefault) {
            $label .= ' [Défaut]';
        }
        
        if (!$this->isEnabled) {
            $label .= ' [Inactif]';
        }
        
        return $label;
    }
}