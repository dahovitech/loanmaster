<?php

declare(strict_types=1);

namespace App\Application\Command\Loan;

final readonly class RejectLoanCommand
{
    public function __construct(
        public string $loanId,
        public string $rejectedBy,
        public string $reason
    ) {}
}
