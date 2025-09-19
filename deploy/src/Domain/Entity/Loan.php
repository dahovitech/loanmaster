<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Event\DomainEventInterface;
use App\Domain\Event\LoanApplicationCreated;
use App\Domain\Event\LoanStatusChanged;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\InterestRate;
use App\Domain\ValueObject\LoanId;
use App\Domain\ValueObject\LoanStatus;
use App\Domain\ValueObject\LoanType;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

class Loan
{
    /** @var DomainEventInterface[] */
    private array $domainEvents = [];
    
    private function __construct(
        private LoanId $id,
        private UserId $userId,
        private string $number,
        private LoanType $type,
        private Amount $amount,
        private Duration $duration,
        private InterestRate $interestRate,
        private LoanStatus $status,
        private ?string $projectDescription,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $approvedAt = null,
        private ?DateTimeImmutable $fundedAt = null
    ) {}
    
    public static function create(
        LoanId $id,
        UserId $userId,
        string $number,
        LoanType $type,
        Amount $amount,
        Duration $duration,
        ?string $projectDescription = null
    ): self {
        // Validation des règles métier
        if ($amount->getValue() > $type->getMaxAmount()) {
            throw new InvalidArgumentException(
                sprintf('Amount %.2f exceeds maximum for %s loans (%.2f)', 
                    $amount->getValue(), 
                    $type->getLabel(), 
                    $type->getMaxAmount()
                )
            );
        }
        
        if ($duration->getMonths() > $type->getMaxDurationMonths()) {
            throw new InvalidArgumentException(
                sprintf('Duration %d months exceeds maximum for %s loans (%d months)', 
                    $duration->getMonths(), 
                    $type->getLabel(), 
                    $type->getMaxDurationMonths()
                )
            );
        }
        
        $interestRate = InterestRate::fromDecimal($type->getBaseInterestRate());
        $createdAt = new DateTimeImmutable();
        
        $loan = new self(
            $id,
            $userId,
            $number,
            $type,
            $amount,
            $duration,
            $interestRate,
            LoanStatus::PENDING,
            $projectDescription,
            $createdAt
        );
        
        $loan->recordEvent(new LoanApplicationCreated(
            $id,
            $userId,
            $number
        ));
        
        return $loan;
    }
    
    public function approve(): void
    {
        $this->changeStatus(LoanStatus::APPROVED);
        $this->approvedAt = new DateTimeImmutable();
    }
    
    public function reject(string $reason = null): void
    {
        $this->changeStatus(LoanStatus::REJECTED);
    }
    
    public function fund(): void
    {
        $this->changeStatus(LoanStatus::FUNDED);
        $this->fundedAt = new DateTimeImmutable();
    }
    
    public function activate(): void
    {
        if ($this->status !== LoanStatus::FUNDED) {
            throw new InvalidArgumentException('Loan must be funded before activation');
        }
        
        $this->changeStatus(LoanStatus::ACTIVE);
    }
    
    public function complete(): void
    {
        $this->changeStatus(LoanStatus::COMPLETED);
    }
    
    public function markAsDefault(): void
    {
        $this->changeStatus(LoanStatus::DEFAULTED);
    }
    
    public function cancel(): void
    {
        if ($this->status->isFinal()) {
            throw new InvalidArgumentException('Cannot cancel a finalized loan');
        }
        
        $this->changeStatus(LoanStatus::CANCELLED);
    }
    
    private function changeStatus(LoanStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                sprintf('Cannot transition from %s to %s', 
                    $this->status->getLabel(), 
                    $newStatus->getLabel()
                )
            );
        }
        
        $previousStatus = $this->status;
        $this->status = $newStatus;
        
        $this->recordEvent(new LoanStatusChanged(
            $this->id,
            $previousStatus,
            $newStatus
        ));
    }
    
    public function calculateMonthlyPayment(): Amount
    {
        return $this->amount->calculateInterest($this->interestRate, $this->duration);
    }
    
    public function calculateTotalAmount(): Amount
    {
        $monthlyPayment = $this->calculateMonthlyPayment();
        return $monthlyPayment->multiply($this->duration->getMonths());
    }
    
    public function calculateTotalInterest(): Amount
    {
        return $this->calculateTotalAmount()->subtract($this->amount);
    }
    
    private function recordEvent(DomainEventInterface $event): void
    {
        $this->domainEvents[] = $event;
    }
    
    /**
     * @return DomainEventInterface[]
     */
    public function getUncommittedEvents(): array
    {
        return $this->domainEvents;
    }
    
    public function markEventsAsCommitted(): void
    {
        $this->domainEvents = [];
    }
    
    // Getters
    public function getId(): LoanId
    {
        return $this->id;
    }
    
    public function getUserId(): UserId
    {
        return $this->userId;
    }
    
    public function getNumber(): string
    {
        return $this->number;
    }
    
    public function getType(): LoanType
    {
        return $this->type;
    }
    
    public function getAmount(): Amount
    {
        return $this->amount;
    }
    
    public function getDuration(): Duration
    {
        return $this->duration;
    }
    
    public function getInterestRate(): InterestRate
    {
        return $this->interestRate;
    }
    
    public function getStatus(): LoanStatus
    {
        return $this->status;
    }
    
    public function getProjectDescription(): ?string
    {
        return $this->projectDescription;
    }
    
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }
    
    public function getFundedAt(): ?DateTimeImmutable
    {
        return $this->fundedAt;
    }
}
