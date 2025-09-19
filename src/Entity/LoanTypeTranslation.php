<?php

namespace App\Entity;

use App\Repository\LoanTypeTranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanTypeTranslationRepository::class)]
#[ORM\UniqueConstraint(name: 'loan_type_language_unique', columns: ['translatable_id', 'language_id'])]
class LoanTypeTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: LoanType::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LoanType $translatable = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTranslatable(): ?LoanType
    {
        return $this->translatable;
    }

    public function setTranslatable(?LoanType $translatable): static
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

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
