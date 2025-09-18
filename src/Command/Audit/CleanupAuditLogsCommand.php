<?php

namespace App\Command\Audit;

use App\Service\Audit\AuditLoggerService;
use App\Service\GDPR\GDPRService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:cleanup',
    description: 'Nettoie les anciens logs d\'audit et données RGPD selon les politiques de rétention'
)]
class CleanupAuditLogsCommand extends Command
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private GDPRService $gdprService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('audit-retention-days', null, InputOption::VALUE_OPTIONAL, 'Nombre de jours de rétention pour les logs d\'audit', 365)
            ->addOption('consent-retention-days', null, InputOption::VALUE_OPTIONAL, 'Nombre de jours de rétention pour les consentements', 2555)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule le nettoyage sans supprimer les données')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force le nettoyage sans confirmation')
            ->setHelp('Cette commande nettoie les anciens logs d\'audit et données RGPD selon les politiques de rétention configurées.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $auditRetentionDays = (int) $input->getOption('audit-retention-days');
        $consentRetentionDays = (int) $input->getOption('consent-retention-days');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Nettoyage des logs d\'audit et données RGPD');

        // Validation des paramètres
        if ($auditRetentionDays <= 0 || $consentRetentionDays <= 0) {
            $io->error('Les jours de rétention doivent être supérieurs à 0');
            return Command::FAILURE;
        }

        // Affichage des paramètres
        $io->section('Configuration du nettoyage');
        $io->definitionList(
            ['Rétention logs d\'audit' => "{$auditRetentionDays} jours"],
            ['Rétention consentements' => "{$consentRetentionDays} jours"],
            ['Mode simulation' => $dryRun ? 'OUI' : 'NON'],
            ['Date de coupure audit' => (new \DateTime("-{$auditRetentionDays} days"))->format('Y-m-d H:i:s')],
            ['Date de coupure consentements' => (new \DateTime("-{$consentRetentionDays} days"))->format('Y-m-d H:i:s')]
        );

        // Confirmation
        if (!$force && !$dryRun) {
            if (!$io->confirm('Êtes-vous sûr de vouloir procéder au nettoyage ? Cette action est irréversible.', false)) {
                $io->info('Nettoyage annulé.');
                return Command::SUCCESS;
            }
        }

        $io->section('Traitement en cours...');

        try {
            // Nettoyage des logs d'audit
            $io->text('Nettoyage des logs d\'audit...');
            
            if (!$dryRun) {
                $deletedAuditLogs = $this->auditLogger->cleanup($auditRetentionDays);
                $io->success("✓ {$deletedAuditLogs} logs d'audit supprimés");
            } else {
                // En mode simulation, on compte seulement
                $io->info('Mode simulation - aucune suppression effectuée');
            }

            // Nettoyage RGPD
            $io->text('Nettoyage des données RGPD...');
            
            if (!$dryRun) {
                $gdprCleanupResult = $this->gdprService->cleanup();
                $io->success("✓ Nettoyage RGPD terminé");
                
                if (isset($gdprCleanupResult['deletedConsents'])) {
                    $io->text("  - {$gdprCleanupResult['deletedConsents']} anciens consentements supprimés");
                }
                
                if (isset($gdprCleanupResult['expiredConsents'])) {
                    $io->text("  - {$gdprCleanupResult['expiredConsents']} consentements expirés traités");
                }
            } else {
                $io->info('Mode simulation - aucune suppression effectuée');
            }

            // Statistiques finales
            $io->section('Résumé du nettoyage');
            
            if (!$dryRun) {
                $io->success('Nettoyage terminé avec succès !');
                
                // Log de l'opération de nettoyage
                $this->auditLogger->log(
                    'cleanup_executed',
                    'System',
                    null,
                    null,
                    [
                        'auditRetentionDays' => $auditRetentionDays,
                        'consentRetentionDays' => $consentRetentionDays,
                        'deletedAuditLogs' => $deletedAuditLogs ?? 0,
                        'executedBy' => 'console_command'
                    ],
                    'Nettoyage automatique des données d\'audit et RGPD exécuté',
                    AuditLoggerService::SEVERITY_HIGH
                );
            } else {
                $io->info('Simulation terminée. Utilisez --force pour exécuter réellement le nettoyage.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage : ' . $e->getMessage());
            
            // Log de l'erreur
            $this->auditLogger->log(
                'cleanup_failed',
                'System',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'auditRetentionDays' => $auditRetentionDays,
                    'consentRetentionDays' => $consentRetentionDays
                ],
                'Échec du nettoyage automatique : ' . $e->getMessage(),
                AuditLoggerService::SEVERITY_CRITICAL
            );

            return Command::FAILURE;
        }
    }
}
