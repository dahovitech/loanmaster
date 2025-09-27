<?php

namespace App\Service\Mail;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

/**
 * Service for handling email operations with settings integration
 */
class EmailService
{
    private ?Setting $setting = null;

    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Send templated email with error handling and logging
     */
    public function sendTemplatedEmail(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $from = null
    ): bool {
        try {
            $email = (new TemplatedEmail())
                ->from($from ? new Address($from) : $this->getDefaultFromAddress())
                ->to(new Address($to))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);

            $this->mailer->send($email);
            
            $this->logger->info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'template' => $template
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get application setting with caching
     */
    public function getSetting(): ?Setting
    {
        if ($this->setting === null) {
            $this->setting = $this->entityManager
                ->getRepository(Setting::class)
                ->findOneBy([]);
        }

        return $this->setting;
    }

    /**
     * Get default email address from settings
     */
    private function getDefaultFromAddress(): Address
    {
        $setting = $this->getSetting();
        
        if ($setting && $setting->getEmail()) {
            return new Address($setting->getEmail(), $setting->getName() ?? 'LoanMaster');
        }

        return new Address('noreply@loanmaster.com', 'LoanMaster');
    }

    /**
     * Clear setting cache (useful after settings update)
     */
    public function clearSettingCache(): void
    {
        $this->setting = null;
    }
}