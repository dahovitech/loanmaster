<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Annotation\Configurable;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SeoRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SeoRepository::class)]
#[Configurable]
class Seo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // SEO Image (non traduisible)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoImage = null;

    #[ORM\OneToMany(targetEntity: SeoTranslation::class, mappedBy: 'seo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeoImage(): ?string
    {
        return $this->seoImage;
    }

    public function setSeoImage(?string $seoImage): self
    {
        $this->seoImage = $seoImage;
        return $this;
    }

    /**
     * @return Collection<int, SeoTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(SeoTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setSeo($this);
        }
        return $this;
    }

    public function removeTranslation(SeoTranslation $translation): self
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getSeo() === $this) {
                $translation->setSeo(null);
            }
        }
        return $this;
    }

    /**
     * Récupère une traduction spécifique par langue
     */
    public function getTranslationForLanguage(string $languageCode): ?SeoTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $languageCode) {
                return $translation;
            }
        }
        return null;
    }

    // Méthodes de compatibilité - déléguer vers la traduction par défaut
    public function getSeoHomeTitle(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoHomeTitle();
    }

    public function setSeoHomeTitle(?string $seoHomeTitle): self
    {
        // Méthode temporaire pour la migration depuis Gedmo
        return $this;
    }

    public function getSeoHomeKeywords(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoHomeKeywords();
    }

    public function setSeoHomeKeywords(?string $seoHomeKeywords): self
    {
        return $this;
    }

    public function getSeoHomeDescription(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoHomeDescription();
    }

    public function setSeoHomeDescription(?string $seoHomeDescription): self
    {
        return $this;
    }

    public function getSeoAboutTitle(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoAboutTitle();
    }

    public function setSeoAboutTitle(?string $seoAboutTitle): self
    {
        return $this;
    }

    public function getSeoAboutKeywords(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoAboutKeywords();
    }

    public function setSeoAboutKeywords(?string $seoAboutKeywords): self
    {
        return $this;
    }

    public function getSeoAboutDescription(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoAboutDescription();
    }

    public function setSeoAboutDescription(?string $seoAboutDescription): self
    {
        return $this;
    }

    public function getSeoServiceTitle(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoServiceTitle();
    }

    public function setSeoServiceTitle(?string $seoServiceTitle): self
    {
        return $this;
    }

    public function getSeoServiceKeywords(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoServiceKeywords();
    }

    public function setSeoServiceKeywords(?string $seoServiceKeywords): self
    {
        return $this;
    }

    public function getSeoServiceDescription(): ?string
    {
        $translation = $this->getDefaultTranslation();
        return $translation?->getSeoServiceDescription();
    }

    public function setSeoServiceDescription(?string $seoServiceDescription): self
    {
        return $this;
    }

    /**
     * Récupère la traduction par défaut ou la première disponible
     */
    private function getDefaultTranslation(): ?SeoTranslation
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
