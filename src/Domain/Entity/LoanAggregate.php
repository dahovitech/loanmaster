<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Event\Loan\LoanApplicationCreated;
use App\Domain\Event\Loan\LoanStatusChanged;
use App\Domain\Event\Loan\LoanFunded;
use App\Domain\Event\Loan\LoanPaymentReceived;
use App\Domain\Event\Loan\LoanRiskAssessed;
use App\Infrastructure\EventSourcing\AggregateRoot;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Agrégat Loan avec Event Sourcing complet
 * Tous les changements d'état sont capturés via des événements
 */
class LoanAggregate extends AggregateRoot
{
    private UuidInterface $customerId;
    private float $requestedAmount;
    private float $approvedAmount;
    private int $durationMonths;
    private string $purpose;
    private string $status;
    private int $riskScore;
    private string $riskLevel;
    private float $interestRate;
    private float $currentBalance;
    private array $paymentHistory = [];
    private array $customerData = [];
    private array $financialData = [];
    private ?DateTimeImmutable $fundedAt = null;
    private ?DateTimeImmutable $completedAt = null;

    // Statuts possibles
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_REQUIRES_DOCUMENTS = 'requires_documents';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FUNDED = 'funded';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DEFAULTED = 'defaulted';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Création d'une nouvelle demande de prêt
     */
    public static function createApplication(
        UuidInterface $loanId,
        UuidInterface $customerId,
        float $requestedAmount,
        int $durationMonths,
        string $purpose,
        array $customerData,
        array $financialData,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $loan = new self($loanId);
        
        $event = new LoanApplicationCreated(
            $loanId,
            $customerId,
            $requestedAmount,
            $durationMonths,
            $purpose,
            $customerData,
            $financialData,
            $ipAddress,
            $userAgent
        );
        
        $loan->recordEvent($event);
        
        return $loan;
    }

    /**
     * Change le statut du prêt
     */
    public function changeStatus(
        string $newStatus,
        string $reason,
        ?UuidInterface $changedBy = null,
        ?string $comments = null,
        array $additionalData = []
    ): void {
        if ($this->status === $newStatus) {
            return; // Pas de changement
        }
        
        $this->validateStatusTransition($this->status, $newStatus);
        
        $event = new LoanStatusChanged(
            $this->id,
            $this->status,
            $newStatus,
            $reason,
            $changedBy,
            $comments,
            $additionalData
        );
        
        $this->recordEvent($event);
    }

    /**
     * Évalue le risque du prêt
     */
    public function assessRisk(
        int $riskScore,
        string $riskLevel,
        array $riskFactors,
        string $assessmentMethod,
        ?UuidInterface $assessedBy = null
    ): void {
        $event = new LoanRiskAssessed(
            $this->id,
            $riskScore,
            $riskLevel,
            $riskFactors,
            $assessmentMethod,
            $assessedBy
        );
        
        $this->recordEvent($event);
    }

    /**
     * Finance le prêt
     */
    public function fund(
        float $fundedAmount,
        string $fundingMethod,
        ?string $transactionId = null,
        ?string $bankAccount = null,
        ?DateTimeImmutable $expectedTransferDate = null
    ): void {
        if ($this->status !== self::STATUS_APPROVED) {
            throw new InvalidArgumentException('Loan must be approved before funding');
        }
        
        $event = new LoanFunded(
            $this->id,
            $fundedAmount,
            $fundingMethod,
            $transactionId,
            $bankAccount,
            $expectedTransferDate
        );
        
        $this->recordEvent($event);
    }

    /**
     * Enregistre un paiement
     */
    public function receivePayment(
        float $paymentAmount,
        string $paymentMethod,
        ?string $transactionId = null,
        ?DateTimeImmutable $dueDate = null,
        ?int $installmentNumber = null,
        bool $isEarlyPayment = false
    ): void {
        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FUNDED])) {
            throw new InvalidArgumentException('Loan must be active to receive payments');
        }
        
        $event = new LoanPaymentReceived(
            $this->id,
            $paymentAmount,
            $paymentMethod,
            $transactionId,
            $dueDate,
            $installmentNumber,
            $isEarlyPayment
        );
        
        $this->recordEvent($event);
    }

    // Event Handlers - appliquent les changements d'état
    
    protected function applyLoanApplicationCreated(LoanApplicationCreated $event): void
    {
        $this->customerId = \Ramsey\Uuid\Uuid::fromString($event->getCustomerId());
        $this->requestedAmount = $event->getRequestedAmount();
        $this->durationMonths = $event->getDurationMonths();
        $this->purpose = $event->getPurpose();
        $this->customerData = $event->getCustomerData();
        $this->financialData = $event->getFinancialData();
        $this->status = self::STATUS_PENDING;
        $this->currentBalance = 0;
        $this->interestRate = 5.0; // Taux par défaut
    }

    protected function applyLoanStatusChanged(LoanStatusChanged $event): void
    {
        $this->status = $event->getNewStatus();
        
        if ($event->getNewStatus() === self::STATUS_FUNDED) {
            $this->fundedAt = new DateTimeImmutable();
        } elseif ($event->getNewStatus() === self::STATUS_COMPLETED) {
            $this->completedAt = new DateTimeImmutable();
        }
    }

    protected function applyLoanRiskAssessed(LoanRiskAssessed $event): void
    {
        $this->riskScore = $event->getRiskScore();
        $this->riskLevel = $event->getRiskLevel();
        
        // Ajustement du taux d'intérêt basé sur le risque
        $this->interestRate += $event->getInterestRateAdjustment();
    }

    protected function applyLoanFunded(LoanFunded $event): void
    {
        $this->approvedAmount = $event->getFundedAmount();
        $this->currentBalance = $event->getFundedAmount();
        $this->status = self::STATUS_ACTIVE;
        $this->fundedAt = new DateTimeImmutable();
    }

    protected function applyLoanPaymentReceived(LoanPaymentReceived $event): void
    {
        $this->paymentHistory[] = [
            'amount' => $event->getPaymentAmount(),
            'method' => $event->getPaymentMethod(),
            'transactionId' => $event->getTransactionId(),
            'receivedAt' => new DateTimeImmutable(),
            'principalPaid' => $event->getPrincipalPaid(),
            'interestPaid' => $event->getInterestPaid()
        ];
        
        $this->currentBalance -= $event->getPrincipalPaid();
        
        // Vérification si le prêt est complètement remboursé
        if ($this->currentBalance <= 0) {
            $this->status = self::STATUS_COMPLETED;
            $this->completedAt = new DateTimeImmutable();
        }
    }

    /**
     * Valide les transitions de statut
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $allowedTransitions = [
            self::STATUS_PENDING => [self::STATUS_UNDER_REVIEW, self::STATUS_REQUIRES_DOCUMENTS, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_REQUIRES_DOCUMENTS],
            self::STATUS_REQUIRES_DOCUMENTS => [self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED],
            self::STATUS_APPROVED => [self::STATUS_FUNDED, self::STATUS_CANCELLED],
            self::STATUS_FUNDED => [self::STATUS_ACTIVE],
            self::STATUS_ACTIVE => [self::STATUS_COMPLETED, self::STATUS_DEFAULTED],
        ];
        
        if (!isset($allowedTransitions[$currentStatus]) || 
            !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new InvalidArgumentException(
                "Invalid status transition from {$currentStatus} to {$newStatus}"
            );
        }
    }

    // Getters
    
    public function getCustomerId(): UuidInterface
    {
        return $this->customerId;
    }

    public function getRequestedAmount(): float
    {
        return $this->requestedAmount;
    }

    public function getApprovedAmount(): float
    {
        return $this->approvedAmount ?? 0;
    }

    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore ?? 0;
    }

    public function getInterestRate(): float
    {
        return $this->interestRate;
    }

    public function getPaymentHistory(): array
    {
        return $this->paymentHistory;
    }

    // Snapshots pour optimisation
    
    public function takeSnapshot(): array
    {
        return [
            'id' => $this->id->toString(),
            'customerId' => $this->customerId->toString(),
            'requestedAmount' => $this->requestedAmount,
            'approvedAmount' => $this->approvedAmount ?? 0,
            'currentBalance' => $this->currentBalance,
            'status' => $this->status,
            'riskScore' => $this->riskScore ?? 0,
            'riskLevel' => $this->riskLevel ?? '',
            'interestRate' => $this->interestRate,
            'paymentHistory' => $this->paymentHistory,
            'version' => $this->version
        ];
    }

    public function restoreFromSnapshot(array $snapshot): void
    {
        $this->id = \Ramsey\Uuid\Uuid::fromString($snapshot['id']);
        $this->customerId = \Ramsey\Uuid\Uuid::fromString($snapshot['customerId']);
        $this->requestedAmount = $snapshot['requestedAmount'];
        $this->approvedAmount = $snapshot['approvedAmount'];
        $this->currentBalance = $snapshot['currentBalance'];
        $this->status = $snapshot['status'];
        $this->riskScore = $snapshot['riskScore'];
        $this->riskLevel = $snapshot['riskLevel'];
        $this->interestRate = $snapshot['interestRate'];
        $this->paymentHistory = $snapshot['paymentHistory'];
        $this->version = $snapshot['version'];
    }
}
