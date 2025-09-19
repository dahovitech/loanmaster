<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\Loan;
use App\Domain\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Événement déclenché lors du changement de statut d'un prêt
 */
class LoanStatusChangedEvent extends Event
{
    public const NAME = 'loan.status.changed';

    public function __construct(
        private readonly Loan $loan,
        private readonly string $previousStatus,
        private readonly string $newStatus,
        private readonly ?User $actor = null
    ) {}

    public function getLoan(): Loan
    {
        return $this->loan;
    }

    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getTransitionContext(): array
    {
        return [
            'loan_id' => $this->loan->getId(),
            'user_id' => $this->loan->getUser()->getId(),
            'amount' => $this->loan->getAmount(),
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'actor_id' => $this->actor?->getId(),
            'timestamp' => new \DateTimeImmutable()
        ];
    }
}
