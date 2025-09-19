<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PageRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: PageRepository::class)]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnabled = true;

    #[ORM\OneToMany(targetEntity: PageTranslation::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function isIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    /**
     * @return Collection<int, PageTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PageTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPage($this);
        }
        return $this;
    }

    public function removeTranslation(PageTranslation $translation): self
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getPage() === $this) {
                $translation->setPage(null);
            }
        }
        return $this;
    }

    /**
     * Récupère une traduction spécifique par langue
     */
    public function getTranslationForLanguage(string $languageCode): ?PageTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $languageCode) {
                return $translation;
            }
        }
        return null;
    }

    // Méthodes de compatibilité - deleguer vers la traduction par défaut ou première disponible
    public function getTitle(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getTitle();
    }

    public function getContent(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getContent();
    }

    public function getSlug(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSlug();
    }

    public function getResume(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getResume();
    }

    // Setters de compatibilité (temporaires pour la migration)
    public function setTitle(string $title): self
    {
        // Ces méthodes sont temporaires pour la migration depuis Gedmo
        return $this;
    }

    public function setContent(string $content): self
    {
        return $this;
    }

    public function setSlug(string $slug): self
    {
        return $this;
    }

    public function setResume(?string $resume): self
    {
        return $this;
    }

    /**
     * Récupère la traduction par défaut ou la première disponible
     */
    private function getDefaultTranslation(): ?PageTranslation
    {
        if ($this->translations->isEmpty()) {
            return null;
        }

        // Chercher d'abord une traduction dans la langue par défaut
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->isIsDefault()) {
                return $translation;
            }
        }

        // Sinon retourner la première traduction disponible
        return $this->translations->first() ?: null;
    }
}
