<?php

declare(strict_types=1);

namespace App\Application\Command\Loan;

final readonly class ApproveLoanCommand
{
    public function __construct(
        public string $loanId,
        public string $approvedBy,
        public ?string $comments = null
    ) {}
}
