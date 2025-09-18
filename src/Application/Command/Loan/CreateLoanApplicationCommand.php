<?php

declare(strict_types=1);

namespace App\Application\Command\Loan;

final readonly class CreateLoanApplicationCommand
{
    public function __construct(
        public string $userId,
        public string $loanType,
        public float $amount,
        public int $durationMonths,
        public ?string $projectDescription = null
    ) {}
}
