<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour générer des rapports en arrière-plan
 */
class GenerateReportMessage
{
    public function __construct(
        private readonly string $reportType,
        private readonly array $parameters = [],
        private readonly ?int $userId = null,
        private readonly string $format = 'pdf'
    ) {}

    public function getReportType(): string
    {
        return $this->reportType;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
