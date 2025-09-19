<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\Http\Api\Provider\UserProvider;
use App\Infrastructure\Http\Api\Provider\UserCollectionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('ROLE_ADMIN') or object.id == user.getId()",
            provider: UserProvider::class
        ),
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            provider: UserCollectionProvider::class
        )
    ],
    normalizationContext: ['groups' => ['user:read']],
    paginationEnabled: true
)]
final class UserResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['user:read'])]
    public string $id;
    
    #[Groups(['user:read'])]
    public string $email;
    
    #[Groups(['user:read'])]
    public string $firstName;
    
    #[Groups(['user:read'])]
    public string $lastName;
    
    #[Groups(['user:read'])]
    public string $fullName;
    
    #[Groups(['user:read'])]
    public \DateTimeImmutable $createdAt;
    
    #[Groups(['user:read'])]
    public int $activeLoansCount;
    
    #[Groups(['user:read'])]
    public float $totalBorrowedAmount;
}
