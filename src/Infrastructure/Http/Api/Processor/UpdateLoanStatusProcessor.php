<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\Loan\ApproveLoanCommand;
use App\Application\Command\Loan\RejectLoanCommand;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Infrastructure\Http\Api\Resource\LoanResource;
use App\Infrastructure\Http\Api\Transformer\LoanResourceTransformer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UpdateLoanStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private LoanRepositoryInterface $loanRepository,
        private LoanResourceTransformer $transformer,
        private Security $security
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoanResource
    {
        assert($data instanceof LoanResource);
        
        $user = $this->security->getUser();
        if (!$user) {
            throw new \RuntimeException('User must be authenticated');
        }
        
        $loanId = $uriVariables['id'] ?? null;
        if (!$loanId) {
            throw new \InvalidArgumentException('Loan ID is required');
        }
        
        // Dispatch appropriate command based on action
        match ($data->action) {
            'approve' => $this->commandBus->dispatch(
                new ApproveLoanCommand($loanId, $user->getId(), $data->actionComment)
            ),
            'reject' => $this->commandBus->dispatch(
                new RejectLoanCommand($loanId, $user->getId(), $data->actionComment ?? 'No reason provided')
            ),
            default => throw new \InvalidArgumentException('Unsupported action: ' . $data->action)
        };
        
        // Reload and return updated loan
        $loan = $this->loanRepository->findById(
            \App\Domain\ValueObject\LoanId::fromString($loanId)
        );
        
        if (!$loan) {
            throw new \RuntimeException('Loan not found after update');
        }
        
        return $this->transformer->transformToResource($loan);
    }
}
