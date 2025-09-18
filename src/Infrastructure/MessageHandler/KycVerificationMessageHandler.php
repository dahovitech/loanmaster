<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageHandler;

use App\Application\Message\KycVerificationMessage;
use App\Application\Service\WorkflowService;
use App\Domain\Repository\UserKycRepositoryInterface;
use App\Infrastructure\Service\NotificationService;
use App\Infrastructure\Service\ExternalKycService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour traiter la vérification KYC en arrière-plan
 */
#[AsMessageHandler]
class KycVerificationMessageHandler
{
    public function __construct(
        private readonly UserKycRepositoryInterface $kycRepository,
        private readonly WorkflowService $workflowService,
        private readonly NotificationService $notificationService,
        private readonly ExternalKycService $externalKycService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(KycVerificationMessage $message): void
    {
        $this->logger->info('Processing KYC verification', [
            'kyc_id' => $message->getKycId(),
            'verification_type' => $message->getVerificationType()
        ]);

        try {
            $kyc = $this->kycRepository->find($message->getKycId());
            if (!$kyc) {
                throw new \RuntimeException("KYC {$message->getKycId()} not found");
            }

            // Vérifier si la transition est possible
            if (!$this->workflowService->canApplyKycTransition($kyc, 'start_verification')) {
                throw new \RuntimeException("Cannot start verification for KYC {$message->getKycId()}");
            }

            // Démarrer la vérification
            $this->workflowService->applyKycTransition($kyc, 'start_verification');

            // Traitement selon le type de vérification
            $verificationResult = match ($message->getVerificationType()) {
                'automatic' => $this->performAutomaticVerification($kyc),
                'manual' => $this->scheduleManualVerification($kyc),
                'hybrid' => $this->performHybridVerification($kyc),
                default => throw new \InvalidArgumentException("Unknown verification type: {$message->getVerificationType()}")
            };

            // Appliquer le résultat
            if ($verificationResult['success']) {
                $this->workflowService->applyKycTransition($kyc, 'verify');
                $kyc->setVerificationScore($verificationResult['score'] ?? 100);
                $kyc->setVerificationNotes($verificationResult['notes'] ?? '');
                
                $this->logger->info('KYC verification successful', [
                    'kyc_id' => $message->getKycId(),
                    'score' => $verificationResult['score'] ?? 100
                ]);

                // Notification de succès
                $this->notificationService->sendKycVerifiedNotification($kyc->getUser());

                // Vérifier si des prêts en attente peuvent être traités
                $this->triggerPendingLoansReview($kyc->getUser());

            } else {
                $this->workflowService->applyKycTransition($kyc, 'reject_kyc');
                $kyc->setRejectionReason($verificationResult['reason'] ?? 'Verification failed');
                
                $this->logger->warning('KYC verification failed', [
                    'kyc_id' => $message->getKycId(),
                    'reason' => $verificationResult['reason'] ?? 'Unknown'
                ]);

                // Notification d'échec
                $this->notificationService->sendKycRejectedNotification($kyc->getUser());
            }

            $this->entityManager->flush();

        } catch (\Exception $e) {
            $this->logger->error('KYC verification processing failed', [
                'kyc_id' => $message->getKycId(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function performAutomaticVerification($kyc): array
    {
        $this->logger->info('Performing automatic KYC verification', [
            'kyc_id' => $kyc->getId()
        ]);

        try {
            // Vérification automatique via service externe
            $result = $this->externalKycService->verifyDocuments($kyc);
            
            // Scoring automatique basé sur différents critères
            $score = $this->calculateVerificationScore($kyc, $result);
            
            return [
                'success' => $score >= 70, // Seuil de 70%
                'score' => $score,
                'notes' => "Automatic verification completed. Score: {$score}/100",
                'details' => $result
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'reason' => 'Automatic verification service error: ' . $e->getMessage()
            ];
        }
    }

    private function scheduleManualVerification($kyc): array
    {
        $this->logger->info('Scheduling manual KYC verification', [
            'kyc_id' => $kyc->getId()
        ]);

        // Créer une tâche pour vérification manuelle
        $kyc->setManualReviewRequired(true);
        $kyc->setManualReviewAssignedAt(new \DateTimeImmutable());

        // Notifier l'équipe de vérification
        $this->notificationService->sendKycManualReviewRequest($kyc);

        return [
            'success' => false, // En attente de vérification manuelle
            'reason' => 'Manual verification scheduled',
            'manual_review_required' => true
        ];
    }

    private function performHybridVerification($kyc): array
    {
        $this->logger->info('Performing hybrid KYC verification', [
            'kyc_id' => $kyc->getId()
        ]);

        // Commencer par la vérification automatique
        $autoResult = $this->performAutomaticVerification($kyc);
        
        // Si le score automatique est entre 40 et 80, demander une vérification manuelle
        if (isset($autoResult['score']) && $autoResult['score'] >= 40 && $autoResult['score'] < 80) {
            $manualResult = $this->scheduleManualVerification($kyc);
            
            return [
                'success' => false,
                'reason' => 'Requires manual review after automatic verification',
                'auto_score' => $autoResult['score'],
                'manual_review_required' => true
            ];
        }

        return $autoResult;
    }

    private function calculateVerificationScore($kyc, array $externalResult): int
    {
        $score = 0;

        // Vérification de l'identité (30 points)
        if ($externalResult['identity_verified'] ?? false) {
            $score += 30;
        }

        // Vérification de l'adresse (20 points)
        if ($externalResult['address_verified'] ?? false) {
            $score += 20;
        }

        // Vérification des documents (25 points)
        if ($externalResult['documents_valid'] ?? false) {
            $score += 25;
        }

        // Vérification de la photo (15 points)
        if ($externalResult['photo_verified'] ?? false) {
            $score += 15;
        }

        // Checks supplémentaires (10 points)
        if ($externalResult['additional_checks_passed'] ?? false) {
            $score += 10;
        }

        return min($score, 100);
    }

    private function triggerPendingLoansReview($user): void
    {
        // Déclencher la révision des prêts en attente pour cet utilisateur
        $this->logger->info('Triggering pending loans review after KYC verification', [
            'user_id' => $user->getId()
        ]);

        // Cette logique pourrait dispatcher d'autres messages pour traiter les prêts en attente
    }
}
