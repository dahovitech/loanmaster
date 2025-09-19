<?php

declare(strict_types=1);

namespace App\Application\EventListener;

use App\Domain\Event\LoanApplicationCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class SendLoanApplicationNotification
{
    public function __construct(
        private LoggerInterface $logger
    ) {}
    
    public function __invoke(LoanApplicationCreated $event): void
    {
        // Log de l'événement
        $this->logger->info('New loan application created', [
            'loanId' => $event->getLoanId()->toString(),
            'userId' => $event->getUserId()->toString(),
            'loanNumber' => $event->getLoanNumber(),
        ]);
        
        // TODO: Envoyer notification à l'admin
        // TODO: Envoyer email de confirmation au client
        // TODO: Déclencher le processus d'évaluation automatique
    }
}
