<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * Message pour envoyer des notifications email en arrière-plan
 */
class EmailNotificationMessage
{
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $template,
        private readonly array $templateData = [],
        private readonly array $attachments = []
    ) {}

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
