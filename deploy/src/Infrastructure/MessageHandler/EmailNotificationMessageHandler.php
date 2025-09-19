<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageHandler;

use App\Application\Message\EmailNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Handler pour envoyer des emails en arrière-plan
 */
#[AsMessageHandler]
class EmailNotificationMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = 'noreply@loanmaster.com'
    ) {}

    public function __invoke(EmailNotificationMessage $message): void
    {
        $this->logger->info('Processing email notification', [
            'to' => $message->getTo(),
            'subject' => $message->getSubject(),
            'template' => $message->getTemplate()
        ]);

        try {
            // Rendu du template email
            $htmlContent = $this->twig->render(
                'emails/' . $message->getTemplate() . '.html.twig',
                $message->getTemplateData()
            );

            $textContent = $this->twig->render(
                'emails/' . $message->getTemplate() . '.txt.twig',
                $message->getTemplateData()
            );

            // Création de l'email
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($message->getTo())
                ->subject($message->getSubject())
                ->html($htmlContent)
                ->text($textContent);

            // Ajout des pièces jointes
            foreach ($message->getAttachments() as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $email->attachFromPath(
                        $attachment['path'],
                        $attachment['name'] ?? null,
                        $attachment['mime'] ?? null
                    );
                }
            }

            // Envoi de l'email
            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
