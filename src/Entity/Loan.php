<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\LoanRepository;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $loanNumber = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $loanType = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime',nullable: true)]
    #[Assert\Type(\DateTime::class) ]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\Type(\DateTime::class)]
    #[Assert\GreaterThan(propertyPath: "startDate")]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive()]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $interestRate = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Positive()]
    private ?int $durationMonths = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: ['pending', 'loading', 'success'])]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private string $payStatus;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private string $payContractStatus;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private string $contractStatus;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amountRepaid = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $priceContract;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastPaymentDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[Gedmo\Blameable(on: 'create')]
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $createdBy = null;

    #[Gedmo\Blameable(on: 'update')]
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\ManyToOne(inversedBy: 'loans')]
    private ?User $user = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Media $payFile = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Media $contractSignFile = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bankInfo = null;

    #[ORM\ManyToOne(inversedBy: 'loans')]
    private ?Bank $bank = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $projectName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectDescription = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $projectStartDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $projectEndDate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $projectBudget = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $projectLocation = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $projectManager = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectTeam = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectMilestones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectRisks = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $projectBenefits = null;


    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function setProjectName(?string $projectName): self
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getProjectDescription(): ?string
    {
        return $this->projectDescription;
    }

    public function setProjectDescription(?string $projectDescription): self
    {
        $this->projectDescription = $projectDescription;

        return $this;
    }

    public function getProjectStartDate(): ?\DateTimeInterface
    {
        return $this->projectStartDate;
    }

    public function setProjectStartDate(?\DateTimeInterface $projectStartDate): self
    {
        $this->projectStartDate = $projectStartDate;

        return $this;
    }

    public function getProjectEndDate(): ?\DateTimeInterface
    {
        return $this->projectEndDate;
    }

    public function setProjectEndDate(?\DateTimeInterface $projectEndDate): self
    {
        $this->projectEndDate = $projectEndDate;

        return $this;
    }

    public function getProjectBudget(): ?float
    {
        return $this->projectBudget;
    }

    public function setProjectBudget(?float $projectBudget): self
    {
        $this->projectBudget = $projectBudget;

        return $this;
    }

    public function getProjectLocation(): ?string
    {
        return $this->projectLocation;
    }

    public function setProjectLocation(?string $projectLocation): self
    {
        $this->projectLocation = $projectLocation;

        return $this;
    }

    public function getProjectManager(): ?string
    {
        return $this->projectManager;
    }

    public function setProjectManager(?string $projectManager): self
    {
        $this->projectManager = $projectManager;

        return $this;
    }

    public function getProjectTeam(): ?string
    {
        return $this->projectTeam;
    }

    public function setProjectTeam(?string $projectTeam): self
    {
        $this->projectTeam = $projectTeam;

        return $this;
    }

    public function getProjectMilestones(): ?string
    {
        return $this->projectMilestones;
    }

    public function setProjectMilestones(?string $projectMilestones): self
    {
        $this->projectMilestones = $projectMilestones;

        return $this;
    }

    public function getProjectRisks(): ?string
    {
        return $this->projectRisks;
    }

    public function setProjectRisks(?string $projectRisks): self
    {
        $this->projectRisks = $projectRisks;

        return $this;
    }

    public function getProjectBenefits(): ?string
    {
        return $this->projectBenefits;
    }

    public function setProjectBenefits(?string $projectBenefits): self
    {
        $this->projectBenefits = $projectBenefits;

        return $this;
    }
    

    public function getPayFile(): ?Media
    {
        return $this->payFile;
    }

    public function setPayFile(?Media $payFile): self
    {
        $this->payFile = $payFile;

        return $this;
    }
    public function __construct()
    {
        $this->loanNumber = $this->generateLoanNumber();
        $this->interestRate = 3;

    }

    public function getId(): ?int
    {
        return $this->id;
    }


    private function generateLoanNumber()
    {
        $prefix = 'DOC000';
        $suffix = '';
        $length = 6;
        
        // Générer un nombre aléatoire de six chiffres
        $randomNumber = str_pad(mt_rand(0, 99999999999999), $length, '0', STR_PAD_LEFT);
        
        // Concaténer le préfixe, le nombre aléatoire et le suffixe pour obtenir le chrono complet
        $loanNumber = $prefix . $randomNumber . $suffix;
        
        return $loanNumber;
    }

    public function getLoanNumber(): ?string
    {
        return $this->loanNumber;
    }

    public function setLoanNumber(string $loanNumber): self
    {
        $this->loanNumber = $loanNumber;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        if ($this->startDate && $this->durationMonths) {
            $endDate = clone $this->startDate;
            $endDate->modify('+' . ($this->durationMonths + 3) . ' months');
            return $endDate;
        }
    
        return null;
    }
    
    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(string $interestRate): self
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getDurationMonths(): ?int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): self
    {
        $this->durationMonths = $durationMonths;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getAmountRepaid(): string
    {
         // Calculer le montant mensuel
         $monthlyPayment = $this->calculateMonthlyPayment();

         // Calculer 25% du montant mensuel
         $amountToRepay = $monthlyPayment * 0.25;
        return  (float)$amountToRepay;
    }

    public function setAmountRepaid(string $amountRepaid): self
    {
        $this->amountRepaid = $amountRepaid;
        return $this;
    }

    public function getLastPaymentDate(): ?\DateTimeInterface
    {
        return $this->lastPaymentDate;
    }

    public function setLastPaymentDate(?\DateTimeInterface $lastPaymentDate): self
    {
        $this->lastPaymentDate = $lastPaymentDate;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    // Utility methods

    public function isFullyRepaid(): bool
    {
        return (float)$this->amountRepaid >= (float)$this->amount;
    }

    public function getRemainingAmount(): float
    {
        return max(0, (float)$this->amount - (float)$this->amountRepaid);
    }



    public function calculateMonthlyPayment(): float
    {
        $principal = (float)$this->amount;
        $ratePerMonth = (float)$this->interestRate / 100 / 12;
        $numPayments = $this->durationMonths;

        // If the interest rate is zero, return the principal divided by the number of payments
        if ($ratePerMonth == 0) {
            return $principal / $numPayments;
        }

        // Monthly payment formula for an amortizing loan
        $monthlyPayment = $principal * $ratePerMonth / (1 - pow(1 + $ratePerMonth, -$numPayments));

        return round($monthlyPayment, 2); // Rounded to 2 decimal places
    }
    
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of loanType
     */ 
    public function getLoanType()
    {
        return $this->loanType;
    }

    /**
     * Set the value of loanType
     *
     * @return  self
     */ 
    public function setLoanType($loanType)
    {
        $this->loanType = $loanType;

        return $this;
    }

    /**
     * Get the value of payStatus
     */ 
    public function getPayStatus()
    {
        return $this->payStatus;
    }

    /**
     * Set the value of payStatus
     *
     * @return  self
     */ 
    public function setPayStatus($payStatus)
    {
        $this->payStatus = $payStatus;

        return $this;
    }

    /**
     * Get the value of price
     */ 
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set the value of price
     *
     * @return  self
     */ 
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get the value of bankInfo
     */ 
    public function getBankInfo()
    {
        return $this->bankInfo;
    }

    /**
     * Set the value of bankInfo
     *
     * @return  self
     */ 
    public function setBankInfo($bankInfo)
    {
        $this->bankInfo = $bankInfo;

        return $this;
    }

    /**
     * Get the value of priceContract
     */ 
    public function getPriceContract()
    {
        return $this->priceContract;
    }

    /**
     * Set the value of priceContract
     *
     * @return  self
     */ 
    public function setPriceContract($priceContract)
    {
        $this->priceContract = $priceContract;

        return $this;
    }

    public function getBank(): ?Bank
    {
        return $this->bank;
    }

    public function setBank(?Bank $bank): static
    {
        $this->bank = $bank;

        return $this;
    }

    /**
     * Get the value of payContractStatus
     */ 
    public function getPayContractStatus()
    {
        return $this->payContractStatus;
    }

    /**
     * Set the value of payContractStatus
     *
     * @return  self
     */ 
    public function setPayContractStatus($payContractStatus)
    {
        $this->payContractStatus = $payContractStatus;

        return $this;
    }

    /**
     * Get the value of contractStatus
     */ 
    public function getContractStatus()
    {
        return $this->contractStatus;
    }

    /**
     * Set the value of contractStatus
     *
     * @return  self
     */ 
    public function setContractStatus($contractStatus)
    {
        $this->contractStatus = $contractStatus;

        return $this;
    }

    /**
     * Get the value of contractSignFile
     */ 
    public function getContractSignFile()
    {
        return $this->contractSignFile;
    }

    /**
     * Set the value of contractSignFile
     *
     * @return  self
     */ 
    public function setContractSignFile($contractSignFile)
    {
        $this->contractSignFile = $contractSignFile;

        return $this;
    }
}
