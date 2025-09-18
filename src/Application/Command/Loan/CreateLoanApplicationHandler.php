<?php

namespace App\Application\Command\Loan;

use App\Domain\Entity\LoanAggregate;
use App\Infrastructure\EventSourcing\Repository\LoanEventSourcedRepository;
use App\Infrastructure\EventSourcing\SnapshotStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour la commande de création de demande de prêt
 */
class CreateLoanApplicationHandler
{
    private LoanEventSourcedRepository $repository;
    private SnapshotStore $snapshotStore;

    public function __construct(
        LoanEventSourcedRepository $repository,
        SnapshotStore $snapshotStore
    ) {
        $this->repository = $repository;
        $this->snapshotStore = $snapshotStore;
    }

    #[AsMessageHandler]
    public function __invoke(CreateLoanApplicationCommand $command): LoanAggregate
    {
        // Vérification que le prêt n'existe pas déjà
        if ($this->repository->loanExists($command->getLoanId())) {
            throw new \InvalidArgumentException(
                'Loan with ID ' . $command->getLoanId()->toString() . ' already exists'
            );
        }

        // Création de l'agrégat
        $loan = LoanAggregate::createApplication(
            $command->getLoanId(),
            $command->getCustomerId(),
            $command->getRequestedAmount(),
            $command->getDurationMonths(),
            $command->getPurpose(),
            $command->getCustomerData(),
            $command->getFinancialData(),
            $command->getIpAddress(),
            $command->getUserAgent()
        );

        // Sauvegarde de l'agrégat
        $this->repository->saveLoan($loan);

        // Création d'un snapshot si nécessaire
        if ($this->snapshotStore->shouldTakeSnapshot($loan)) {
            $this->snapshotStore->saveSnapshot($loan);
        }

        return $loan;
    }
}
