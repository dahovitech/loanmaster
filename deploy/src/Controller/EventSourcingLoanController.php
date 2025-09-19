<?php

namespace App\Controller;

use App\Application\Command\Loan\CreateLoanApplicationCommand;
use App\Application\Query\Loan\GetLoanStatisticsQuery;
use App\Infrastructure\EventSourcing\CommandBus;
use App\Infrastructure\EventSourcing\QueryBus;
use App\Infrastructure\EventSourcing\Repository\LoanEventSourcedRepository;
use App\Infrastructure\EventSourcing\AuditService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTimeImmutable;

/**
 * Contrôleur pour démontrer l'Event Sourcing des prêts
 * API complète avec audit et métriques
 */
#[Route('/api/event-sourcing/loans', name: 'api_event_sourcing_loans_')]
class EventSourcingLoanController extends AbstractController
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;
    private LoanEventSourcedRepository $loanRepository;
    private AuditService $auditService;

    public function __construct(
        CommandBus $commandBus,
        QueryBus $queryBus,
        LoanEventSourcedRepository $loanRepository,
        AuditService $auditService
    ) {
        $this->commandBus = $commandBus;
        $this->queryBus = $queryBus;
        $this->loanRepository = $loanRepository;
        $this->auditService = $auditService;
    }

    /**
     * Créer une nouvelle demande de prêt avec Event Sourcing
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function createLoanApplication(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation basique
            $this->validateLoanData($data);
            
            $loanId = Uuid::uuid4();
            $customerId = Uuid::fromString($data['customerId']);
            
            $command = new CreateLoanApplicationCommand(
                $loanId,
                $customerId,
                (float) $data['requestedAmount'],
                (int) $data['durationMonths'],
                $data['purpose'],
                $data['customerData'] ?? [],
                $data['financialData'] ?? [],
                $this->getUser()?->getId(),
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                $request->headers->get('X-Correlation-ID')
            );
            
            $loan = $this->commandBus->dispatch($command);
            
            return new JsonResponse([
                'success' => true,
                'loanId' => $loan->getId()->toString(),
                'status' => $loan->getStatus(),
                'message' => 'Loan application created successfully'
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Récupérer un prêt par son ID
     */
    #[Route('/{loanId}', name: 'get', methods: ['GET'])]
    public function getLoan(string $loanId): JsonResponse
    {
        try {
            $loan = $this->loanRepository->loadLoan(Uuid::fromString($loanId));
            
            if (!$loan) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Loan not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return new JsonResponse([
                'success' => true,
                'loan' => [
                    'id' => $loan->getId()->toString(),
                    'customerId' => $loan->getCustomerId()->toString(),
                    'requestedAmount' => $loan->getRequestedAmount(),
                    'approvedAmount' => $loan->getApprovedAmount(),
                    'currentBalance' => $loan->getCurrentBalance(),
                    'status' => $loan->getStatus(),
                    'riskScore' => $loan->getRiskScore(),
                    'interestRate' => $loan->getInterestRate(),
                    'version' => $loan->getVersion(),
                    'paymentHistory' => $loan->getPaymentHistory()
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Obtenir les statistiques des prêts
     */
    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function getLoanStatistics(Request $request): JsonResponse
    {
        try {
            $status = $request->query->get('status');
            $riskLevel = $request->query->get('riskLevel');
            
            $since = $request->query->get('since') ? 
                new DateTimeImmutable($request->query->get('since')) : null;
            $until = $request->query->get('until') ? 
                new DateTimeImmutable($request->query->get('until')) : null;
            
            $query = new GetLoanStatisticsQuery($status, $riskLevel, $since, $until);
            $statistics = $this->queryBus->execute($query);
            
            return new JsonResponse([
                'success' => true,
                'statistics' => $statistics
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Obtenir l'historique d'audit d'un prêt
     */
    #[Route('/{loanId}/audit', name: 'audit', methods: ['GET'])]
    public function getLoanAuditHistory(string $loanId, Request $request): JsonResponse
    {
        try {
            $since = $request->query->get('since') ? 
                new DateTimeImmutable($request->query->get('since')) : null;
            $until = $request->query->get('until') ? 
                new DateTimeImmutable($request->query->get('until')) : null;
            $limit = (int) ($request->query->get('limit') ?? 100);
            
            $auditHistory = $this->auditService->getAuditHistory(
                'loan',
                $loanId,
                $since,
                $until,
                $limit
            );
            
            return new JsonResponse([
                'success' => true,
                'auditHistory' => $auditHistory,
                'total' => count($auditHistory)
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reconstituer l'état d'un prêt à un moment donné
     */
    #[Route('/{loanId}/reconstruct', name: 'reconstruct', methods: ['GET'])]
    public function reconstructLoanState(string $loanId, Request $request): JsonResponse
    {
        try {
            $pointInTime = $request->query->get('pointInTime') ? 
                new DateTimeImmutable($request->query->get('pointInTime')) : 
                new DateTimeImmutable();
            
            $state = $this->auditService->reconstructEntityState(
                'loan',
                $loanId,
                $pointInTime
            );
            
            if (!$state) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Could not reconstruct loan state'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return new JsonResponse([
                'success' => true,
                'pointInTime' => $pointInTime->format(DATE_ATOM),
                'state' => $state
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Démonstration Event Sourcing : changer le statut d'un prêt
     */
    #[Route('/{loanId}/status', name: 'change_status', methods: ['PUT'])]
    public function changeLoanStatus(string $loanId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['status']) || !isset($data['reason'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Status and reason are required'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $loan = $this->loanRepository->loadLoan(Uuid::fromString($loanId));
            
            if (!$loan) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Loan not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $loan->changeStatus(
                $data['status'],
                $data['reason'],
                $this->getUser() ? Uuid::fromString($this->getUser()->getId()) : null,
                $data['comments'] ?? null
            );
            
            $this->loanRepository->saveLoan($loan);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Loan status changed successfully',
                'newStatus' => $loan->getStatus(),
                'version' => $loan->getVersion()
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Validation des données de prêt
     */
    private function validateLoanData(array $data): void
    {
        $required = ['customerId', 'requestedAmount', 'durationMonths', 'purpose'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
        
        if ($data['requestedAmount'] <= 0) {
            throw new \InvalidArgumentException('Requested amount must be positive');
        }
        
        if ($data['durationMonths'] <= 0 || $data['durationMonths'] > 360) {
            throw new \InvalidArgumentException('Duration must be between 1 and 360 months');
        }
    }
}
