<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SeoTranslationRepository;

#[ORM\Entity(repositoryClass: SeoTranslationRepository::class)]
#[ORM\Table(name: 'seo_translations')]
#[ORM\UniqueConstraint(name: 'UNIQ_SEO_LANGUAGE', columns: ['seo_id', 'language_id'])]
class SeoTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Seo::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'seo_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Seo $seo = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(name: 'language_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Language $language = null;

    // SEO Home fields
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoHomeTitle = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoHomeKeywords = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoHomeDescription = null;

    // SEO About fields
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoAboutTitle = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoAboutKeywords = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoAboutDescription = null;

    // SEO Service fields
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoServiceTitle = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $seoServiceKeywords = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoServiceDescription = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeo(): ?Seo
    {
        return $this->seo;
    }

    public function setSeo(?Seo $seo): static
    {
        $this->seo = $seo;
        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;
        return $this;
    }

    // SEO Home getters/setters
    public function getSeoHomeTitle(): ?string
    {
        return $this->seoHomeTitle;
    }

    public function setSeoHomeTitle(?string $seoHomeTitle): static
    {
        $this->seoHomeTitle = $seoHomeTitle;
        return $this;
    }

    public function getSeoHomeKeywords(): ?string
    {
        return $this->seoHomeKeywords;
    }

    public function setSeoHomeKeywords(?string $seoHomeKeywords): static
    {
        $this->seoHomeKeywords = $seoHomeKeywords;
        return $this;
    }

    public function getSeoHomeDescription(): ?string
    {
        return $this->seoHomeDescription;
    }

    public function setSeoHomeDescription(?string $seoHomeDescription): static
    {
        $this->seoHomeDescription = $seoHomeDescription;
        return $this;
    }

    // SEO About getters/setters
    public function getSeoAboutTitle(): ?string
    {
        return $this->seoAboutTitle;
    }

    public function setSeoAboutTitle(?string $seoAboutTitle): static
    {
        $this->seoAboutTitle = $seoAboutTitle;
        return $this;
    }

    public function getSeoAboutKeywords(): ?string
    {
        return $this->seoAboutKeywords;
    }

    public function setSeoAboutKeywords(?string $seoAboutKeywords): static
    {
        $this->seoAboutKeywords = $seoAboutKeywords;
        return $this;
    }

    public function getSeoAboutDescription(): ?string
    {
        return $this->seoAboutDescription;
    }

    public function setSeoAboutDescription(?string $seoAboutDescription): static
    {
        $this->seoAboutDescription = $seoAboutDescription;
        return $this;
    }

    // SEO Service getters/setters
    public function getSeoServiceTitle(): ?string
    {
        return $this->seoServiceTitle;
    }

    public function setSeoServiceTitle(?string $seoServiceTitle): static
    {
        $this->seoServiceTitle = $seoServiceTitle;
        return $this;
    }

    public function getSeoServiceKeywords(): ?string
    {
        return $this->seoServiceKeywords;
    }

    public function setSeoServiceKeywords(?string $seoServiceKeywords): static
    {
        $this->seoServiceKeywords = $seoServiceKeywords;
        return $this;
    }

    public function getSeoServiceDescription(): ?string
    {
        return $this->seoServiceDescription;
    }

    public function setSeoServiceDescription(?string $seoServiceDescription): static
    {
        $this->seoServiceDescription = $seoServiceDescription;
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
