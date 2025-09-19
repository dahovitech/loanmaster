<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Entity\User;
use App\Domain\Entity\Loan;
use Psr\Log\LoggerInterface;

/**
 * Service de notification pour les événements workflow
 */
class NotificationService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function sendLoanSubmittedNotification(User $user, Loan $loan): void
    {
        $this->logger->info('Loan submitted notification', [
            'user_id' => $user->getId(),
            'loan_id' => $loan->getId()
        ]);
        
        // Logique d'envoi de notification
        // Email, SMS, push notification, etc.
    }

    public function sendLoanApprovedNotification(User $user, Loan $loan): void
    {
        $this->logger->info('Loan approved notification', [
            'user_id' => $user->getId(),
            'loan_id' => $loan->getId()
        ]);
    }

    public function sendLoanRejectedNotification(User $user, Loan $loan): void
    {
        $this->logger->info('Loan rejected notification', [
            'user_id' => $user->getId(),
            'loan_id' => $loan->getId()
        ]);
    }

    public function sendLoanDisbursedNotification(User $user, Loan $loan): void
    {
        $this->logger->info('Loan disbursed notification', [
            'user_id' => $user->getId(),
            'loan_id' => $loan->getId()
        ]);
    }

    public function sendKycVerifiedNotification(User $user): void
    {
        $this->logger->info('KYC verified notification', [
            'user_id' => $user->getId()
        ]);
    }

    public function sendKycRejectedNotification(User $user): void
    {
        $this->logger->info('KYC rejected notification', [
            'user_id' => $user->getId()
        ]);
    }

    public function sendLoanStatusNotification(User $user, string $status, Loan $loan): void
    {
        $this->logger->info('Loan status change notification', [
            'user_id' => $user->getId(),
            'loan_id' => $loan->getId(),
            'new_status' => $status
        ]);
    }
}
