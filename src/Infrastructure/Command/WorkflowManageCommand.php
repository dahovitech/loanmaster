<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Service\WorkflowService;
use App\Domain\Repository\LoanRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:workflow:manage',
    description: 'Manage loan workflows and transitions',
)]
class WorkflowManageCommand extends Command
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly LoanRepositoryInterface $loanRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (transition, status, list)')
            ->addArgument('loan-id', InputArgument::OPTIONAL, 'Loan ID')
            ->addArgument('transition', InputArgument::OPTIONAL, 'Transition name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing')
            ->addOption('batch', null, InputOption::VALUE_NONE, 'Process multiple loans')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter loans by status')
            ->setHelp('
This command allows you to manage loan workflows:

<info>Show loan status:</info>
  <comment>php bin/console app:workflow:manage status 123</comment>

<info>Apply transition:</info>
  <comment>php bin/console app:workflow:manage transition 123 approve</comment>

<info>List available transitions:</info>
  <comment>php bin/console app:workflow:manage list 123</comment>

<info>Batch process loans:</info>
  <comment>php bin/console app:workflow:manage transition --batch --filter=submitted start_review</comment>
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $loanId = $input->getArgument('loan-id');
        $transition = $input->getArgument('transition');
        $dryRun = $input->getOption('dry-run');
        $batch = $input->getOption('batch');
        $filter = $input->getOption('filter');

        try {
            switch ($action) {
                case 'status':
                    return $this->showLoanStatus($io, $loanId);
                    
                case 'transition':
                    return $this->applyTransition($io, $loanId, $transition, $dryRun, $batch, $filter);
                    
                case 'list':
                    return $this->listTransitions($io, $loanId);
                    
                case 'auto-process':
                    return $this->autoProcessLoans($io, $dryRun);
                    
                default:
                    $io->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function showLoanStatus(SymfonyStyle $io, ?string $loanId): int
    {
        if (!$loanId) {
            $io->error('Loan ID is required for status action');
            return Command::FAILURE;
        }

        $loan = $this->loanRepository->find((int) $loanId);
        if (!$loan) {
            $io->error("Loan with ID {$loanId} not found");
            return Command::FAILURE;
        }

        $summary = $this->workflowService->getLoanStatusSummary($loan);
        
        $io->title("Loan #{$loanId} Status");
        $io->table(['Property', 'Value'], [
            ['Current Status', $summary['current_status']],
            ['Current Places', implode(', ', $summary['current_places'])],
            ['Workflow', $summary['workflow_name']],
            ['Amount', $loan->getAmount()],
            ['User', $loan->getUser()->getEmail()],
            ['Created At', $loan->getCreatedAt()->format('Y-m-d H:i:s')],
        ]);

        if (!empty($summary['available_transitions'])) {
            $io->section('Available Transitions');
            $transitions = [];
            foreach ($summary['available_transitions'] as $trans) {
                $transitions[] = [
                    $trans['name'],
                    $trans['metadata']['title'] ?? '',
                    $trans['metadata']['description'] ?? ''
                ];
            }
            $io->table(['Transition', 'Title', 'Description'], $transitions);
        } else {
            $io->note('No transitions available for this loan');
        }

        return Command::SUCCESS;
    }

    private function applyTransition(
        SymfonyStyle $io,
        ?string $loanId,
        ?string $transition,
        bool $dryRun,
        bool $batch,
        ?string $filter
    ): int {
        if ($batch) {
            return $this->batchApplyTransition($io, $transition, $filter, $dryRun);
        }

        if (!$loanId || !$transition) {
            $io->error('Loan ID and transition are required');
            return Command::FAILURE;
        }

        $loan = $this->loanRepository->find((int) $loanId);
        if (!$loan) {
            $io->error("Loan with ID {$loanId} not found");
            return Command::FAILURE;
        }

        // Vérifier si la transition est possible
        if (!$this->workflowService->canApplyLoanTransition($loan, $transition)) {
            $io->error("Transition '{$transition}' is not available for loan {$loanId}");
            return Command::FAILURE;
        }

        // Valider les pré-conditions
        $errors = $this->workflowService->validateTransitionPreConditions($loan, $transition);
        if (!empty($errors)) {
            $io->error('Pre-condition validation failed:');
            $io->listing($errors);
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note("DRY RUN: Would apply transition '{$transition}' to loan {$loanId}");
            return Command::SUCCESS;
        }

        $success = $this->workflowService->applyLoanTransition($loan, $transition);
        
        if ($success) {
            $io->success("Transition '{$transition}' applied successfully to loan {$loanId}");
            return Command::SUCCESS;
        } else {
            $io->error("Failed to apply transition '{$transition}' to loan {$loanId}");
            return Command::FAILURE;
        }
    }

    private function listTransitions(SymfonyStyle $io, ?string $loanId): int
    {
        if (!$loanId) {
            $io->error('Loan ID is required for list action');
            return Command::FAILURE;
        }

        $loan = $this->loanRepository->find((int) $loanId);
        if (!$loan) {
            $io->error("Loan with ID {$loanId} not found");
            return Command::FAILURE;
        }

        $transitions = $this->workflowService->getAvailableLoanTransitions($loan);
        
        if (empty($transitions)) {
            $io->note("No transitions available for loan {$loanId}");
            return Command::SUCCESS;
        }

        $io->title("Available Transitions for Loan #{$loanId}");
        $data = [];
        foreach ($transitions as $transition) {
            $metadata = $transition->getMetadata();
            $data[] = [
                $transition->getName(),
                $metadata['title'] ?? '',
                $metadata['description'] ?? '',
                implode(', ', $transition->getFroms()),
                implode(', ', $transition->getTos())
            ];
        }

        $io->table(['Name', 'Title', 'Description', 'From', 'To'], $data);
        return Command::SUCCESS;
    }

    private function batchApplyTransition(
        SymfonyStyle $io,
        ?string $transition,
        ?string $filter,
        bool $dryRun
    ): int {
        if (!$transition) {
            $io->error('Transition is required for batch processing');
            return Command::FAILURE;
        }

        // Récupérer les prêts selon le filtre
        $loans = $this->getLoansForBatch($filter);
        
        if (empty($loans)) {
            $io->note('No loans found matching the criteria');
            return Command::SUCCESS;
        }

        $io->title("Batch Processing: Apply '{$transition}' transition");
        $io->note("Found " . count($loans) . " loans to process");

        $processed = 0;
        $errors = 0;

        foreach ($loans as $loan) {
            if (!$this->workflowService->canApplyLoanTransition($loan, $transition)) {
                $io->writeln("<comment>Skipping loan {$loan->getId()}: transition not available</comment>");
                continue;
            }

            if ($dryRun) {
                $io->writeln("<info>DRY RUN: Would process loan {$loan->getId()}</info>");
                $processed++;
                continue;
            }

            $success = $this->workflowService->applyLoanTransition($loan, $transition);
            if ($success) {
                $io->writeln("<info>✓ Processed loan {$loan->getId()}</info>");
                $processed++;
            } else {
                $io->writeln("<error>✗ Failed to process loan {$loan->getId()}</error>");
                $errors++;
            }
        }

        $io->success("Batch processing completed: {$processed} processed, {$errors} errors");
        return Command::SUCCESS;
    }

    private function autoProcessLoans(SymfonyStyle $io, bool $dryRun): int
    {
        $io->title('Auto-processing loans');

        // Auto-submit ready loans
        $draftLoans = $this->loanRepository->findBy(['status' => 'draft']);
        $autoSubmitted = 0;

        foreach ($draftLoans as $loan) {
            if ($dryRun) {
                $io->writeln("<info>DRY RUN: Would auto-submit loan {$loan->getId()}</info>");
                $autoSubmitted++;
                continue;
            }

            if ($this->workflowService->autoSubmitLoanIfReady($loan)) {
                $io->writeln("<info>✓ Auto-submitted loan {$loan->getId()}</info>");
                $autoSubmitted++;
            }
        }

        // Auto-start review for eligible loans
        $submittedLoans = $this->loanRepository->findBy(['status' => 'submitted']);
        $autoReviewed = 0;

        foreach ($submittedLoans as $loan) {
            if ($dryRun) {
                $io->writeln("<info>DRY RUN: Would auto-start review for loan {$loan->getId()}</info>");
                $autoReviewed++;
                continue;
            }

            if ($this->workflowService->autoStartReviewIfEligible($loan)) {
                $io->writeln("<info>✓ Auto-started review for loan {$loan->getId()}</info>");
                $autoReviewed++;
            }
        }

        $io->success("Auto-processing completed: {$autoSubmitted} submitted, {$autoReviewed} reviews started");
        return Command::SUCCESS;
    }

    private function getLoansForBatch(?string $filter): array
    {
        if ($filter) {
            return $this->loanRepository->findBy(['status' => $filter]);
        }

        return $this->loanRepository->findAll();
    }
}
