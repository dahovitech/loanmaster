<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Infrastructure\Http\Api\Processor\CreateLoanProcessor;
use App\Infrastructure\Http\Api\Processor\UpdateLoanStatusProcessor;
use App\Infrastructure\Http\Api\Provider\LoanProvider;
use App\Infrastructure\Http\Api\Provider\LoanCollectionProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('loan.view', object)",
            provider: LoanProvider::class
        ),
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            provider: LoanCollectionProvider::class
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            validationContext: ['groups' => ['loan:create']],
            processor: CreateLoanProcessor::class
        ),
        new Patch(
            security: "is_granted('loan.edit', object)",
            validationContext: ['groups' => ['loan:update']],
            processor: UpdateLoanStatusProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['loan:read']],
    denormalizationContext: ['groups' => ['loan:write']],
    paginationEnabled: true,
    paginationItemsPerPage: 20
)]
final class LoanResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['loan:read'])]
    public string $id;
    
    #[Groups(['loan:read'])]
    public string $number;
    
    #[Groups(['loan:read', 'loan:write'])]
    #[Assert\NotBlank(groups: ['loan:create'], message: 'Le type de prêt est requis')]
    #[Assert\Choice(
        choices: ['personal', 'auto', 'mortgage', 'business', 'student', 'renovation'],
        groups: ['loan:create'],
        message: 'Type de prêt invalide'
    )]
    public string $loanType;
    
    #[Groups(['loan:read', 'loan:write'])]
    #[Assert\NotBlank(groups: ['loan:create'], message: 'Le montant est requis')]
    #[Assert\Positive(groups: ['loan:create'], message: 'Le montant doit être positif')]
    #[Assert\Range(
        min: 1000,
        max: 1000000,
        groups: ['loan:create'],
        notInRangeMessage: 'Le montant doit être entre {{ min }} et {{ max }} euros'
    )]
    public float $amount;
    
    #[Groups(['loan:read', 'loan:write'])]
    #[Assert\NotBlank(groups: ['loan:create'], message: 'La durée est requise')]
    #[Assert\Positive(groups: ['loan:create'], message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 6,
        max: 360,
        groups: ['loan:create'],
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} mois'
    )]
    public int $durationMonths;
    
    #[Groups(['loan:read', 'loan:write'])]
    #[Assert\Length(
        max: 1000,
        groups: ['loan:create', 'loan:update'],
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $projectDescription = null;
    
    #[Groups(['loan:read'])]
    public string $status;
    
    #[Groups(['loan:read'])]
    public string $statusLabel;
    
    #[Groups(['loan:read'])]
    public float $interestRate;
    
    #[Groups(['loan:read'])]
    public float $monthlyPayment;
    
    #[Groups(['loan:read'])]
    public float $totalAmount;
    
    #[Groups(['loan:read'])]
    public float $totalInterest;
    
    #[Groups(['loan:read'])]
    public string $userId;
    
    #[Groups(['loan:read'])]
    public \DateTimeImmutable $createdAt;
    
    #[Groups(['loan:read'])]
    public ?\DateTimeImmutable $approvedAt = null;
    
    #[Groups(['loan:read'])]
    public ?\DateTimeImmutable $fundedAt = null;
    
    // Actions pour les changements de statut
    #[Groups(['loan:update'])]
    #[Assert\Choice(
        choices: ['approve', 'reject', 'fund', 'activate', 'complete', 'default', 'cancel'],
        groups: ['loan:update'],
        message: 'Action invalide'
    )]
    public ?string $action = null;
    
    #[Groups(['loan:update'])]
    #[Assert\Length(
        max: 500,
        groups: ['loan:update'],
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $actionComment = null;
}
