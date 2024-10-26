<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Annotation\Configurable;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SeoRepository;
use Gedmo\Mapping\Annotation as Gedmo;


#[ORM\Entity(repositoryClass: SeoRepository::class)]
#[Gedmo\TranslationEntity(class: SeoTranslation::class)]
#[Configurable]
class Seo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // SEO Home Title
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoImage = null;

    // SEO Home Title
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoHomeTitle = null;

    // SEO Home Keywords
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoHomeKeywords = null;

    // SEO Home Description
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoHomeDescription = null;

    // SEO About Title
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoAboutTitle = null;

    // SEO About Keywords
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoAboutKeywords = null;

    // SEO About Description
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoAboutDescription = null;

    // SEO Service Title
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoServiceTitle = null;

    // SEO Service Keywords
    #[Gedmo\Translatable]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoServiceKeywords = null;

    // SEO Service Description
    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoServiceDescription = null;


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

    // Getter and Setter for seoHomeTitle
    public function getSeoHomeTitle(): ?string
    {
        return $this->seoHomeTitle;
    }

    public function setSeoHomeTitle(?string $seoHomeTitle): self
    {
        $this->seoHomeTitle = $seoHomeTitle;

        return $this;
    }

    // Getter and Setter for seoHomeKeywords
    public function getSeoHomeKeywords(): ?string
    {
        return $this->seoHomeKeywords;
    }

    public function setSeoHomeKeywords(?string $seoHomeKeywords): self
    {
        $this->seoHomeKeywords = $seoHomeKeywords;

        return $this;
    }

    // Getter and Setter for seoHomeDescription
    public function getSeoHomeDescription(): ?string
    {
        return $this->seoHomeDescription;
    }

    public function setSeoHomeDescription(?string $seoHomeDescription): self
    {
        $this->seoHomeDescription = $seoHomeDescription;

        return $this;
    }

    // Getter and Setter for seoAboutTitle
    public function getSeoAboutTitle(): ?string
    {
        return $this->seoAboutTitle;
    }

    public function setSeoAboutTitle(?string $seoAboutTitle): self
    {
        $this->seoAboutTitle = $seoAboutTitle;

        return $this;
    }

    // Getter and Setter for seoAboutKeywords
    public function getSeoAboutKeywords(): ?string
    {
        return $this->seoAboutKeywords;
    }

    public function setSeoAboutKeywords(?string $seoAboutKeywords): self
    {
        $this->seoAboutKeywords = $seoAboutKeywords;

        return $this;
    }

    // Getter and Setter for seoAboutDescription
    public function getSeoAboutDescription(): ?string
    {
        return $this->seoAboutDescription;
    }

    public function setSeoAboutDescription(?string $seoAboutDescription): self
    {
        $this->seoAboutDescription = $seoAboutDescription;

        return $this;
    }

    // Getter and Setter for seoServiceTitle
    public function getSeoServiceTitle(): ?string
    {
        return $this->seoServiceTitle;
    }

    public function setSeoServiceTitle(?string $seoServiceTitle): self
    {
        $this->seoServiceTitle = $seoServiceTitle;

        return $this;
    }

    // Getter and Setter for seoServiceKeywords
    public function getSeoServiceKeywords(): ?string
    {
        return $this->seoServiceKeywords;
    }

    public function setSeoServiceKeywords(?string $seoServiceKeywords): self
    {
        $this->seoServiceKeywords = $seoServiceKeywords;

        return $this;
    }

    // Getter and Setter for seoServiceDescription
    public function getSeoServiceDescription(): ?string
    {
        return $this->seoServiceDescription;
    }

    public function setSeoServiceDescription(?string $seoServiceDescription): self
    {
        $this->seoServiceDescription = $seoServiceDescription;

        return $this;
    }
}
