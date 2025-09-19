<?php

namespace App\Application\Command\Loan;

use App\Infrastructure\EventSourcing\Command\CommandInterface;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

/**
 * Commande pour créer une nouvelle demande de prêt
 */
class CreateLoanApplicationCommand implements CommandInterface
{
    private string $commandId;
    private UuidInterface $loanId;
    private UuidInterface $customerId;
    private float $requestedAmount;
    private int $durationMonths;
    private string $purpose;
    private array $customerData;
    private array $financialData;
    private ?string $userId;
    private ?string $ipAddress;
    private ?string $userAgent;
    private ?string $correlationId;
    private DateTimeImmutable $createdAt;

    public function __construct(
        UuidInterface $loanId,
        UuidInterface $customerId,
        float $requestedAmount,
        int $durationMonths,
        string $purpose,
        array $customerData,
        array $financialData,
        ?string $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $correlationId = null
    ) {
        $this->commandId = uniqid('cmd_create_loan_', true);
        $this->loanId = $loanId;
        $this->customerId = $customerId;
        $this->requestedAmount = $requestedAmount;
        $this->durationMonths = $durationMonths;
        $this->purpose = $purpose;
        $this->customerData = $customerData;
        $this->financialData = $financialData;
        $this->userId = $userId;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->correlationId = $correlationId ?? uniqid('corr_', true);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function getLoanId(): UuidInterface
    {
        return $this->loanId;
    }

    public function getCustomerId(): UuidInterface
    {
        return $this->customerId;
    }

    public function getRequestedAmount(): float
    {
        return $this->requestedAmount;
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getCustomerData(): array
    {
        return $this->customerData;
    }

    public function getFinancialData(): array
    {
        return $this->financialData;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
