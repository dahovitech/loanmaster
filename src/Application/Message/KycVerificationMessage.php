<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour traiter la vérification KYC en arrière-plan
 */
class KycVerificationMessage
{
    public function __construct(
        private readonly int $kycId,
        private readonly string $verificationType = 'automatic'
    ) {}

    public function getKycId(): int
    {
        return $this->kycId;
    }

    public function getVerificationType(): string
    {
        return $this->verificationType;
    }
}
