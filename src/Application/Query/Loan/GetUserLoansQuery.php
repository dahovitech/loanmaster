<?php

declare(strict_types=1);

namespace App\Application\Query\Loan;

final readonly class GetUserLoansQuery
{
    public function __construct(
        public string $userId,
        public ?string $status = null,
        public int $page = 1,
        public int $limit = 20
    ) {}
}
