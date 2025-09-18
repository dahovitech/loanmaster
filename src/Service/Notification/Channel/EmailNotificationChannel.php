<?php

namespace App\Service\Notification\Channel;

use App\Service\Notification\NotificationResult;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;
use Psr\Log\LoggerInterface;

/**
 * Canal de notification par email
 * Intégration avec Symfony Mailer
 */
class EmailNotificationChannel implements ChannelInterface
{
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private array $configuration;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger,
        array $configuration = []
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->configuration = array_merge([
            'from_email' => 'noreply@loanmaster.local',
            'from_name' => 'LoanMaster',
            'template_path' => 'notifications/email/'
        ], $configuration);
    }

    public function send(string $type, array $recipients, array $data, array $options = []): NotificationResult
    {
        $startTime = microtime(true);
        $deliveredCount = 0;
        $failedCount = 0;
        $errors = [];
        
        foreach ($recipients as $recipient) {
            if (empty($recipient['email'])) {
                $failedCount++;
                $errors[] = 'Missing email address for recipient';
                continue;
            }
            
            try {
                $email = $this->createEmail($type, $recipient, $data, $options);
                $this->mailer->send($email);
                $deliveredCount++;
                
                $this->logger->info('Email sent successfully', [
                    'recipient' => $recipient['email'],
                    'type' => $type
                ]);
                
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Failed to send email to {$recipient['email']}: {$e->getMessage()}";
                
                $this->logger->error('Failed to send email', [
                    'recipient' => $recipient['email'],
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $executionTime = microtime(true) - $startTime;
        
        return new NotificationResult(
            uniqid('email_'),
            $deliveredCount > 0,
            $deliveredCount,
            $failedCount,
            [],
            $executionTime,
            $errors,
            ['channel' => 'email']
        );
    }

    public function isAvailable(): bool
    {
        return true; // Symfony Mailer est toujours disponible
    }

    public function getName(): string
    {
        return 'email';
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function validateData(array $data): bool
    {
        // Validation basique des données email
        return !empty($data);
    }

    public function formatData(array $data, array $options = []): array
    {
        return $data; // Pas de formatage spécial pour email
    }

    private function createEmail(string $type, array $recipient, array $data, array $options): Email
    {
        $template = $options['template'] ?? $type;
        $templatePath = $this->configuration['template_path'] . $template . '.html.twig';
        
        $email = (new TemplatedEmail())
            ->from($this->configuration['from_email'])
            ->to($recipient['email'])
            ->subject($this->getSubject($type, $data))
            ->htmlTemplate($templatePath)
            ->context(array_merge($data, [
                'recipient' => $recipient,
                'notification_type' => $type
            ]));
        
        // Ajout de pièces jointes si nécessaire
        if (!empty($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                $email->attachFromPath($attachment['path'], $attachment['name'] ?? null);
            }
        }
        
        return $email;
    }

    private function getSubject(string $type, array $data): string
    {
        return match ($type) {
            'loan_status_update' => "Mise à jour de votre demande de prêt - {$data['status_display']}",
            'payment_reminder' => 'Rappel de paiement - Prêt LoanMaster',
            'risk_alert' => "Alerte de risque - Niveau {$data['risk_level']}",
            'audit_alert' => 'Alerte de sécurité - LoanMaster',
            default => 'Notification LoanMaster'
        };
    }
}
