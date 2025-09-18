<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Domain\Event\LoanStatusChangedEvent;
use App\Infrastructure\Service\NotificationService;
use App\Infrastructure\Service\AuditLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\LeaveEvent;

/**
 * Gestionnaire d'événements pour les workflows
 */
class WorkflowEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Événements de workflow Symfony
            'workflow.loan_lifecycle.guard' => 'onLoanGuard',
            'workflow.loan_lifecycle.transition' => 'onLoanTransition',
            'workflow.loan_lifecycle.entered' => 'onLoanEntered',
            'workflow.loan_lifecycle.leave' => 'onLoanLeave',
            
            // Événements KYC
            'workflow.kyc_validation.transition' => 'onKycTransition',
            'workflow.kyc_validation.entered' => 'onKycEntered',
            
            // Événements métier personnalisés
            LoanStatusChangedEvent::NAME => 'onLoanStatusChanged',
        ];
    }

    /**
     * Garde pour valider les transitions de prêt
     */
    public function onLoanGuard(GuardEvent $event): void
    {
        $loan = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->debug('Workflow guard check', [
            'loan_id' => $loan->getId(),
            'transition' => $transition,
            'current_status' => $loan->getStatus()
        ]);

        // Validations métier spécifiques
        switch ($transition) {
            case 'approve':
                if (!$loan->getUser()->getKyc()?->isVerified()) {
                    $event->setBlocked(true, 'KYC utilisateur non vérifié');
                    return;
                }
                
                if ($loan->getAmount() <= 0) {
                    $event->setBlocked(true, 'Montant du prêt invalide');
                    return;
                }
                break;

            case 'disburse':
                if (!$loan->getBankDetails()) {
                    $event->setBlocked(true, 'Détails bancaires manquants');
                    return;
                }
                break;

            case 'mark_default':
                // Vérifier qu'il y a effectivement un retard de paiement
                if (!$this->hasPaymentDelay($loan)) {
                    $event->setBlocked(true, 'Aucun retard de paiement détecté');
                    return;
                }
                break;
        }
    }

    /**
     * Événement lors d'une transition de prêt
     */
    public function onLoanTransition(TransitionEvent $event): void
    {
        $loan = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->info('Loan workflow transition', [
            'loan_id' => $loan->getId(),
            'transition' => $transition,
            'from' => $event->getMarking()->getPlaces(),
            'to' => $event->getTransition()->getTos()
        ]);

        // Actions spécifiques selon la transition
        switch ($transition) {
            case 'submit':
                $this->onLoanSubmitted($loan);
                break;
            case 'approve':
                $this->onLoanApproved($loan);
                break;
            case 'reject':
                $this->onLoanRejected($loan);
                break;
            case 'disburse':
                $this->onLoanDisbursed($loan);
                break;
        }
    }

    /**
     * Événement lors de l'entrée dans un état
     */
    public function onLoanEntered(EnteredEvent $event): void
    {
        $loan = $event->getSubject();
        $place = $event->getTransition()->getTos()[0] ?? 'unknown';

        $this->logger->debug('Loan entered state', [
            'loan_id' => $loan->getId(),
            'state' => $place
        ]);

        // Mettre à jour les timestamps
        $this->updateLoanTimestamps($loan, $place);
    }

    /**
     * Événement lors de la sortie d'un état
     */
    public function onLoanLeave(LeaveEvent $event): void
    {
        $loan = $event->getSubject();
        $place = $event->getTransition()->getFroms()[0] ?? 'unknown';

        $this->auditLogService->logWorkflowTransition(
            'loan',
            $loan->getId(),
            $place,
            $event->getTransition()->getName()
        );
    }

    /**
     * Gestion des transitions KYC
     */
    public function onKycTransition(TransitionEvent $event): void
    {
        $kyc = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger->info('KYC workflow transition', [
            'kyc_id' => $kyc->getId(),
            'user_id' => $kyc->getUser()->getId(),
            'transition' => $transition
        ]);

        // Notifications selon l'état KYC
        switch ($transition) {
            case 'verify':
                $this->notificationService->sendKycVerifiedNotification($kyc->getUser());
                break;
            case 'reject_kyc':
                $this->notificationService->sendKycRejectedNotification($kyc->getUser());
                break;
        }
    }

    /**
     * Gestion de l'entrée dans un état KYC
     */
    public function onKycEntered(EnteredEvent $event): void
    {
        $kyc = $event->getSubject();
        $place = $event->getTransition()->getTos()[0] ?? 'unknown';

        if ($place === 'verified') {
            // Auto-activer les prêts en attente si KYC validé
            $this->triggerPendingLoansReview($kyc->getUser());
        }
    }

    /**
     * Gestionnaire pour l'événement métier de changement de statut
     */
    public function onLoanStatusChanged(LoanStatusChangedEvent $event): void
    {
        $loan = $event->getLoan();
        
        // Log d'audit détaillé
        $this->auditLogService->logLoanStatusChange($event->getTransitionContext());
        
        // Notifications utilisateur
        $this->notificationService->sendLoanStatusNotification(
            $loan->getUser(),
            $event->getNewStatus(),
            $loan
        );
        
        // Métriques et analytics
        $this->recordLoanTransitionMetrics($event);
    }

    /**
     * Actions spécifiques lors de la soumission d'un prêt
     */
    private function onLoanSubmitted($loan): void
    {
        $loan->setSubmittedAt(new \DateTimeImmutable());
        $this->notificationService->sendLoanSubmittedNotification($loan->getUser(), $loan);
    }

    /**
     * Actions lors de l'approbation d'un prêt
     */
    private function onLoanApproved($loan): void
    {
        $loan->setApprovedAt(new \DateTimeImmutable());
        $this->notificationService->sendLoanApprovedNotification($loan->getUser(), $loan);
        
        // Programmer le débours automatique si configuré
        $this->scheduleAutomaticDisbursement($loan);
    }

    /**
     * Actions lors du rejet d'un prêt
     */
    private function onLoanRejected($loan): void
    {
        $loan->setRejectedAt(new \DateTimeImmutable());
        $this->notificationService->sendLoanRejectedNotification($loan->getUser(), $loan);
    }

    /**
     * Actions lors du débours d'un prêt
     */
    private function onLoanDisbursed($loan): void
    {
        $loan->setDisbursedAt(new \DateTimeImmutable());
        $this->notificationService->sendLoanDisbursedNotification($loan->getUser(), $loan);
        
        // Créer le planning de remboursement
        $this->createRepaymentSchedule($loan);
    }

    /**
     * Met à jour les timestamps du prêt selon l'état
     */
    private function updateLoanTimestamps($loan, string $state): void
    {
        $now = new \DateTimeImmutable();
        
        switch ($state) {
            case 'under_review':
                $loan->setReviewStartedAt($now);
                break;
            case 'active':
                $loan->setActivatedAt($now);
                break;
            case 'completed':
                $loan->setCompletedAt($now);
                break;
        }
    }

    /**
     * Vérifie s'il y a un retard de paiement
     */
    private function hasPaymentDelay($loan): bool
    {
        // Logique de vérification des retards
        return true; // Placeholder
    }

    /**
     * Déclenche la révision des prêts en attente
     */
    private function triggerPendingLoansReview($user): void
    {
        // Logique pour relancer l'évaluation des prêts en attente
    }

    /**
     * Enregistre les métriques de transition
     */
    private function recordLoanTransitionMetrics(LoanStatusChangedEvent $event): void
    {
        // Enregistrement des métriques pour analytics
    }

    /**
     * Programme le débours automatique
     */
    private function scheduleAutomaticDisbursement($loan): void
    {
        // Logique de programmation du débours automatique
    }

    /**
     * Crée le planning de remboursement
     */
    private function createRepaymentSchedule($loan): void
    {
        // Logique de création du planning de remboursement
    }
}
