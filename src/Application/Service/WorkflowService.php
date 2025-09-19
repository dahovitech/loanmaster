<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Loan;
use App\Domain\Entity\User;
use App\Domain\Entity\UserKyc;
use App\Domain\Event\LoanStatusChangedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Registry;

/**
 * Service de gestion des workflows métier
 */
class WorkflowService
{
    public function __construct(
        private readonly Registry $workflowRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Applique une transition sur un prêt avec validation
     */
    public function applyLoanTransition(Loan $loan, string $transition, ?User $user = null): bool
    {
        $workflow = $this->workflowRegistry->get($loan, 'loan_lifecycle');
        
        if (!$workflow->can($loan, $transition)) {
            return false;
        }

        $previousState = $loan->getStatus();
        
        // Appliquer la transition
        $workflow->apply($loan, $transition);
        
        // Sauvegarder les changements
        $this->entityManager->flush();
        
        // Dispatcher l'événement de changement d'état
        $this->eventDispatcher->dispatch(
            new LoanStatusChangedEvent($loan, $previousState, $loan->getStatus(), $user),
            LoanStatusChangedEvent::NAME
        );
        
        return true;
    }

    /**
     * Obtient les transitions possibles pour un prêt
     */
    public function getAvailableLoanTransitions(Loan $loan): array
    {
        $workflow = $this->workflowRegistry->get($loan, 'loan_lifecycle');
        return $workflow->getEnabledTransitions($loan);
    }

    /**
     * Vérifie si une transition est possible
     */
    public function canApplyLoanTransition(Loan $loan, string $transition): bool
    {
        $workflow = $this->workflowRegistry->get($loan, 'loan_lifecycle');
        return $workflow->can($loan, $transition);
    }

    /**
     * Obtient l'historique des transitions d'un prêt
     */
    public function getLoanWorkflowHistory(Loan $loan): array
    {
        // Cette méthode utiliserait l'audit trail du workflow
        // Pour l'instant, retour d'un tableau vide
        return [];
    }

    /**
     * Workflow KYC - Applique une transition
     */
    public function applyKycTransition(UserKyc $kyc, string $transition, ?User $user = null): bool
    {
        $workflow = $this->workflowRegistry->get($kyc, 'kyc_validation');
        
        if (!$workflow->can($kyc, $transition)) {
            return false;
        }

        $previousStatus = $kyc->getStatus();
        $workflow->apply($kyc, $transition);
        
        // Mettre à jour les timestamps selon la transition
        $this->updateKycTimestamps($kyc, $transition);
        
        $this->entityManager->flush();
        
        return true;
    }

    /**
     * Soumission automatique d'un prêt selon les conditions
     */
    public function autoSubmitLoanIfReady(Loan $loan): bool
    {
        // Vérifications automatiques avant soumission
        if (!$this->isLoanReadyForSubmission($loan)) {
            return false;
        }

        return $this->applyLoanTransition($loan, 'submit');
    }

    /**
     * Workflow intelligent : transition automatique vers review si conditions remplies
     */
    public function autoStartReviewIfEligible(Loan $loan): bool
    {
        // Vérifier l'éligibilité automatique
        if (!$this->isLoanEligibleForAutoReview($loan)) {
            return false;
        }

        return $this->applyLoanTransition($loan, 'start_review');
    }

    /**
     * Obtient un résumé du statut avec métadonnées
     */
    public function getLoanStatusSummary(Loan $loan): array
    {
        $workflow = $this->workflowRegistry->get($loan, 'loan_lifecycle');
        $currentPlace = $workflow->getMarking($loan)->getPlaces();
        
        $transitions = $this->getAvailableLoanTransitions($loan);
        
        return [
            'current_status' => $loan->getStatus(),
            'current_places' => array_keys($currentPlace),
            'available_transitions' => array_map(fn($t) => [
                'name' => $t->getName(),
                'metadata' => $t->getMetadata()
            ], $transitions),
            'workflow_name' => 'loan_lifecycle'
        ];
    }

    /**
     * Validation des pré-conditions pour les transitions complexes
     */
    public function validateTransitionPreConditions(Loan $loan, string $transition): array
    {
        $errors = [];

        switch ($transition) {
            case 'approve':
                if (!$loan->getUser()->getKyc()?->isVerified()) {
                    $errors[] = 'KYC utilisateur non vérifié';
                }
                if ($loan->getAmount() <= 0) {
                    $errors[] = 'Montant du prêt invalide';
                }
                break;
                
            case 'disburse':
                if (!$loan->getBankDetails()) {
                    $errors[] = 'Détails bancaires manquants';
                }
                break;
                
            case 'activate':
                if (!$loan->getDisbursedAt()) {
                    $errors[] = 'Date de débours manquante';
                }
                break;
        }

        return $errors;
    }

    /**
     * Met à jour les timestamps KYC selon la transition
     */
    private function updateKycTimestamps(UserKyc $kyc, string $transition): void
    {
        $now = new \DateTimeImmutable();
        
        switch ($transition) {
            case 'start_verification':
                $kyc->setVerificationStartedAt($now);
                break;
            case 'verify':
                $kyc->setVerifiedAt($now);
                break;
            case 'reject_kyc':
                $kyc->setRejectedAt($now);
                break;
        }
    }

    /**
     * Vérifie si un prêt est prêt pour soumission
     */
    private function isLoanReadyForSubmission(Loan $loan): bool
    {
        return $loan->getAmount() > 0 
            && $loan->getUser()->getKyc()?->getStatus() === 'verified'
            && $loan->getDescription() !== null;
    }

    /**
     * Vérifie l'éligibilité pour review automatique
     */
    private function isLoanEligibleForAutoReview(Loan $loan): bool
    {
        // Conditions pour review automatique (montants faibles, bon historique, etc.)
        return $loan->getAmount() < 5000 
            && $loan->getUser()->getKyc()?->isVerified()
            && $this->hasGoodCreditHistory($loan->getUser());
    }

    /**
     * Vérifie l'historique de crédit de l'utilisateur
     */
    private function hasGoodCreditHistory(User $user): bool
    {
        // Logique de vérification de l'historique de crédit
        // Pour l'instant, retourne true
        return true;
    }
}
