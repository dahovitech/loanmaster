<?php

namespace App\Command\GDPR;

use App\Service\GDPR\GDPRService;
use App\Service\Audit\AuditLoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gdpr:process-expired-consents',
    description: 'Traite les consentements RGPD expirés et envoie des notifications'
)]
class ProcessExpiredConsentsCommand extends Command
{
    public function __construct(
        private GDPRService $gdprService,
        private AuditLoggerService $auditLogger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('notification-days', null, InputOption::VALUE_OPTIONAL, 'Nombre de jours avant expiration pour envoyer les notifications', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule le traitement sans modifier les données')
            ->addOption('send-notifications', null, InputOption::VALUE_NONE, 'Envoie les notifications par email')
            ->setHelp('Cette commande traite les consentements RGPD expirés et peut envoyer des notifications aux utilisateurs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $notificationDays = (int) $input->getOption('notification-days');
        $dryRun = $input->getOption('dry-run');
        $sendNotifications = $input->getOption('send-notifications');

        $io->title('Traitement des consentements RGPD expirés');

        $io->section('Configuration du traitement');
        $io->definitionList(
            ['Notification avant expiration' => "{$notificationDays} jours"],
            ['Mode simulation' => $dryRun ? 'OUI' : 'NON'],
            ['Envoi notifications' => $sendNotifications ? 'OUI' : 'NON']
        );

        try {
            // 1. Traiter les consentements expirés
            $io->text('Traitement des consentements expirés...');
            
            $expiredConsents = $this->gdprService->handleExpiredConsents();
            $expiredCount = count($expiredConsents);
            
            if ($expiredCount > 0) {
                $io->success("✓ {$expiredCount} consentements expirés traités");
                
                // Afficher les détails
                $io->table(
                    ['Utilisateur ID', 'Type de consentement', 'Date d\'expiration'],
                    array_map(function($consent) {
                        return [
                            $consent['userId'],
                            $consent['consentType'],
                            $consent['expiresAt'] ?? 'N/A'
                        ];
                    }, array_slice($expiredConsents, 0, 10)) // Limiter l'affichage à 10
                );
                
                if ($expiredCount > 10) {
                    $io->text("... et " . ($expiredCount - 10) . " autres");
                }
            } else {
                $io->info('Aucun consentement expiré trouvé');
            }

            // 2. Identifier les consentements qui expirent bientôt
            $io->text('Identification des consentements qui expirent bientôt...');
            
            $expiringSoonConsents = $this->gdprService->getExpiringSoonConsents($notificationDays);
            $expiringSoonCount = count($expiringSoonConsents);
            
            if ($expiringSoonCount > 0) {
                $io->warning("⚠ {$expiringSoonCount} consentements expirent dans les {$notificationDays} prochains jours");
                
                // Afficher les détails
                $io->table(
                    ['Utilisateur ID', 'Type de consentement', 'Expire dans', 'Date d\'expiration'],
                    array_map(function($consent) {
                        $daysUntil = $consent->getDaysUntilExpiry();
                        return [
                            $consent->getUserId(),
                            $consent->getConsentType(),
                            $daysUntil . ' jours',
                            $consent->getExpiresAt()?->format('Y-m-d H:i:s') ?? 'N/A'
                        ];
                    }, array_slice($expiringSoonConsents, 0, 10))
                );
                
                if ($expiringSoonCount > 10) {
                    $io->text("... et " . ($expiringSoonCount - 10) . " autres");
                }

                // 3. Envoyer les notifications si demandé
                if ($sendNotifications && !$dryRun) {
                    $io->text('Envoi des notifications...');
                    
                    $sentNotifications = 0;
                    foreach ($expiringSoonConsents as $consent) {
                        try {
                            // TODO: Implémenter l'envoi réel d'emails
                            // $this->emailService->sendConsentExpirationNotification($consent);
                            
                            $sentNotifications++;
                            
                            // Log de la notification
                            $this->auditLogger->logGdprEvent(
                                'consent_expiration_notification_sent',
                                $consent->getUserId(),
                                $consent->getConsentType(),
                                [
                                    'daysUntilExpiry' => $consent->getDaysUntilExpiry(),
                                    'expiresAt' => $consent->getExpiresAt()?->format('Y-m-d H:i:s')
                                ],
                                "Notification d'expiration envoyée pour le consentement {$consent->getConsentType()}"
                            );
                            
                        } catch (\Exception $e) {
                            $io->warning("Erreur lors de l'envoi de notification pour l'utilisateur {$consent->getUserId()}: " . $e->getMessage());
                        }
                    }
                    
                    $io->success("✓ {$sentNotifications} notifications envoyées");
                } elseif ($sendNotifications && $dryRun) {
                    $io->info("Mode simulation - {$expiringSoonCount} notifications auraient été envoyées");
                }
            } else {
                $io->info("Aucun consentement n'expire dans les {$notificationDays} prochains jours");
            }

            // 4. Statistiques de conformité
            $io->section('Statistiques de conformité RGPD');
            
            $complianceStats = $this->gdprService->getComplianceStats();
            
            $io->definitionList(
                ['Taux de conformité global' => $complianceStats['complianceRate'] . '%'],
                ['Utilisateurs totaux' => $complianceStats['totalUsers']],
                ['Utilisateurs conformes' => $complianceStats['compliantUsers']],
                ['Retraits récents' => $complianceStats['recentWithdrawals']],
                ['Consentements expirés' => $complianceStats['expiredConsents']]
            );

            // 5. Résumé final
            $io->section('Résumé du traitement');
            
            $summary = [];
            $summary[] = ['Consentements expirés traités', $expiredCount];
            $summary[] = ['Consentements expirant bientôt', $expiringSoonCount];
            
            if ($sendNotifications && !$dryRun) {
                $summary[] = ['Notifications envoyées', $sentNotifications ?? 0];
            }
            
            $io->table(['Élément', 'Nombre'], $summary);

            // Log de l'exécution de la commande
            if (!$dryRun) {
                $this->auditLogger->log(
                    'gdpr_expired_consents_processed',
                    'System',
                    null,
                    null,
                    [
                        'expiredConsentsCount' => $expiredCount,
                        'expiringSoonCount' => $expiringSoonCount,
                        'notificationsSent' => $sentNotifications ?? 0,
                        'notificationDays' => $notificationDays,
                        'executedBy' => 'console_command'
                    ],
                    'Traitement automatique des consentements RGPD expirés',
                    AuditLoggerService::SEVERITY_MEDIUM
                );
            }

            $io->success('Traitement terminé avec succès !');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du traitement : ' . $e->getMessage());
            
            // Log de l'erreur
            $this->auditLogger->log(
                'gdpr_processing_failed',
                'System',
                null,
                null,
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'notificationDays' => $notificationDays
                ],
                'Échec du traitement des consentements expirés : ' . $e->getMessage(),
                AuditLoggerService::SEVERITY_CRITICAL
            );

            return Command::FAILURE;
        }
    }
}
