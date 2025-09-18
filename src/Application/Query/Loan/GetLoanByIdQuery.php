<?php

declare(strict_types=1);

namespace App\Application\Query\Loan;

final readonly class GetLoanByIdQuery
{
    public function __construct(public string $loanId)
    {}
}
