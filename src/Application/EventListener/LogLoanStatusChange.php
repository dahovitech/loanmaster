<?php

declare(strict_types=1);

namespace App\Application\EventListener;

use App\Domain\Event\LoanStatusChanged;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class LogLoanStatusChange
{
    public function __construct(
        private LoggerInterface $logger
    ) {}
    
    public function __invoke(LoanStatusChanged $event): void
    {
        $this->logger->info('Loan status changed', [
            'loanId' => $event->getLoanId()->toString(),
            'previousStatus' => $event->getPreviousStatus()->value,
            'newStatus' => $event->getNewStatus()->value,
            'timestamp' => $event->getOccurredOn()->format('c'),
        ]);
        
        // TODO: Mettre à jour le cache
        // TODO: Envoyer notification temps réel via Mercure
        // TODO: Déclencher actions automatisées selon le statut
    }
}
