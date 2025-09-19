<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour traiter le débours de prêt en arrière-plan
 */
class LoanDisbursementMessage
{
    public function __construct(
        private readonly int $loanId,
        private readonly array $disbursementData = []
    ) {}

    public function getLoanId(): int
    {
        return $this->loanId;
    }

    public function getDisbursementData(): array
    {
        return $this->disbursementData;
    }
}
