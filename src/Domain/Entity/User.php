<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\UserId;
use DateTimeImmutable;

class User
{
    public function __construct(
        private UserId $id,
        private string $email,
        private string $firstName,
        private string $lastName,
        private DateTimeImmutable $createdAt
    ) {}
    
    public static function create(
        UserId $id,
        string $email,
        string $firstName,
        string $lastName
    ): self {
        return new self(
            $id,
            $email,
            $firstName,
            $lastName,
            new DateTimeImmutable()
        );
    }
    
    public function getId(): UserId
    {
        return $this->id;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    
    public function getLastName(): string
    {
        return $this->lastName;
    }
    
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
