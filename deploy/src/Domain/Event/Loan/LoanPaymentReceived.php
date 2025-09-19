<?php

declare(strict_types=1);

namespace App\Domain\Event\Loan;

use App\Domain\Event\AbstractDomainEvent;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

/**
 * Événement déclenché lors d'un paiement de remboursement
 */
class LoanPaymentReceived extends AbstractDomainEvent
{
    public function __construct(
        UuidInterface $loanId,
        float $paymentAmount,
        string $paymentMethod,
        ?string $transactionId = null,
        ?DateTimeImmutable $dueDate = null,
        ?int $installmentNumber = null,
        bool $isEarlyPayment = false,
        int $version = 1
    ) {
        $payload = [
            'paymentAmount' => $paymentAmount,
            'paymentMethod' => $paymentMethod,
            'transactionId' => $transactionId,
            'dueDate' => $dueDate?->format(DATE_ATOM),
            'installmentNumber' => $installmentNumber,
            'isEarlyPayment' => $isEarlyPayment,
            'paymentDetails' => [
                'receivedAt' => new DateTimeImmutable(),
                'currency' => 'EUR',
                'status' => 'completed',
                'fees' => $isEarlyPayment ? 0 : ($paymentAmount * 0.01) // 1% frais si paiement à temps
            ],
            'balanceImpact' => [
                'principalPaid' => $paymentAmount * 0.8,
                'interestPaid' => $paymentAmount * 0.2,
                'lateFeesPaid' => 0
            ]
        ];
        
        parent::__construct($loanId, $payload, $version);
    }

    public function getPaymentAmount(): float
    {
        return $this->payload['paymentAmount'];
    }

    public function getPaymentMethod(): string
    {
        return $this->payload['paymentMethod'];
    }

    public function getTransactionId(): ?string
    {
        return $this->payload['transactionId'];
    }

    public function getInstallmentNumber(): ?int
    {
        return $this->payload['installmentNumber'];
    }

    public function isEarlyPayment(): bool
    {
        return $this->payload['isEarlyPayment'];
    }

    public function getPrincipalPaid(): float
    {
        return $this->payload['balanceImpact']['principalPaid'];
    }

    public function getInterestPaid(): float
    {
        return $this->payload['balanceImpact']['interestPaid'];
    }

    public function getFees(): float
    {
        return $this->payload['paymentDetails']['fees'];
    }
}
