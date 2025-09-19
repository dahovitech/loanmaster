<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Application\Command\Loan\CreateLoanApplicationCommand;

final class CreateLoanRequest
{
    #[Assert\NotBlank(message: 'Le type de prêt est requis')]
    #[Assert\Choice(
        choices: ['personal', 'auto', 'mortgage', 'business', 'student', 'renovation'],
        message: 'Type de prêt invalide'
    )]
    public string $loanType;
    
    #[Assert\NotBlank(message: 'Le montant est requis')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Assert\Range(
        min: 1000,
        max: 1000000,
        notInRangeMessage: 'Le montant doit être entre {{ min }} et {{ max }} euros'
    )]
    public float $amount;
    
    #[Assert\NotBlank(message: 'La durée est requise')]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 6,
        max: 360,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} mois'
    )]
    public int $durationMonths;
    
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $projectDescription = null;
    
    public function toCommand(string $userId): CreateLoanApplicationCommand
    {
        return new CreateLoanApplicationCommand(
            $userId,
            $this->loanType,
            $this->amount,
            $this->durationMonths,
            $this->projectDescription
        );
    }
}
