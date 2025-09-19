<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour traiter l'approbation de prêt en arrière-plan
 */
class LoanApprovalMessage
{
    public function __construct(
        private readonly int $loanId,
        private readonly int $adminId,
        private readonly array $approvalData = []
    ) {}

    public function getLoanId(): int
    {
        return $this->loanId;
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function getApprovalData(): array
    {
        return $this->approvalData;
    }
}
