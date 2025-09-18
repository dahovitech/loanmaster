<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\ValueObject\LoanId;
use App\Infrastructure\Http\Api\Resource\LoanResource;
use App\Infrastructure\Http\Api\Transformer\LoanResourceTransformer;

final readonly class LoanProvider implements ProviderInterface
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private LoanResourceTransformer $transformer
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?LoanResource
    {
        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            return null;
        }
        
        try {
            $loanId = LoanId::fromString($id);
            $loan = $this->loanRepository->findById($loanId);
            
            if (!$loan) {
                return null;
            }
            
            return $this->transformer->transformToResource($loan);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
