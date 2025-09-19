<?php

namespace App\Entity;

use App\Repository\SliderRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: SliderRepository::class)]
#[Gedmo\TranslationEntity(class: SliderTranslation::class)]
class Slider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $btnText = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $btnUrl = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isEnabled = true;

    #[ORM\OneToMany(targetEntity: SliderTranslation::class, mappedBy: 'object', cascade: ['persist', 'remove'])]
    private $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }



    public function isIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, SliderTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(SliderTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setObject($this);
        }

        return $this;
    }

    public function removeTranslation(SliderTranslation $translation): self
    {
        if ($this->translations->removeElement($translation)) {
            // set the owning side to null (unless already changed)
            if ($translation->getObject() === $this) {
                $translation->setObject(null);
            }
        }

        return $this;
    }

    /**
     * Get the value of btnText
     */ 
    public function getBtnText()
    {
        return $this->btnText;
    }

    /**
     * Set the value of btnText
     *
     * @return  self
     */ 
    public function setBtnText($btnText)
    {
        $this->btnText = $btnText;

        return $this;
    }

    /**
     * Get the value of btnUrl
     */ 
    public function getBtnUrl()
    {
        return $this->btnUrl;
    }

    /**
     * Set the value of btnUrl
     *
     * @return  self
     */ 
    public function setBtnUrl($btnUrl)
    {
        $this->btnUrl = $btnUrl;

        return $this;
    }

    /**
     * Get the value of description
     */ 
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */ 
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }
}
