<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour calculer les statistiques en arrière-plan
 */
class CalculateStatisticsMessage
{
    public function __construct(
        private readonly string $statisticsType,
        private readonly array $filters = [],
        private readonly \DateTimeInterface $fromDate = new \DateTime('-30 days'),
        private readonly ?\DateTimeInterface $toDate = null
    ) {}

    public function getStatisticsType(): string
    {
        return $this->statisticsType;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFromDate(): \DateTimeInterface
    {
        return $this->fromDate;
    }

    public function getToDate(): ?\DateTimeInterface
    {
        return $this->toDate ?? new \DateTime();
    }
}
