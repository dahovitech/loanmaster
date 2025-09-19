<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Loan;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\LoanStatus;

interface LoanRepositoryInterface
{
    public function save(Loan $loan): void;
    
    public function findById(LoanId $id): ?Loan;
    
    public function findByNumber(string $number): ?Loan;
    
    /**
     * @return Loan[]
     */
    public function findByUserId(UserId $userId): array;
    
    /**
     * @return Loan[]
     */
    public function findActiveLoansForUser(UserId $userId): array;
    
    /**
     * @return Loan[]
     */
    public function findByStatus(LoanStatus $status): array;
    
    /**
     * @return Loan[]
     */
    public function findPendingLoans(): array;
    
    public function remove(Loan $loan): void;
    
    public function nextIdentity(): LoanId;
}
