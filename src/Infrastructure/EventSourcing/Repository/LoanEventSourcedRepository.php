<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSourcing\Repository;

use App\Domain\Entity\LoanAggregate;
use App\Infrastructure\EventSourcing\EventSourcedRepository;
use App\Infrastructure\EventSourcing\EventStore;
use Ramsey\Uuid\UuidInterface;

/**
 * Repository pour l'agrégat Loan avec Event Sourcing
 */
class LoanEventSourcedRepository extends EventSourcedRepository
{
    public function __construct(EventStore $eventStore)
    {
        parent::__construct($eventStore, LoanAggregate::class);
    }

    /**
     * Sauvegarde un agrégat Loan
     */
    public function saveLoan(LoanAggregate $loan): void
    {
        $this->save($loan);
    }

    /**
     * Charge un agrégat Loan par son ID
     */
    public function loadLoan(UuidInterface $loanId): ?LoanAggregate
    {
        return $this->load($loanId);
    }

    /**
     * Vérifie si un prêt existe
     */
    public function loanExists(UuidInterface $loanId): bool
    {
        return $this->exists($loanId);
    }

    /**
     * Récupère tous les prêts d'un client
     */
    public function findLoansByCustomer(UuidInterface $customerId): array
    {
        // Cette méthode nécessite une projection ou un index
        // Pour l'instant, on peut utiliser une requête sur l'event store
        // En production, on utiliserait une vue matérialisée
        
        $events = $this->eventStore->getEventsByType('App\\Domain\\Event\\Loan\\LoanApplicationCreated');
        $loanIds = [];
        
        foreach ($events as $event) {
            $payload = $event->getPayload();
            if ($payload['customerId'] === $customerId->toString()) {
                $loanIds[] = $event->getAggregateId();
            }
        }
        
        $loans = [];
        foreach ($loanIds as $loanId) {
            $loan = $this->loadLoan(\Ramsey\Uuid\Uuid::fromString($loanId));
            if ($loan) {
                $loans[] = $loan;
            }
        }
        
        return $loans;
    }

    /**
     * Récupère les statistiques des prêts
     */
    public function getLoanStatistics(): array
    {
        $creationEvents = $this->eventStore->getEventsByType('App\\Domain\\Event\\Loan\\LoanApplicationCreated');
        $statusEvents = $this->eventStore->getEventsByType('App\\Domain\\Event\\Loan\\LoanStatusChanged');
        
        $stats = [
            'totalApplications' => count($creationEvents),
            'statusDistribution' => [],
            'averageAmount' => 0,
            'totalFunded' => 0
        ];
        
        // Calcul de la moyenne des montants demandés
        $totalAmount = 0;
        foreach ($creationEvents as $event) {
            $payload = $event->getPayload();
            $totalAmount += $payload['requestedAmount'];
        }
        
        if (!empty($creationEvents)) {
            $stats['averageAmount'] = $totalAmount / count($creationEvents);
        }
        
        // Distribution des statuts (approximative)
        foreach ($statusEvents as $event) {
            $payload = $event->getPayload();
            $status = $payload['newStatus'];
            $stats['statusDistribution'][$status] = ($stats['statusDistribution'][$status] ?? 0) + 1;
        }
        
        return $stats;
    }
}
