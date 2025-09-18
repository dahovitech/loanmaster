<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Command\Loan\CreateLoanApplicationCommand;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Infrastructure\Http\Api\Resource\LoanResource;
use App\Infrastructure\Http\Api\Transformer\LoanResourceTransformer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateLoanProcessor implements ProcessorInterface
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
        
        $command = new CreateLoanApplicationCommand(
            $user->getId(), // Assuming user has getId() method
            $data->loanType,
            $data->amount,
            $data->durationMonths,
            $data->projectDescription
        );
        
        /** @var LoanId $loanId */
        $loanId = $this->commandBus->dispatch($command);
        
        $loan = $this->loanRepository->findById($loanId);
        if (!$loan) {
            throw new \RuntimeException('Created loan not found');
        }
        
        return $this->transformer->transformToResource($loan);
    }
}
