<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Annotation\Configurable;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SettingTranslation;
use App\Repository\SettingRepository;
use Gedmo\Mapping\Annotation as Gedmo;


#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[Gedmo\TranslationEntity(class: SettingTranslation::class)]
#[Configurable]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoDark = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoLight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailImg = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $favicon = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailSender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 10)]
    private ?string $devise = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $theme = null;

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


    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

        return $this;
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLogoDark(): ?string
    {
        return $this->logoDark;
    }

    public function setLogoDark(?string $logoDark): self
    {
        $this->logoDark = $logoDark;

        return $this;
    }

    public function getLogoLight(): ?string
    {
        return $this->logoLight;
    }

    public function setLogoLight(?string $logoLight): self
    {
        $this->logoLight = $logoLight;

        return $this;
    }

    public function getEmailImg(): ?string
    {
        return $this->emailImg;
    }

    public function setEmailImg(?string $emailImg): self
    {
        $this->emailImg = $emailImg;

        return $this;
    }

    public function getFavicon(): ?string
    {
        return $this->favicon;
    }

    public function setFavicon(?string $favicon): self
    {
        $this->favicon = $favicon;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): self
    {
        $this->devise = $devise;

        return $this;
    }


    public function getEmailSender(): ?string
    {
        return $this->emailSender;
    }

    public function setEmailSender(?string $emailSender): self
    {
        $this->emailSender = $emailSender;

        return $this;
    }

    /**
     * Get the value of address
     */ 
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the value of address
     *
     * @return  self
     */ 
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

}
