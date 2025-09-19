<?php

namespace App\Entity;

use App\Repository\LoanScoringModelRepository;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * Entité pour les modèles de scoring ML
 */
#[ORM\Entity(repositoryClass: LoanScoringModelRepository::class)]
#[ORM\Table(name: 'loan_scoring_models')]
#[ORM\Index(name: 'idx_model_status', columns: ['status'])]
#[ORM\Index(name: 'idx_model_version', columns: ['version'])]
#[ORM\Index(name: 'idx_model_created', columns: ['created_at'])]
class LoanScoringModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $modelId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $version;

    #[ORM\Column(type: 'string', length: 50)]
    private string $algorithm;

    #[ORM\Column(type: 'json')]
    private array $performanceMetrics = [];

    #[ORM\Column(type: 'json')]
    private array $modelData = [];

    #[ORM\Column(type: 'json')]
    private array $featureImportance = [];

    #[ORM\Column(type: 'json')]
    private array $trainingOptions = [];

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'training';

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deployedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $retiredAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $trainingSamples = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $accuracy = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $precision = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $recall = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $f1Score = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $auc = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $validationResults = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $driftMetrics = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $usageCount = 0;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function setModelId(string $modelId): self
    {
        $this->modelId = $modelId;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    public function setPerformanceMetrics(array $performanceMetrics): self
    {
        $this->performanceMetrics = $performanceMetrics;
        
        // Extraction des métriques principales pour indexation
        if (isset($performanceMetrics['accuracy'])) {
            $this->accuracy = $performanceMetrics['accuracy'];
        }
        if (isset($performanceMetrics['precision'])) {
            $this->precision = $performanceMetrics['precision'];
        }
        if (isset($performanceMetrics['recall'])) {
            $this->recall = $performanceMetrics['recall'];
        }
        if (isset($performanceMetrics['f1_score'])) {
            $this->f1Score = $performanceMetrics['f1_score'];
        }
        if (isset($performanceMetrics['auc'])) {
            $this->auc = $performanceMetrics['auc'];
        }
        if (isset($performanceMetrics['total_samples'])) {
            $this->trainingSamples = $performanceMetrics['total_samples'];
        }
        
        return $this;
    }

    public function getModelData(): array
    {
        return $this->modelData;
    }

    public function setModelData(array $modelData): self
    {
        $this->modelData = $modelData;
        return $this;
    }

    public function getFeatureImportance(): array
    {
        return $this->featureImportance;
    }

    public function setFeatureImportance(array $featureImportance): self
    {
        $this->featureImportance = $featureImportance;
        return $this;
    }

    public function getTrainingOptions(): array
    {
        return $this->trainingOptions;
    }

    public function setTrainingOptions(array $trainingOptions): self
    {
        $this->trainingOptions = $trainingOptions;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getDeployedAt(): ?DateTimeImmutable
    {
        return $this->deployedAt;
    }

    public function setDeployedAt(?DateTimeImmutable $deployedAt): self
    {
        $this->deployedAt = $deployedAt;
        return $this;
    }

    public function getRetiredAt(): ?DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function setRetiredAt(?DateTimeImmutable $retiredAt): self
    {
        $this->retiredAt = $retiredAt;
        return $this;
    }

    public function getTrainingSamples(): ?int
    {
        return $this->trainingSamples;
    }

    public function setTrainingSamples(?int $trainingSamples): self
    {
        $this->trainingSamples = $trainingSamples;
        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(?float $accuracy): self
    {
        $this->accuracy = $accuracy;
        return $this;
    }

    public function getPrecision(): ?float
    {
        return $this->precision;
    }

    public function setPrecision(?float $precision): self
    {
        $this->precision = $precision;
        return $this;
    }

    public function getRecall(): ?float
    {
        return $this->recall;
    }

    public function setRecall(?float $recall): self
    {
        $this->recall = $recall;
        return $this;
    }

    public function getF1Score(): ?float
    {
        return $this->f1Score;
    }

    public function setF1Score(?float $f1Score): self
    {
        $this->f1Score = $f1Score;
        return $this;
    }

    public function getAuc(): ?float
    {
        return $this->auc;
    }

    public function setAuc(?float $auc): self
    {
        $this->auc = $auc;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    public function setValidationResults(array $validationResults): self
    {
        $this->validationResults = $validationResults;
        return $this;
    }

    public function getDriftMetrics(): array
    {
        return $this->driftMetrics;
    }

    public function setDriftMetrics(array $driftMetrics): self
    {
        $this->driftMetrics = $driftMetrics;
        return $this;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): self
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsageCount(): self
    {
        $this->usageCount++;
        $this->lastUsedAt = new DateTimeImmutable();
        return $this;
    }

    public function isDeployed(): bool
    {
        return $this->status === 'deployed';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['deployed', 'trained']);
    }

    public function getDaysInProduction(): ?int
    {
        if (!$this->deployedAt) {
            return null;
        }

        return (new \DateTime())->diff($this->deployedAt)->days;
    }

    public function getQualityScore(): float
    {
        // Score composite basé sur les métriques de performance
        $weights = [
            'accuracy' => 0.3,
            'precision' => 0.2,
            'recall' => 0.2,
            'f1_score' => 0.2,
            'auc' => 0.1
        ];

        $score = 0;
        $totalWeight = 0;

        foreach ($weights as $metric => $weight) {
            $property = $metric === 'f1_score' ? 'f1Score' : $metric;
            $value = $this->{'get' . ucfirst($property)}();
            
            if ($value !== null) {
                $score += $value * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? $score / $totalWeight : 0;
    }

    public function getTopFeatures(int $limit = 5): array
    {
        if (empty($this->featureImportance)) {
            return [];
        }

        $importance = $this->featureImportance;
        arsort($importance);

        return array_slice($importance, 0, $limit, true);
    }

    public function needsRetraining(): bool
    {
        // Vérification si le modèle a besoin d'être réentraîné
        $maxDaysInProduction = 90; // 3 mois
        $minAccuracy = 0.8;

        if ($this->getDaysInProduction() > $maxDaysInProduction) {
            return true;
        }

        if ($this->accuracy && $this->accuracy < $minAccuracy) {
            return true;
        }

        if (!empty($this->driftMetrics) && 
            isset($this->driftMetrics['drift_detected']) && 
            $this->driftMetrics['drift_detected']) {
            return true;
        }

        return false;
    }

    public function getPerformanceSummary(): array
    {
        return [
            'accuracy' => $this->accuracy ? round($this->accuracy * 100, 2) . '%' : 'N/A',
            'precision' => $this->precision ? round($this->precision * 100, 2) . '%' : 'N/A',
            'recall' => $this->recall ? round($this->recall * 100, 2) . '%' : 'N/A',
            'f1_score' => $this->f1Score ? round($this->f1Score * 100, 2) . '%' : 'N/A',
            'auc' => $this->auc ? round($this->auc, 3) : 'N/A',
            'quality_score' => round($this->getQualityScore() * 100, 1) . '%',
            'training_samples' => $this->trainingSamples ?: 'N/A',
            'days_in_production' => $this->getDaysInProduction() ?: 0,
            'usage_count' => $this->usageCount,
            'needs_retraining' => $this->needsRetraining()
        ];
    }
}
