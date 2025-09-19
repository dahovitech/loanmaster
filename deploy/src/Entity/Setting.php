<?php

namespace App\Entity;

use App\Annotation\Configurable;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SettingRepository;


#[ORM\Entity(repositoryClass: SettingRepository::class)]
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

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

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
