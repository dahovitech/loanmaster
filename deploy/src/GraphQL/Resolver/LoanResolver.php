<?php

namespace App\GraphQL\Resolver;

use App\Infrastructure\EventSourcing\Repository\LoanEventSourcedRepository;
use App\Infrastructure\EventSourcing\EventHandler\LoanProjectionHandler;
use App\Application\Query\Loan\GetLoanStatisticsQuery;
use App\Infrastructure\EventSourcing\QueryBus;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

/**
 * Résolveur GraphQL pour les requêtes sur les prêts
 * Intégration complète avec Event Sourcing
 */
class LoanResolver
{
    private LoanEventSourcedRepository $loanRepository;
    private LoanProjectionHandler $projectionHandler;
    private QueryBus $queryBus;

    public function __construct(
        LoanEventSourcedRepository $loanRepository,
        LoanProjectionHandler $projectionHandler,
        QueryBus $queryBus
    ) {
        $this->loanRepository = $loanRepository;
        $this->projectionHandler = $projectionHandler;
        $this->queryBus = $queryBus;
    }

    /**
     * Récupère une liste de prêts avec filtres et pagination
     */
    public function loans($root, array $args, $context, $info): array
    {
        $filters = $args['filters'] ?? [];
        $pagination = $args['pagination'] ?? [];
        $sorting = $args['sorting'] ?? ['field' => 'CREATED_AT', 'direction' => 'DESC'];
        
        // Construction de la requête SQL dynamique
        $sql = "SELECT * FROM loan_projections WHERE 1=1";
        $params = [];
        
        // Application des filtres
        if (!empty($filters['status'])) {
            $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
            $sql .= " AND status IN ($placeholders)";
            $params = array_merge($params, $filters['status']);
        }
        
        if (!empty($filters['riskLevel'])) {
            $placeholders = implode(',', array_fill(0, count($filters['riskLevel']), '?'));
            $sql .= " AND risk_level IN ($placeholders)";
            $params = array_merge($params, $filters['riskLevel']);
        }
        
        if (!empty($filters['amountRange'])) {
            if (isset($filters['amountRange']['min'])) {
                $sql .= " AND requested_amount >= ?";
                $params[] = $filters['amountRange']['min'];
            }
            if (isset($filters['amountRange']['max'])) {
                $sql .= " AND requested_amount <= ?";
                $params[] = $filters['amountRange']['max'];
            }
        }
        
        if (!empty($filters['dateRange'])) {
            if (isset($filters['dateRange']['start'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['dateRange']['start'];
            }
            if (isset($filters['dateRange']['end'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['dateRange']['end'];
            }
        }
        
        if (!empty($filters['customerId'])) {
            $sql .= " AND customer_id = ?";
            $params[] = $filters['customerId'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (loan_id LIKE ? OR customer_id LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Tri
        $sortField = $this->mapSortField($sorting['field']);
        $sortDirection = strtoupper($sorting['direction']);
        $sql .= " ORDER BY $sortField $sortDirection";
        
        // Pagination
        $limit = $pagination['first'] ?? 20;
        $offset = 0;
        
        if (!empty($pagination['after'])) {
            // Implémentation cursor-based pagination
            $cursor = base64_decode($pagination['after']);
            $sql .= " AND created_at < ?";
            $params[] = $cursor;
        }
        
        $sql .= " LIMIT ?";
        $params[] = $limit;
        
        // Exécution de la requête
        $loans = []; // TODO: Exécuter la requête via Doctrine DBAL
        
        // Construction de la connexion GraphQL
        return [
            'edges' => array_map(function($loan) {
                return [
                    'node' => $this->transformLoanProjection($loan),
                    'cursor' => base64_encode($loan['created_at'])
                ];
            }, $loans),
            'pageInfo' => [
                'hasNextPage' => count($loans) === $limit,
                'hasPreviousPage' => !empty($pagination['after']),
                'startCursor' => !empty($loans) ? base64_encode($loans[0]['created_at']) : null,
                'endCursor' => !empty($loans) ? base64_encode(end($loans)['created_at']) : null
            ],
            'totalCount' => 0 // TODO: Compter le total
        ];
    }

    /**
     * Récupère un prêt spécifique par ID
     */
    public function loan($root, array $args, $context, $info): ?array
    {
        $loanId = Uuid::fromString($args['id']);
        $loan = $this->loanRepository->loadLoan($loanId);
        
        if (!$loan) {
            return null;
        }
        
        return $this->transformLoanAggregate($loan);
    }

    /**
     * Récupère les statistiques des prêts
     */
    public function statistics($root, array $args, $context, $info): array
    {
        $filters = $args['filters'] ?? [];
        
        $since = null;
        $until = null;
        
        if (!empty($filters['dateRange'])) {
            $since = isset($filters['dateRange']['start']) ? 
                new DateTimeImmutable($filters['dateRange']['start']) : null;
            $until = isset($filters['dateRange']['end']) ? 
                new DateTimeImmutable($filters['dateRange']['end']) : null;
        }
        
        $query = new GetLoanStatisticsQuery(
            $filters['status'][0] ?? null,
            $filters['riskLevel'][0] ?? null,
            $since,
            $until
        );
        
        $stats = $this->queryBus->execute($query);
        
        return [
            'totalLoans' => (int) ($stats['total_loans'] ?? 0),
            'pendingLoans' => (int) ($stats['pending_loans'] ?? 0),
            'approvedLoans' => (int) ($stats['approved_loans'] ?? 0),
            'activeLoans' => (int) ($stats['active_loans'] ?? 0),
            'completedLoans' => (int) ($stats['completed_loans'] ?? 0),
            'rejectedLoans' => (int) ($stats['rejected_loans'] ?? 0),
            'defaultedLoans' => (int) ($stats['defaulted_loans'] ?? 0),
            'totalRequested' => (float) ($stats['total_requested_amount'] ?? 0),
            'totalApproved' => (float) ($stats['total_approved_amount'] ?? 0),
            'totalOutstanding' => (float) ($stats['total_outstanding_balance'] ?? 0),
            'averageAmount' => (float) ($stats['avg_requested_amount'] ?? 0),
            'approvalRate' => (float) ($stats['approval_rate'] ?? 0),
            'completionRate' => (float) ($stats['completion_rate'] ?? 0),
            'defaultRate' => (float) ($stats['default_rate'] ?? 0),
            'averageRiskScore' => (float) ($stats['avg_risk_score'] ?? 0),
            'riskDistribution' => $this->calculateRiskDistribution($stats),
            'periodStart' => $since,
            'periodEnd' => $until
        ];
    }

    /**
     * Transforme un agrégat Loan en format GraphQL
     */
    private function transformLoanAggregate($loan): array
    {
        return [
            'id' => $loan->getId()->toString(),
            'customerId' => $loan->getCustomerId()->toString(),
            'requestedAmount' => $loan->getRequestedAmount(),
            'approvedAmount' => $loan->getApprovedAmount(),
            'currentBalance' => $loan->getCurrentBalance(),
            'status' => strtoupper($loan->getStatus()),
            'interestRate' => $loan->getInterestRate(),
            'riskScore' => $loan->getRiskScore(),
            'version' => $loan->getVersion(),
            'events' => $this->transformEvents($loan->getUncommittedEvents()),
            // Autres champs...
        ];
    }

    /**
     * Transforme une projection de prêt en format GraphQL
     */
    private function transformLoanProjection(array $projection): array
    {
        return [
            'id' => $projection['loan_id'],
            'customerId' => $projection['customer_id'],
            'requestedAmount' => (float) $projection['requested_amount'],
            'approvedAmount' => (float) $projection['approved_amount'],
            'currentBalance' => (float) $projection['current_balance'],
            'status' => strtoupper($projection['status']),
            'interestRate' => (float) $projection['interest_rate'],
            'riskScore' => (int) $projection['risk_score'],
            'riskLevel' => strtoupper($projection['risk_level'] ?? ''),
            'createdAt' => $projection['created_at'],
            'updatedAt' => $projection['updated_at'],
            'fundedAt' => $projection['funded_at'],
            'completedAt' => $projection['completed_at']
        ];
    }

    /**
     * Transforme les événements en format GraphQL
     */
    private function transformEvents(array $events): array
    {
        return array_map(function($event) {
            return [
                'id' => uniqid(),
                'aggregateId' => $event->getAggregateId(),
                'eventType' => $event->getEventName(),
                'version' => $event->getVersion(),
                'payload' => $event->getPayload(),
                'occurredAt' => $event->getOccurredOn(),
                'metadata' => $event instanceof \App\Infrastructure\EventSourcing\StoredDomainEvent ? 
                    $event->getStoredMetadata() : []
            ];
        }, $events);
    }

    /**
     * Mappe les champs de tri GraphQL vers les colonnes SQL
     */
    private function mapSortField(string $field): string
    {
        return match ($field) {
            'CREATED_AT' => 'created_at',
            'UPDATED_AT' => 'updated_at',
            'AMOUNT' => 'requested_amount',
            'STATUS' => 'status',
            'RISK_SCORE' => 'risk_score',
            'CUSTOMER_NAME' => 'customer_id', // TODO: Join avec table customer
            default => 'created_at'
        };
    }

    /**
     * Calcule la distribution des niveaux de risque
     */
    private function calculateRiskDistribution(array $stats): array
    {
        // TODO: Implémenter le calcul de distribution des risques
        return [
            ['level' => 'LOW', 'count' => 0, 'percentage' => 0],
            ['level' => 'MEDIUM', 'count' => 0, 'percentage' => 0],
            ['level' => 'HIGH', 'count' => 0, 'percentage' => 0],
            ['level' => 'CRITICAL', 'count' => 0, 'percentage' => 0]
        ];
    }
}
