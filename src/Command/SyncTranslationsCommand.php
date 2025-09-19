<?php

namespace App\Command;

use App\Service\TranslationManagerService;
use App\Repository\LanguageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Commande de synchronisation des traductions - Système Oragon
 * 
 * Fonctionnalités :
 * - Synchronisation automatique avec les langues actives
 * - Migration des traductions existantes
 * - Validation des fichiers de traduction
 * - Statistiques de progression
 * - Nettoyage des traductions obsolètes
 * 
 * @author Prudence ASSOGBA
 */
#[AsCommand(
    name: 'app:translation:sync',
    description: 'Synchronise les traductions avec les langues actives du système Oragon'
)]
class SyncTranslationsCommand extends Command
{
    public function __construct(
        private TranslationManagerService $translationManager,
        private LanguageRepository $languageRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::OPTIONAL, 'Domaine de traduction à synchroniser', 'messages')
            ->addOption('all-domains', 'a', InputOption::VALUE_NONE, 'Synchroniser tous les domaines')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Afficher les statistiques de traduction')
            ->addOption('validate', 'v', InputOption::VALUE_NONE, 'Valider les traductions existantes')
            ->addOption('clean', 'c', InputOption::VALUE_NONE, 'Nettoyer les traductions obsolètes')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la synchronisation même si les fichiers existent')
            ->setHelp('
Cette commande synchronise les traductions avec les langues actives configurées dans le système.

<info>Exemples d\'utilisation :</info>

  <comment># Synchroniser le domaine "messages"</comment>
  php bin/console app:translation:sync

  <comment># Synchroniser un domaine spécifique</comment>
  php bin/console app:translation:sync admin

  <comment># Synchroniser tous les domaines</comment>
  php bin/console app:translation:sync --all-domains

  <comment># Afficher les statistiques</comment>
  php bin/console app:translation:sync --stats

  <comment># Valider les traductions</comment>
  php bin/console app:translation:sync --validate

  <comment># Nettoyer les traductions obsolètes</comment>
  php bin/console app:translation:sync --clean
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🌍 Synchronisation des traductions - Système Oragon');
        
        try {
            // Vérification des langues actives
            $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
            
            if (empty($activeLanguages)) {
                $io->error('Aucune langue active trouvée. Veuillez activer au moins une langue.');
                return Command::FAILURE;
            }
            
            $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
            
            if (!$defaultLanguage) {
                $io->error('Aucune langue par défaut définie. Veuillez définir une langue par défaut.');
                return Command::FAILURE;
            }
            
            $io->section('Configuration détectée');
            $io->listing([
                "Langue par défaut : {$defaultLanguage->getName()} ({$defaultLanguage->getCode()})",
                "Langues actives : " . count($activeLanguages),
                "Codes langues : " . implode(', ', array_map(fn($l) => $l->getCode(), $activeLanguages))
            ]);
            
            // Traitement selon les options
            if ($input->getOption('stats')) {
                return $this->showStats($io, $input->getArgument('domain'));
            }
            
            if ($input->getOption('validate')) {
                return $this->validateTranslations($io, $input->getArgument('domain'));
            }
            
            if ($input->getOption('clean')) {
                return $this->cleanTranslations($io);
            }
            
            // Synchronisation principale
            if ($input->getOption('all-domains')) {
                return $this->syncAllDomains($io, $activeLanguages, $input->getOption('force'));
            } else {
                return $this->syncDomain($io, $input->getArgument('domain'), $activeLanguages, $input->getOption('force'));
            }
            
        } catch (\Exception $e) {
            $io->error('Erreur lors de la synchronisation : ' . $e->getMessage());
            
            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), 'TRACE', 'fg=yellow');
            }
            
            return Command::FAILURE;
        }
    }

    /**
     * Synchronise un domaine spécifique
     */
    private function syncDomain(SymfonyStyle $io, string $domain, array $activeLanguages, bool $force): int
    {
        $io->section("Synchronisation du domaine : {$domain}");
        
        $progressBar = new ProgressBar($io, count($activeLanguages));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Initialisation...');
        $progressBar->start();
        
        $results = [];
        
        try {
            $this->translationManager->synchronizeWithLanguages($domain);
            
            foreach ($activeLanguages as $language) {
                $progressBar->setMessage("Traitement : {$language->getName()} ({$language->getCode()})");
                
                $translations = $this->translationManager->getTranslations($domain, $language->getCode());
                $results[] = [
                    'language' => $language->getName(),
                    'code' => $language->getCode(),
                    'keys_count' => count($this->translationManager->flattenTranslations($translations)),
                    'status' => 'Synchronisé'
                ];
                
                $progressBar->advance();
                usleep(100000); // Pause de 100ms pour la visualisation
            }
            
        } catch (\Exception $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error("Erreur lors de la synchronisation : {$e->getMessage()}");
            return Command::FAILURE;
        }
        
        $progressBar->setMessage('Synchronisation terminée');
        $progressBar->finish();
        $io->newLine(2);
        
        // Affichage des résultats
        $io->success("✅ Synchronisation du domaine '{$domain}' terminée avec succès");
        
        $tableData = array_map(function($result) {
            return [
                $result['language'],
                $result['code'],
                $result['keys_count'],
                $result['status']
            ];
        }, $results);
        
        $io->table(['Langue', 'Code', 'Clés', 'Statut'], $tableData);
        
        // Afficher les statistiques
        $this->displayDomainStats($io, $domain);
        
        return Command::SUCCESS;
    }

    /**
     * Synchronise tous les domaines
     */
    private function syncAllDomains(SymfonyStyle $io, array $activeLanguages, bool $force): int
    {
        $domains = $this->translationManager->getAvailableDomains();
        
        $io->section("Synchronisation de tous les domaines");
        $io->text("Domaines détectés : " . implode(', ', $domains));
        
        $totalOperations = count($domains);
        $progressBar = new ProgressBar($io, $totalOperations);
        $progressBar->start();
        
        $globalResults = [];
        
        foreach ($domains as $domain) {
            $progressBar->setMessage("Synchronisation : {$domain}");
            
            try {
                $this->translationManager->synchronizeWithLanguages($domain);
                $globalResults[$domain] = '✅ Synchronisé';
            } catch (\Exception $e) {
                $globalResults[$domain] = '❌ Erreur: ' . substr($e->getMessage(), 0, 50);
                $io->warning("Erreur pour le domaine {$domain}: {$e->getMessage()}");
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $io->newLine(2);
        
        // Résumé global
        $io->success("✅ Synchronisation globale terminée");
        
        $tableData = [];
        foreach ($globalResults as $domain => $status) {
            $tableData[] = [$domain, $status];
        }
        
        $io->table(['Domaine', 'Statut'], $tableData);
        
        return Command::SUCCESS;
    }

    /**
     * Affiche les statistiques de traduction
     */
    private function showStats(SymfonyStyle $io, string $domain): int
    {
        $io->section("📊 Statistiques de traduction pour : {$domain}");
        
        $stats = $this->translationManager->getTranslationStats($domain);
        
        if (empty($stats)) {
            $io->warning("Aucune statistique disponible pour le domaine '{$domain}'");
            return Command::SUCCESS;
        }
        
        $tableData = [];
        $totalKeys = $stats[0]['total_keys'] ?? 0;
        $globalTranslated = 0;
        $globalMissing = 0;
        
        foreach ($stats as $stat) {
            $language = $stat['language'];
            
            $tableData[] = [
                $language['name'] . ($language['isDefault'] ? ' (défaut)' : ''),
                $language['code'],
                $stat['translated_keys'],
                $stat['missing_keys'],
                $stat['completion_percentage'] . '%',
                $this->getStatusEmoji($stat['status'])
            ];
            
            $globalTranslated += $stat['translated_keys'];
            $globalMissing += $stat['missing_keys'];
        }
        
        $io->table([
            'Langue', 'Code', 'Traduites', 'Manquantes', 'Progression', 'Statut'
        ], $tableData);
        
        // Statistiques globales
        $avgCompletion = $totalKeys > 0 ? round(($globalTranslated / ($totalKeys * count($stats))) * 100, 2) : 0;
        
        $io->definitionList(
            ['Total des clés' => $totalKeys],
            ['Langues actives' => count($stats)],
            ['Progression moyenne' => $avgCompletion . '%'],
            ['Total traductions manquantes' => $globalMissing]
        );
        
        return Command::SUCCESS;
    }

    /**
     * Valide les traductions existantes
     */
    private function validateTranslations(SymfonyStyle $io, string $domain): int
    {
        $io->section("🔍 Validation des traductions pour : {$domain}");
        
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        $hasErrors = false;
        
        foreach ($activeLanguages as $language) {
            $io->text("Validation : {$language->getName()} ({$language->getCode()})");
            
            $translations = $this->translationManager->getTranslations($domain, $language->getCode());
            $errors = $this->translationManager->validateTranslations($translations);
            
            if (!empty($errors)) {
                $hasErrors = true;
                $io->error("Erreurs pour {$language->getName()} :");
                $io->listing($errors);
            } else {
                $io->text("  ✅ Aucune erreur détectée");
            }
        }
        
        if (!$hasErrors) {
            $io->success('✅ Toutes les traductions sont valides');
        } else {
            $io->warning('⚠️ Des erreurs ont été détectées dans les traductions');
        }
        
        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Nettoie les traductions obsolètes
     */
    private function cleanTranslations(SymfonyStyle $io): int
    {
        $io->section("🧹 Nettoyage des traductions obsolètes");
        
        $io->warning('Cette fonctionnalité n\'est pas encore implémentée.');
        $io->text('À implémenter : suppression des fichiers pour les langues désactivées');
        
        return Command::SUCCESS;
    }

    /**
     * Affiche les statistiques détaillées d'un domaine
     */
    private function displayDomainStats(SymfonyStyle $io, string $domain): void
    {
        try {
            $stats = $this->translationManager->getTranslationStats($domain);
            
            if (!empty($stats)) {
                $io->section('📈 Statistiques détaillées');
                
                $totalKeys = $stats[0]['total_keys'] ?? 0;
                $avgCompletion = 0;
                
                if (!empty($stats)) {
                    $totalCompletion = array_sum(array_column($stats, 'completion_percentage'));
                    $avgCompletion = round($totalCompletion / count($stats), 2);
                }
                
                $io->definitionList(
                    ['Clés de traduction' => $totalKeys],
                    ['Progression moyenne' => $avgCompletion . '%'],
                    ['Langues configurées' => count($stats)]
                );
            }
        } catch (\Exception $e) {
            $io->text('Impossible d\'afficher les statistiques : ' . $e->getMessage());
        }
    }

    /**
     * Retourne l'emoji correspondant au statut
     */
    private function getStatusEmoji(string $status): string
    {
        return match ($status) {
            'complete' => '✅ Terminé',
            'good' => '🟢 Bon',
            'average' => '🟡 Moyen',
            'poor' => '🟠 Faible',
            'empty' => '🔴 Vide',
            default => '❓ Inconnu'
        };
    }
}
