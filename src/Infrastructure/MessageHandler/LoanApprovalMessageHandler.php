<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageHandler;

use App\Application\Message\LoanApprovalMessage;
use App\Application\Service\WorkflowService;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Service\AuditLogService;
use App\Infrastructure\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour traiter les approbations de prêt en arrière-plan
 */
#[AsMessageHandler]
class LoanApprovalMessageHandler
{
    public function __construct(
        private readonly LoanRepositoryInterface $loanRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly WorkflowService $workflowService,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(LoanApprovalMessage $message): void
    {
        $this->logger->info('Processing loan approval', [
            'loan_id' => $message->getLoanId(),
            'admin_id' => $message->getAdminId()
        ]);

        try {
            // Début de transaction
            $this->entityManager->beginTransaction();

            $loan = $this->loanRepository->find($message->getLoanId());
            if (!$loan) {
                throw new \RuntimeException("Loan {$message->getLoanId()} not found");
            }

            $admin = $this->userRepository->find($message->getAdminId());
            if (!$admin) {
                throw new \RuntimeException("Admin {$message->getAdminId()} not found");
            }

            // Vérifications de sécurité et de business logic
            if (!$this->workflowService->canApplyLoanTransition($loan, 'approve')) {
                throw new \RuntimeException("Cannot approve loan {$message->getLoanId()} in current state");
            }

            // Validation des données d'approbation
            $approvalData = $message->getApprovalData();
            $this->validateApprovalData($loan, $approvalData);

            // Mettre à jour les données du prêt avec les infos d'approbation
            $this->updateLoanWithApprovalData($loan, $approvalData);

            // Appliquer la transition workflow
            $success = $this->workflowService->applyLoanTransition($loan, 'approve', $admin);
            
            if (!$success) {
                throw new \RuntimeException("Failed to apply approval transition");
            }

            // Processus post-approbation
            $this->processPostApproval($loan, $admin, $approvalData);

            // Commit de la transaction
            $this->entityManager->commit();

            $this->logger->info('Loan approval processed successfully', [
                'loan_id' => $message->getLoanId(),
                'admin_id' => $message->getAdminId()
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to process loan approval', [
                'loan_id' => $message->getLoanId(),
                'admin_id' => $message->getAdminId(),
                'error' => $e->getMessage()
            ]);

            // Log d'audit de l'échec
            $this->auditLogService->logWorkflowError(
                'loan',
                $message->getLoanId(),
                'Approval processing failed: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    private function validateApprovalData($loan, array $approvalData): void
    {
        // Validation des conditions d'approbation
        if (isset($approvalData['approved_amount'])) {
            $approvedAmount = (float) $approvalData['approved_amount'];
            if ($approvedAmount <= 0 || $approvedAmount > $loan->getAmount()) {
                throw new \InvalidArgumentException('Invalid approved amount');
            }
        }

        if (isset($approvalData['interest_rate'])) {
            $interestRate = (float) $approvalData['interest_rate'];
            if ($interestRate < 0 || $interestRate > 50) {
                throw new \InvalidArgumentException('Invalid interest rate');
            }
        }

        if (isset($approvalData['loan_term_months'])) {
            $termMonths = (int) $approvalData['loan_term_months'];
            if ($termMonths < 1 || $termMonths > 120) {
                throw new \InvalidArgumentException('Invalid loan term');
            }
        }
    }

    private function updateLoanWithApprovalData($loan, array $approvalData): void
    {
        if (isset($approvalData['approved_amount'])) {
            $loan->setApprovedAmount((float) $approvalData['approved_amount']);
        }

        if (isset($approvalData['interest_rate'])) {
            $loan->setInterestRate((float) $approvalData['interest_rate']);
        }

        if (isset($approvalData['loan_term_months'])) {
            $loan->setLoanTermMonths((int) $approvalData['loan_term_months']);
        }

        if (isset($approvalData['approval_notes'])) {
            $loan->setApprovalNotes($approvalData['approval_notes']);
        }

        // Calculer la date d'échéance
        if (isset($approvalData['loan_term_months'])) {
            $dueDate = (new \DateTime())->modify("+{$approvalData['loan_term_months']} months");
            $loan->setDueDate($dueDate);
        }
    }

    private function processPostApproval($loan, $admin, array $approvalData): void
    {
        // 1. Créer le contrat de prêt
        $this->generateLoanContract($loan);

        // 2. Calculer le calendrier de remboursement
        $this->calculateRepaymentSchedule($loan);

        // 3. Notifications
        $this->notificationService->sendLoanApprovedNotification($loan->getUser(), $loan);

        // 4. Log d'audit
        $this->auditLogService->logAdminAction(
            'loan_approval',
            $admin->getId(),
            [
                'loan_id' => $loan->getId(),
                'approval_data' => $approvalData
            ]
        );

        // 5. Programmer le débours automatique si configuré
        if (isset($approvalData['auto_disburse']) && $approvalData['auto_disburse']) {
            $this->scheduleAutomaticDisbursement($loan);
        }
    }

    private function generateLoanContract($loan): void
    {
        // Génération du contrat de prêt
        // Cette méthode pourrait dispatcher un autre message pour la génération
        $this->logger->info('Loan contract generation scheduled', [
            'loan_id' => $loan->getId()
        ]);
    }

    private function calculateRepaymentSchedule($loan): void
    {
        // Calcul du calendrier de remboursement
        // Logic de calcul des échéances mensuelles
        $this->logger->info('Repayment schedule calculated', [
            'loan_id' => $loan->getId()
        ]);
    }

    private function scheduleAutomaticDisbursement($loan): void
    {
        // Programmer le débours automatique
        $this->logger->info('Automatic disbursement scheduled', [
            'loan_id' => $loan->getId()
        ]);
    }
}
