<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Service\WorkflowService;
use App\Domain\Repository\LoanRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/workflow', name: 'api_workflow_')]
class WorkflowController extends AbstractController
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly LoanRepositoryInterface $loanRepository
    ) {}

    /**
     * Obtient le statut et les transitions disponibles pour un prêt
     */
    #[Route('/loan/{id}/status', name: 'loan_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getLoanStatus(int $id): JsonResponse
    {
        $loan = $this->loanRepository->find($id);
        
        if (!$loan) {
            return $this->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les droits d'accès
        if (!$this->isGranted('VIEW', $loan)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $summary = $this->workflowService->getLoanStatusSummary($loan);

        return $this->json([
            'loan_id' => $id,
            'current_status' => $summary['current_status'],
            'current_places' => $summary['current_places'],
            'available_transitions' => $summary['available_transitions'],
            'workflow' => $summary['workflow_name']
        ]);
    }

    /**
     * Applique une transition à un prêt
     */
    #[Route('/loan/{id}/transition', name: 'loan_transition', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function applyLoanTransition(int $id, Request $request): JsonResponse
    {
        $loan = $this->loanRepository->find($id);
        
        if (!$loan) {
            return $this->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les droits d'accès
        if (!$this->isGranted('EDIT', $loan)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $transition = $data['transition'] ?? null;

        if (!$transition) {
            return $this->json(['error' => 'Transition name is required'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si la transition est possible
        if (!$this->workflowService->canApplyLoanTransition($loan, $transition)) {
            return $this->json([
                'error' => 'Transition not available',
                'transition' => $transition,
                'current_status' => $loan->getStatus()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Valider les pré-conditions
        $errors = $this->workflowService->validateTransitionPreConditions($loan, $transition);
        if (!empty($errors)) {
            return $this->json([
                'error' => 'Pre-condition validation failed',
                'details' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        // Appliquer la transition
        $success = $this->workflowService->applyLoanTransition($loan, $transition, $this->getUser());

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => "Transition '{$transition}' applied successfully",
                'new_status' => $loan->getStatus(),
                'loan_id' => $id
            ]);
        } else {
            return $this->json([
                'error' => 'Failed to apply transition',
                'transition' => $transition
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtient l'historique des transitions d'un prêt
     */
    #[Route('/loan/{id}/history', name: 'loan_history', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getLoanHistory(int $id): JsonResponse
    {
        $loan = $this->loanRepository->find($id);
        
        if (!$loan) {
            return $this->json(['error' => 'Loan not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted('VIEW', $loan)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $history = $this->workflowService->getLoanWorkflowHistory($loan);

        return $this->json([
            'loan_id' => $id,
            'history' => $history
        ]);
    }

    /**
     * Obtient les transitions disponibles pour plusieurs prêts (batch)
     */
    #[Route('/loans/batch-status', name: 'loans_batch_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getBatchLoanStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $loanIds = $data['loan_ids'] ?? [];

        if (empty($loanIds) || !is_array($loanIds)) {
            return $this->json(['error' => 'loan_ids array is required'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        
        foreach ($loanIds as $loanId) {
            $loan = $this->loanRepository->find($loanId);
            
            if (!$loan) {
                $results[] = [
                    'loan_id' => $loanId,
                    'error' => 'Loan not found'
                ];
                continue;
            }

            $summary = $this->workflowService->getLoanStatusSummary($loan);
            $results[] = [
                'loan_id' => $loanId,
                'current_status' => $summary['current_status'],
                'available_transitions' => array_map(
                    fn($t) => $t['name'], 
                    $summary['available_transitions']
                )
            ];
        }

        return $this->json([
            'results' => $results,
            'total_processed' => count($results)
        ]);
    }

    /**
     * Applique une transition à plusieurs prêts (batch - admin uniquement)
     */
    #[Route('/loans/batch-transition', name: 'loans_batch_transition', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function applyBatchTransition(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $loanIds = $data['loan_ids'] ?? [];
        $transition = $data['transition'] ?? null;

        if (empty($loanIds) || !is_array($loanIds)) {
            return $this->json(['error' => 'loan_ids array is required'], Response::HTTP_BAD_REQUEST);
        }

        if (!$transition) {
            return $this->json(['error' => 'transition is required'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($loanIds as $loanId) {
            $loan = $this->loanRepository->find($loanId);
            
            if (!$loan) {
                $results[] = [
                    'loan_id' => $loanId,
                    'success' => false,
                    'error' => 'Loan not found'
                ];
                $errorCount++;
                continue;
            }

            if (!$this->workflowService->canApplyLoanTransition($loan, $transition)) {
                $results[] = [
                    'loan_id' => $loanId,
                    'success' => false,
                    'error' => 'Transition not available',
                    'current_status' => $loan->getStatus()
                ];
                $errorCount++;
                continue;
            }

            $success = $this->workflowService->applyLoanTransition($loan, $transition, $this->getUser());
            
            if ($success) {
                $results[] = [
                    'loan_id' => $loanId,
                    'success' => true,
                    'new_status' => $loan->getStatus()
                ];
                $successCount++;
            } else {
                $results[] = [
                    'loan_id' => $loanId,
                    'success' => false,
                    'error' => 'Failed to apply transition'
                ];
                $errorCount++;
            }
        }

        return $this->json([
            'results' => $results,
            'summary' => [
                'total_processed' => count($loanIds),
                'successful' => $successCount,
                'errors' => $errorCount,
                'transition' => $transition
            ]
        ]);
    }

    /**
     * Obtient les statistiques des workflows
     */
    #[Route('/stats', name: 'workflow_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getWorkflowStats(): JsonResponse
    {
        // Statistiques des statuts de prêts
        $loanStats = $this->loanRepository->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) as count')
            ->groupBy('l.status')
            ->getQuery()
            ->getResult();

        // Transformer en format plus lisible
        $statusDistribution = [];
        foreach ($loanStats as $stat) {
            $statusDistribution[$stat['status']] = (int) $stat['count'];
        }

        return $this->json([
            'loan_status_distribution' => $statusDistribution,
            'total_loans' => array_sum($statusDistribution),
            'generated_at' => (new \DateTime())->format('c')
        ]);
    }
}
