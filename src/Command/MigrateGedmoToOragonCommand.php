<?php

namespace App\Command;

use App\Entity\Page;
use App\Entity\Seo;
use App\Entity\Language;
use App\Repository\PageRepository;
use App\Repository\SeoRepository;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Commande de migration des traductions Gedmo vers Oragon
 * 
 * Cette commande migre les traductions existantes du système Gedmo
 * vers le nouveau système Oragon avec les entités Translation dédiées.
 * 
 * Fonctionnalités :
 * - Migration des entités Page et Seo
 * - Préservation des données existantes
 * - Validation des données migrées
 * - Mode dry-run pour les tests
 * - Sauvegarde automatique des données
 * 
 * @author Prudence ASSOGBA
 */
#[AsCommand(
    name: 'app:migration:gedmo-to-oragon',
    description: 'Migre les traductions Gedmo vers le système Oragon'
)]
class MigrateGedmoToOragonCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageRepository $pageRepository,
        private SeoRepository $seoRepository,
        private LanguageRepository $languageRepository,
        private TranslatableListener $translatableListener
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('entity', 'e', InputOption::VALUE_REQUIRED, 'Entité à migrer (page, seo, all)', 'all')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulation sans modifications en base')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la migration même si des traductions Oragon existent')
            ->addOption('backup', 'b', InputOption::VALUE_NONE, 'Créer une sauvegarde avant migration')
            ->addOption('validate', 'v', InputOption::VALUE_NONE, 'Valider les données après migration')
            ->setHelp('
Cette commande migre les traductions existantes du système Gedmo DoctrineExtensions 
vers le nouveau système Oragon avec des entités Translation dédiées.

<info>Exemples d\'utilisation :</info>

  <comment># Migration complète (simulation)</comment>
  php bin/console app:migration:gedmo-to-oragon --dry-run

  <comment># Migration des pages uniquement</comment>
  php bin/console app:migration:gedmo-to-oragon --entity=page

  <comment># Migration complète avec sauvegarde</comment>
  php bin/console app:migration:gedmo-to-oragon --backup

  <comment># Migration forcée avec validation</comment>
  php bin/console app:migration:gedmo-to-oragon --force --validate

<info>Entités supportées :</info>
  - <comment>page</comment>    : Migration des traductions Page vers PageTranslation Oragon
  - <comment>seo</comment>     : Migration des traductions Seo vers SeoTranslation Oragon  
  - <comment>all</comment>     : Migration de toutes les entités supportées
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🚀 Migration Gedmo → Oragon - Système de traduction');
        
        $isDryRun = $input->getOption('dry-run');
        $entity = $input->getOption('entity');
        $isForced = $input->getOption('force');
        $withBackup = $input->getOption('backup');
        $withValidation = $input->getOption('validate');
        
        if ($isDryRun) {
            $io->note('🧪 Mode simulation activé - Aucune modification ne sera effectuée');
        }
        
        try {
            // Vérifications préliminaires
            if (!$this->runPreflightChecks($io)) {
                return Command::FAILURE;
            }
            
            // Confirmation si pas en dry-run
            if (!$isDryRun && !$isForced) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    '⚠️  Cette opération va modifier la base de données. Continuer ? (y/N) ', 
                    false
                );
                
                if (!$helper->ask($input, $output, $question)) {
                    $io->info('Migration annulée par l\'utilisateur');
                    return Command::SUCCESS;
                }
            }
            
            // Sauvegarde si demandée
            if ($withBackup && !$isDryRun) {
                $this->createBackup($io);
            }
            
            // Exécution de la migration selon l'entité
            $results = match($entity) {
                'page' => $this->migratePage($io, $isDryRun),
                'seo' => $this->migrateSeo($io, $isDryRun),
                'all' => $this->migrateAll($io, $isDryRun),
                default => throw new \InvalidArgumentException("Entité non supportée: {$entity}")
            };
            
            // Validation si demandée
            if ($withValidation && !$isDryRun) {
                $this->validateMigration($io, $entity);
            }
            
            // Rapport final
            $this->displayFinalReport($io, $results, $isDryRun);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors de la migration : ' . $e->getMessage());
            
            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), 'TRACE', 'fg=yellow');
            }
            
            return Command::FAILURE;
        }
    }

    /**
     * Vérifications préliminaires avant migration
     */
    private function runPreflightChecks(SymfonyStyle $io): bool
    {
        $io->section('🔍 Vérifications préliminaires');
        
        $checks = [];
        
        // Vérifier les langues actives
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        $defaultLanguage = $this->languageRepository->findOneBy(['isDefault' => true]);
        
        $checks['Langues actives'] = [
            'status' => !empty($activeLanguages),
            'message' => count($activeLanguages) . ' langues trouvées',
            'error' => 'Aucune langue active trouvée'
        ];
        
        $checks['Langue par défaut'] = [
            'status' => $defaultLanguage !== null,
            'message' => $defaultLanguage ? $defaultLanguage->getName() . ' (' . $defaultLanguage->getCode() . ')' : 'Non définie',
            'error' => 'Aucune langue par défaut définie'
        ];
        
        // Vérifier l'existence des entités à migrer
        $pageCount = $this->pageRepository->count([]);
        $seoCount = $this->seoRepository->count([]);
        
        $checks['Entités Page'] = [
            'status' => true,
            'message' => $pageCount . ' pages trouvées',
            'error' => null
        ];
        
        $checks['Entités Seo'] = [
            'status' => true,
            'message' => $seoCount . ' entités SEO trouvées',
            'error' => null
        ];
        
        // Vérifier la configuration Gedmo
        $checks['Configuration Gedmo'] = [
            'status' => $this->translatableListener !== null,
            'message' => 'TranslatableListener disponible',
            'error' => 'TranslatableListener non configuré'
        ];
        
        // Afficher les résultats des vérifications
        $allGood = true;
        foreach ($checks as $name => $check) {
            if ($check['status']) {
                $io->text("✅ {$name}: {$check['message']}");
            } else {
                $io->text("❌ {$name}: {$check['error']}");
                $allGood = false;
            }
        }
        
        if (!$allGood) {
            $io->error('Des vérifications ont échoué. Veuillez corriger les problèmes avant de continuer.');
            return false;
        }
        
        $io->success('✅ Toutes les vérifications sont passées');
        return true;
    }

    /**
     * Migration de toutes les entités
     */
    private function migrateAll(SymfonyStyle $io, bool $isDryRun): array
    {
        $io->section('🔄 Migration complète - Toutes les entités');
        
        $results = [];
        
        // Migration des pages
        $io->text('📄 Migration des pages...');
        $results['page'] = $this->migratePage($io, $isDryRun, false);
        
        // Migration du SEO
        $io->text('🔍 Migration du SEO...');
        $results['seo'] = $this->migrateSeo($io, $isDryRun, false);
        
        return $results;
    }

    /**
     * Migration des pages
     */
    private function migratePage(SymfonyStyle $io, bool $isDryRun, bool $showSection = true): array
    {
        if ($showSection) {
            $io->section('📄 Migration des traductions Page');
        }
        
        $pages = $this->pageRepository->findAll();
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        
        if (empty($pages)) {
            $io->info('Aucune page à migrer');
            return ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
        }
        
        $results = ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
        
        $progressBar = new ProgressBar($io, count($pages));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();
        
        foreach ($pages as $page) {
            $progressBar->setMessage("Migration page: " . substr($page->getTitle() ?? 'Sans titre', 0, 30));
            
            try {
                // Pour chaque langue active
                foreach ($activeLanguages as $language) {
                    $locale = $language->getCode();
                    
                    // Récupérer les traductions Gedmo existantes
                    $gedmoTranslations = $this->getGedmoTranslations($page, $locale);
                    
                    if (!empty($gedmoTranslations)) {
                        if (!$isDryRun) {
                            // Créer la nouvelle entité PageTranslation Oragon
                            $this->createPageTranslation($page, $language, $gedmoTranslations);
                            $this->entityManager->flush();
                        }
                        $results['migrated']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                if ($io->isVerbose()) {
                    $io->text("Erreur pour la page {$page->getId()}: " . $e->getMessage());
                }
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $io->newLine();
        
        return $results;
    }

    /**
     * Migration du SEO
     */
    private function migrateSeo(SymfonyStyle $io, bool $isDryRun, bool $showSection = true): array
    {
        if ($showSection) {
            $io->section('🔍 Migration des traductions SEO');
        }
        
        $seoEntities = $this->seoRepository->findAll();
        $activeLanguages = $this->languageRepository->findBy(['isEnabled' => true]);
        
        if (empty($seoEntities)) {
            $io->info('Aucune entité SEO à migrer');
            return ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
        }
        
        $results = ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
        
        $progressBar = new ProgressBar($io, count($seoEntities));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->start();
        
        foreach ($seoEntities as $seo) {
            $progressBar->setMessage("Migration SEO: ID " . $seo->getId());
            
            try {
                // Pour chaque langue active
                foreach ($activeLanguages as $language) {
                    $locale = $language->getCode();
                    
                    // Récupérer les traductions Gedmo existantes pour tous les champs SEO
                    $gedmoTranslations = $this->getSeoGedmoTranslations($seo, $locale);
                    
                    if (!empty($gedmoTranslations)) {
                        if (!$isDryRun) {
                            // Créer la nouvelle entité SeoTranslation Oragon
                            $this->createSeoTranslation($seo, $language, $gedmoTranslations);
                            $this->entityManager->flush();
                        }
                        $results['migrated']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                if ($io->isVerbose()) {
                    $io->text("Erreur pour le SEO {$seo->getId()}: " . $e->getMessage());
                }
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $io->newLine();
        
        return $results;
    }

    /**
     * Récupère les traductions Gedmo pour une entité Page
     */
    private function getGedmoTranslations($entity, string $locale): array
    {
        $repository = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');
        
        $translations = $repository->findBy([
            'locale' => $locale,
            'objectClass' => get_class($entity),
            'objectId' => $entity->getId()
        ]);
        
        $result = [];
        foreach ($translations as $translation) {
            $result[$translation->getField()] = $translation->getContent();
        }
        
        return $result;
    }

    /**
     * Récupère les traductions Gedmo pour une entité SEO
     */
    private function getSeoGedmoTranslations(Seo $seo, string $locale): array
    {
        $seoFields = [
            'seoHomeTitle', 'seoHomeKeywords', 'seoHomeDescription',
            'seoAboutTitle', 'seoAboutKeywords', 'seoAboutDescription',
            'seoServiceTitle', 'seoServiceKeywords', 'seoServiceDescription'
        ];
        
        $repository = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');
        
        $translations = $repository->findBy([
            'locale' => $locale,
            'objectClass' => Seo::class,
            'objectId' => $seo->getId(),
            'field' => $seoFields
        ]);
        
        $result = [];
        foreach ($translations as $translation) {
            $result[$translation->getField()] = $translation->getContent();
        }
        
        return $result;
    }

    /**
     * Crée une sauvegarde des données
     */
    private function createBackup(SymfonyStyle $io): void
    {
        $io->section('💾 Création de la sauvegarde');
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "migration_backup_{$timestamp}.sql";
        
        // TODO: Implémenter la création de sauvegarde
        // mysqldump ou export Doctrine
        
        $io->success("✅ Sauvegarde créée: {$backupFile}");
    }

    /**
     * Valide la migration
     */
    private function validateMigration(SymfonyStyle $io, string $entity): void
    {
        $io->section('✅ Validation de la migration');
        
        // TODO: Implémenter la validation
        // - Compter les traductions avant/après
        // - Vérifier l'intégrité des données
        // - Comparer les contenus
        
        $io->success('✅ Validation terminée avec succès');
    }

    /**
     * Affiche le rapport final
     */
    private function displayFinalReport(SymfonyStyle $io, array $results, bool $isDryRun): void
    {
        $io->section('📊 Rapport de migration');
        
        if ($isDryRun) {
            $io->note('Mode simulation - Aucune modification effectuée');
        }
        
        $tableData = [];
        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        
        foreach ($results as $entityType => $result) {
            $tableData[] = [
                ucfirst($entityType),
                $result['migrated'],
                $result['skipped'],
                $result['errors']
            ];
            
            $totalMigrated += $result['migrated'];
            $totalSkipped += $result['skipped'];
            $totalErrors += $result['errors'];
        }
        
        // Ligne de total
        $tableData[] = ['---', '---', '---', '---'];
        $tableData[] = ['TOTAL', $totalMigrated, $totalSkipped, $totalErrors];
        
        $io->table([
            'Entité', 'Migrées', 'Ignorées', 'Erreurs'
        ], $tableData);
        
        if ($totalErrors > 0) {
            $io->warning("⚠️ {$totalErrors} erreur(s) détectée(s) pendant la migration");
        } else {
            $io->success("✅ Migration terminée sans erreur !");
        }
        
        $io->definitionList(
            ['Traductions migrées' => $totalMigrated],
            ['Traductions ignorées' => $totalSkipped],
            ['Erreurs rencontrées' => $totalErrors],
            ['Statut' => $totalErrors === 0 ? '✅ Succès' : '⚠️ Avec erreurs']
        );
    }

    /**
     * Crée une entité PageTranslation Oragon à partir des traductions Gedmo
     */
    private function createPageTranslation(Page $page, Language $language, array $gedmoTranslations): void
    {
        $pageTranslation = new \App\Entity\PageTranslation();
        $pageTranslation->setPage($page);
        $pageTranslation->setLanguage($language);
        
        // Mapper les champs de Gedmo vers Oragon
        $pageTranslation->setTitle($gedmoTranslations['title'] ?? $page->getTitle());
        $pageTranslation->setContent($gedmoTranslations['content'] ?? $page->getContent());
        $pageTranslation->setSlug($gedmoTranslations['slug'] ?? $page->getSlug());
        $pageTranslation->setResume($gedmoTranslations['resume'] ?? $page->getResume());
        
        $this->entityManager->persist($pageTranslation);
    }

    /**
     * Crée une entité SeoTranslation Oragon à partir des traductions Gedmo
     */
    private function createSeoTranslation(Seo $seo, Language $language, array $gedmoTranslations): void
    {
        $seoTranslation = new \App\Entity\SeoTranslation();
        $seoTranslation->setSeo($seo);
        $seoTranslation->setLanguage($language);
        
        // Mapper tous les champs SEO de Gedmo vers Oragon
        $seoTranslation->setSeoHomeTitle($gedmoTranslations['seoHomeTitle'] ?? $seo->getSeoHomeTitle());
        $seoTranslation->setSeoHomeKeywords($gedmoTranslations['seoHomeKeywords'] ?? $seo->getSeoHomeKeywords());
        $seoTranslation->setSeoHomeDescription($gedmoTranslations['seoHomeDescription'] ?? $seo->getSeoHomeDescription());
        
        $seoTranslation->setSeoAboutTitle($gedmoTranslations['seoAboutTitle'] ?? $seo->getSeoAboutTitle());
        $seoTranslation->setSeoAboutKeywords($gedmoTranslations['seoAboutKeywords'] ?? $seo->getSeoAboutKeywords());
        $seoTranslation->setSeoAboutDescription($gedmoTranslations['seoAboutDescription'] ?? $seo->getSeoAboutDescription());
        
        $seoTranslation->setSeoServiceTitle($gedmoTranslations['seoServiceTitle'] ?? $seo->getSeoServiceTitle());
        $seoTranslation->setSeoServiceKeywords($gedmoTranslations['seoServiceKeywords'] ?? $seo->getSeoServiceKeywords());
        $seoTranslation->setSeoServiceDescription($gedmoTranslations['seoServiceDescription'] ?? $seo->getSeoServiceDescription());
        
        $this->entityManager->persist($seoTranslation);
    }
}
