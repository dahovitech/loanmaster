<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour traiter les paiements en arrière-plan
 */
class PaymentProcessingMessage
{
    public function __construct(
        private readonly int $paymentId,
        private readonly string $action = 'process',
        private readonly array $paymentData = []
    ) {}

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getPaymentData(): array
    {
        return $this->paymentData;
    }
}
