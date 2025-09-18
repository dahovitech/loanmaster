<?php

declare(strict_types=1);

namespace App\Domain\Event\Loan;

use App\Domain\Event\AbstractDomainEvent;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

/**
 * Événement déclenché lors de la création d'une demande de prêt
 * Audit complet des informations de la demande
 */
class LoanApplicationCreated extends AbstractDomainEvent
{
    public function __construct(
        UuidInterface $loanId,
        UuidInterface $customerId,
        float $requestedAmount,
        int $durationMonths,
        string $purpose,
        array $customerData,
        array $financialData,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $version = 1
    ) {
        $payload = [
            'customerId' => $customerId->toString(),
            'requestedAmount' => $requestedAmount,
            'durationMonths' => $durationMonths,
            'purpose' => $purpose,
            'customerData' => $this->sanitizeCustomerData($customerData),
            'financialData' => $financialData,
            'applicationSource' => [
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'timestamp' => new DateTimeImmutable()
            ],
            'riskAssessment' => [
                'initialScore' => null,
                'flaggedCriteria' => [],
                'requiresManualReview' => false
            ]
        ];
        
        parent::__construct($loanId, $payload, $version);
    }

    public function getCustomerId(): string
    {
        return $this->payload['customerId'];
    }

    public function getRequestedAmount(): float
    {
        return $this->payload['requestedAmount'];
    }

    public function getDurationMonths(): int
    {
        return $this->payload['durationMonths'];
    }

    public function getPurpose(): string
    {
        return $this->payload['purpose'];
    }

    public function getCustomerData(): array
    {
        return $this->payload['customerData'];
    }

    public function getFinancialData(): array
    {
        return $this->payload['financialData'];
    }

    public function getApplicationSource(): array
    {
        return $this->payload['applicationSource'];
    }

    /**
     * Nettoie les données sensibles du client pour l'audit
     */
    private function sanitizeCustomerData(array $customerData): array
    {
        $sanitized = $customerData;
        
        // Masque les données sensibles
        if (isset($sanitized['ssn'])) {
            $sanitized['ssn'] = '***-**-' . substr($sanitized['ssn'], -4);
        }
        
        if (isset($sanitized['bankAccount'])) {
            $sanitized['bankAccount'] = '****' . substr($sanitized['bankAccount'], -4);
        }
        
        return $sanitized;
    }
}
