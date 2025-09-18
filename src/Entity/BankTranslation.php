<?php

namespace App\Entity;

use App\Repository\BankTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankTranslationRepository::class)]
#[ORM\Table(name: 'bank_translations')]
#[ORM\UniqueConstraint(name: 'bank_language_unique', columns: ['translatable_id', 'language_id'])]
class BankTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Bank::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', nullable: false)]
    private ?Bank $translatable = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signBank = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signNotary = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTranslatable(): ?Bank
    {
        return $this->translatable;
    }

    public function setTranslatable(?Bank $translatable): static
    {
        $this->translatable = $translatable;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getSignBank(): ?string
    {
        return $this->signBank;
    }

    public function setSignBank(?string $signBank): static
    {
        $this->signBank = $signBank;

        return $this;
    }

    public function getSignNotary(): ?string
    {
        return $this->signNotary;
    }

    public function setSignNotary(?string $signNotary): static
    {
        $this->signNotary = $signNotary;

        return $this;
    }
}
