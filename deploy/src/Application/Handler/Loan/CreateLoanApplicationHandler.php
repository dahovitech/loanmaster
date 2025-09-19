<?php

declare(strict_types=1);

namespace App\Application\Handler\Loan;

use App\Application\Command\Loan\CreateLoanApplicationCommand;
use App\Domain\Entity\Loan;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\EventBusInterface;
use App\Domain\Service\LoanNumberGeneratorInterface;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanType;
use App\Domain\ValueObject\UserId;
use InvalidArgumentException;

final readonly class CreateLoanApplicationHandler
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private UserRepositoryInterface $userRepository,
        private EventBusInterface $eventBus,
        private LoanNumberGeneratorInterface $numberGenerator
    ) {}

    public function __invoke(CreateLoanApplicationCommand $command): LoanId
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        
        $loanNumber = $this->numberGenerator->generate();
        $loanType = LoanType::from($command->loanType);
        $amount = Amount::fromFloat($command->amount);
        $duration = Duration::fromMonths($command->durationMonths);
        
        $loan = Loan::create(
            LoanId::generate(),
            $userId,
            $loanNumber,
            $loanType,
            $amount,
            $duration,
            $command->projectDescription
        );
        
        $this->loanRepository->save($loan);
        
        // Dispatch domain events
        $this->eventBus->dispatchEvents($loan->getUncommittedEvents());
        $loan->markEventsAsCommitted();
        
        return $loan->getId();
    }
}
