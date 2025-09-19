<?php

declare(strict_types=1);

namespace App\Application\Handler\Loan;

use App\Application\Query\Loan\GetLoanByIdQuery;
use App\Domain\Entity\Loan;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\ValueObject\LoanId;

final readonly class GetLoanByIdHandler
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository
    ) {}

    public function __invoke(GetLoanByIdQuery $query): ?Loan
    {
        $loanId = LoanId::fromString($query->loanId);
        
        return $this->loanRepository->findById($loanId);
    }
}
