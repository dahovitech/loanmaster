<?php

declare(strict_types=1);

namespace App\Application\Handler\Loan;

use App\Application\Command\Loan\RejectLoanCommand;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\Service\EventBusInterface;
use App\Domain\ValueObject\LoanId;
use InvalidArgumentException;

final readonly class RejectLoanHandler
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private EventBusInterface $eventBus
    ) {}

    public function __invoke(RejectLoanCommand $command): void
    {
        $loanId = LoanId::fromString($command->loanId);
        $loan = $this->loanRepository->findById($loanId);
        
        if (!$loan) {
            throw new InvalidArgumentException('Loan not found');
        }
        
        $loan->reject($command->reason);
        
        $this->loanRepository->save($loan);
        
        // Dispatch domain events
        $this->eventBus->dispatchEvents($loan->getUncommittedEvents());
        $loan->markEventsAsCommitted();
    }
}
