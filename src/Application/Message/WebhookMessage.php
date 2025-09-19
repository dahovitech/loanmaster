<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour les webhooks externes
 */
class WebhookMessage
{
    public function __construct(
        private readonly string $url,
        private readonly array $payload,
        private readonly string $method = 'POST',
        private readonly array $headers = [],
        private readonly int $timeout = 30
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
