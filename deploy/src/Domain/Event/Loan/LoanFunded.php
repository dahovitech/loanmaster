<?php

declare(strict_types=1);

namespace App\Domain\Event\Loan;

use App\Domain\Event\AbstractDomainEvent;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

/**
 * Événement déclenché lors du financement d'un prêt
 */
class LoanFunded extends AbstractDomainEvent
{
    public function __construct(
        UuidInterface $loanId,
        float $fundedAmount,
        string $fundingMethod,
        ?string $transactionId = null,
        ?string $bankAccount = null,
        ?DateTimeImmutable $expectedTransferDate = null,
        int $version = 1
    ) {
        $payload = [
            'fundedAmount' => $fundedAmount,
            'fundingMethod' => $fundingMethod,
            'transactionId' => $transactionId,
            'bankAccount' => $bankAccount ? $this->maskBankAccount($bankAccount) : null,
            'expectedTransferDate' => $expectedTransferDate?->format(DATE_ATOM),
            'fundingDetails' => [
                'processingFee' => $fundedAmount * 0.02, // 2% frais de traitement
                'netAmount' => $fundedAmount * 0.98,
                'currency' => 'EUR',
                'fundingTimestamp' => new DateTimeImmutable()
            ],
            'complianceChecks' => [
                'amlVerified' => true,
                'sanctionListChecked' => true,
                'fraudScoreVerified' => true,
                'regulatoryCompliant' => true
            ]
        ];
        
        parent::__construct($loanId, $payload, $version);
    }

    public function getFundedAmount(): float
    {
        return $this->payload['fundedAmount'];
    }

    public function getFundingMethod(): string
    {
        return $this->payload['fundingMethod'];
    }

    public function getTransactionId(): ?string
    {
        return $this->payload['transactionId'];
    }

    public function getNetAmount(): float
    {
        return $this->payload['fundingDetails']['netAmount'];
    }

    public function getProcessingFee(): float
    {
        return $this->payload['fundingDetails']['processingFee'];
    }

    public function getComplianceChecks(): array
    {
        return $this->payload['complianceChecks'];
    }

    private function maskBankAccount(string $bankAccount): string
    {
        return '****' . substr($bankAccount, -4);
    }
}
