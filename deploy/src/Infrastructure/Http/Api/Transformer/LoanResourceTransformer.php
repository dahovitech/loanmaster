<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Transformer;

use App\Domain\Entity\Loan;
use App\Infrastructure\Http\Api\Resource\LoanResource;

final class LoanResourceTransformer
{
    public function transformToResource(Loan $loan): LoanResource
    {
        $resource = new LoanResource();
        
        $resource->id = $loan->getId()->toString();
        $resource->number = $loan->getNumber();
        $resource->loanType = $loan->getType()->value;
        $resource->amount = $loan->getAmount()->getValue();
        $resource->durationMonths = $loan->getDuration()->getMonths();
        $resource->projectDescription = $loan->getProjectDescription();
        $resource->status = $loan->getStatus()->value;
        $resource->statusLabel = $loan->getStatus()->getLabel();
        $resource->interestRate = $loan->getInterestRate()->getPercentage();
        $resource->userId = $loan->getUserId()->toString();
        $resource->createdAt = $loan->getCreatedAt();
        $resource->approvedAt = $loan->getApprovedAt();
        $resource->fundedAt = $loan->getFundedAt();
        
        // Calculs financiers
        $resource->monthlyPayment = $loan->calculateMonthlyPayment()->getValue();
        $resource->totalAmount = $loan->calculateTotalAmount()->getValue();
        $resource->totalInterest = $loan->calculateTotalInterest()->getValue();
        
        return $resource;
    }
    
    /**
     * @param LoanResource[] $loans
     * @return Loan[]
     */
    public function transformToResourceCollection(array $loans): array
    {
        return array_map([$this, 'transformToResource'], $loans);
    }
}
