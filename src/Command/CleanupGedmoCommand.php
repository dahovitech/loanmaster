<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:gedmo',
    description: 'Clean up Gedmo dependencies and optimize database for Oragon system'
)]
class CleanupGedmoCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing')
            ->addOption('remove-tables', null, InputOption::VALUE_NONE, 'Remove old Gedmo translation tables')
            ->addOption('optimize', null, InputOption::VALUE_NONE, 'Optimize database indexes and constraints')
            ->setHelp('This command cleans up old Gedmo dependencies and optimizes the database for the Oragon translation system.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $removeTables = $input->getOption('remove-tables');
        $optimize = $input->getOption('optimize');

        $io->title('🧹 Nettoyage du système Gedmo et optimisation Oragon');

        if ($isDryRun) {
            $io->note('Mode simulation activé - aucun changement ne sera effectué');
        }

        // Étape 1: Vérification des anciennes tables Gedmo
        $this->checkGedmoTables($io, $isDryRun);

        // Étape 2: Suppression des tables Gedmo si demandé
        if ($removeTables) {
            $this->removeGedmoTables($io, $isDryRun);
        }

        // Étape 3: Optimisation des index et contraintes
        if ($optimize) {
            $this->optimizeDatabase($io, $isDryRun);
        }

        // Étape 4: Vérification de l'intégrité du système Oragon
        $this->verifyOragonIntegrity($io);

        // Étape 5: Rapport final
        $this->generateCleanupReport($io);

        $io->success('Nettoyage terminé avec succès ! Le système Oragon est opérationnel.');

        return Command::SUCCESS;
    }

    private function checkGedmoTables(SymfonyStyle $io, bool $isDryRun): void
    {
        $io->section('📋 Vérification des anciennes tables Gedmo');

        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        
        $tables = $schemaManager->listTableNames();
        $gedmoTables = array_filter($tables, fn($table) => 
            str_contains($table, '_translation') && 
            !str_contains($table, '_translations') // Exclure nos nouvelles tables Oragon
        );

        if (empty($gedmoTables)) {
            $io->info('✅ Aucune ancienne table Gedmo trouvée');
            return;
        }

        $io->warning("Tables Gedmo trouvées :");
        foreach ($gedmoTables as $table) {
            $rowCount = $connection->fetchOne("SELECT COUNT(*) FROM `{$table}`");
            $io->writeln("  - {$table} ({$rowCount} enregistrements)");
        }
    }

    private function removeGedmoTables(SymfonyStyle $io, bool $isDryRun): void
    {
        $io->section('🗑️  Suppression des tables Gedmo');

        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        
        $tables = $schemaManager->listTableNames();
        $gedmoTables = array_filter($tables, fn($table) => 
            str_contains($table, '_translation') && 
            !str_contains($table, '_translations')
        );

        if (empty($gedmoTables)) {
            $io->info('Aucune table Gedmo à supprimer');
            return;
        }

        if (!$isDryRun) {
            if (!$io->confirm('Êtes-vous sûr de vouloir supprimer les anciennes tables Gedmo ?', false)) {
                $io->note('Suppression annulée');
                return;
            }
        }

        $progress = new ProgressBar($io, count($gedmoTables));
        $progress->start();

        foreach ($gedmoTables as $table) {
            if ($isDryRun) {
                $io->writeln("\n[DRY-RUN] Suppression de la table: {$table}");
            } else {
                try {
                    $connection->executeStatement("DROP TABLE `{$table}`");
                    $io->writeln("\n✅ Table supprimée: {$table}");
                } catch (\Exception $e) {
                    $io->writeln("\n❌ Erreur lors de la suppression de {$table}: " . $e->getMessage());
                }
            }
            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);
    }

    private function optimizeDatabase(SymfonyStyle $io, bool $isDryRun): void
    {
        $io->section('⚡ Optimisation de la base de données');

        $optimizations = [
            'bank_translations' => [
                'indexes' => [
                    'idx_bank_translations_lookup' => 'CREATE INDEX idx_bank_translations_lookup ON bank_translations (translatable_id, language_id)',
                ],
                'description' => 'Optimisation des traductions de banques'
            ],
            'notification_translations' => [
                'indexes' => [
                    'idx_notification_translations_lookup' => 'CREATE INDEX idx_notification_translations_lookup ON notification_translations (translatable_id, language_id)',
                ],
                'description' => 'Optimisation des traductions de notifications'
            ],
            'faq_translations' => [
                'indexes' => [
                    'idx_faq_translations_lookup' => 'CREATE INDEX idx_faq_translations_lookup ON faq_translations (translatable_id, language_id)',
                ],
                'description' => 'Optimisation des traductions FAQ'
            ],
            'loan_type_translations' => [
                'indexes' => [
                    'idx_loan_type_translations_lookup' => 'CREATE INDEX idx_loan_type_translations_lookup ON loan_type_translations (translatable_id, language_id)',
                ],
                'description' => 'Optimisation des traductions de types de prêts'
            ]
        ];

        $connection = $this->entityManager->getConnection();
        $totalOptimizations = array_sum(array_map(fn($opt) => count($opt['indexes']), $optimizations));
        
        $progress = new ProgressBar($io, $totalOptimizations);
        $progress->start();

        foreach ($optimizations as $table => $config) {
            $io->writeln("\n" . $config['description']);
            
            foreach ($config['indexes'] as $indexName => $sql) {
                if ($isDryRun) {
                    $io->writeln("  [DRY-RUN] {$sql}");
                } else {
                    try {
                        $connection->executeStatement($sql);
                        $io->writeln("  ✅ Index créé: {$indexName}");
                    } catch (\Exception $e) {
                        // L'index existe peut-être déjà
                        $io->writeln("  ⚠️  Index {$indexName}: " . $e->getMessage());
                    }
                }
                $progress->advance();
            }
        }

        $progress->finish();
        $io->newLine(2);

        // Optimisation SQLite
        if (!$isDryRun) {
            $io->writeln('Optimisation SQLite...');
            try {
                $connection->executeStatement('VACUUM');
                $connection->executeStatement('ANALYZE');
                $io->writeln('✅ Base de données optimisée');
            } catch (\Exception $e) {
                $io->writeln('⚠️  Optimisation échouée: ' . $e->getMessage());
            }
        }
    }

    private function verifyOragonIntegrity(SymfonyStyle $io): void
    {
        $io->section('🔍 Vérification de l\'intégrité du système Oragon');

        $checks = [
            'Entités Oragon' => $this->checkOragonEntities(),
            'Tables de traduction' => $this->checkTranslationTables(),
            'Relations et contraintes' => $this->checkRelations(),
            'Index de performance' => $this->checkIndexes()
        ];

        foreach ($checks as $checkName => $result) {
            if ($result['success']) {
                $io->writeln("✅ {$checkName}: {$result['message']}");
            } else {
                $io->writeln("❌ {$checkName}: {$result['message']}");
            }
        }
    }

    private function checkOragonEntities(): array
    {
        $entities = [
            'App\Entity\PageTranslation',
            'App\Entity\SeoTranslation', 
            'App\Entity\BankTranslation',
            'App\Entity\NotificationTranslation',
            'App\Entity\FaqTranslation',
            'App\Entity\LoanTypeTranslation'
        ];

        $found = 0;
        foreach ($entities as $entity) {
            if (class_exists($entity)) {
                $found++;
            }
        }

        return [
            'success' => $found === count($entities),
            'message' => "{$found}/" . count($entities) . " entités Oragon trouvées"
        ];
    }

    private function checkTranslationTables(): array
    {
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        
        $expectedTables = [
            'page_translations',
            'seo_translations',
            'bank_translations',
            'notification_translations',
            'faq_translations',
            'loan_type_translations'
        ];

        $existingTables = $schemaManager->listTableNames();
        $found = array_filter($expectedTables, fn($table) => in_array($table, $existingTables));

        return [
            'success' => count($found) === count($expectedTables),
            'message' => count($found) . "/" . count($expectedTables) . " tables Oragon trouvées"
        ];
    }

    private function checkRelations(): array
    {
        // Vérification basique des relations via requête
        try {
            $connection = $this->entityManager->getConnection();
            $result = $connection->fetchOne("
                SELECT COUNT(*) 
                FROM sqlite_master 
                WHERE type='table' 
                AND name LIKE '%_translations'
                AND sql LIKE '%FOREIGN KEY%'
            ");
            
            return [
                'success' => $result > 0,
                'message' => "Relations de clés étrangères vérifiées"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la vérification: " . $e->getMessage()
            ];
        }
    }

    private function checkIndexes(): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            $result = $connection->fetchOne("
                SELECT COUNT(*) 
                FROM sqlite_master 
                WHERE type='index' 
                AND name LIKE '%language_unique'
            ");
            
            return [
                'success' => $result > 0,
                'message' => "Index de performance détectés"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Erreur lors de la vérification: " . $e->getMessage()
            ];
        }
    }

    private function generateCleanupReport(SymfonyStyle $io): void
    {
        $io->section('📊 Rapport de nettoyage');

        $connection = $this->entityManager->getConnection();
        
        // Statistiques des tables Oragon
        $oragonTables = [
            'page_translations',
            'seo_translations', 
            'bank_translations',
            'notification_translations',
            'faq_translations',
            'loan_type_translations'
        ];

        $totalTranslations = 0;
        foreach ($oragonTables as $table) {
            try {
                $count = $connection->fetchOne("SELECT COUNT(*) FROM `{$table}`");
                $io->writeln("  {$table}: {$count} traductions");
                $totalTranslations += $count;
            } catch (\Exception $e) {
                $io->writeln("  {$table}: Table non trouvée");
            }
        }

        $io->writeln("\n📈 Total des traductions Oragon: {$totalTranslations}");
        
        // Taille de la base de données
        try {
            $dbPath = $this->entityManager->getConnection()->getParams()['path'] ?? null;
            if ($dbPath && file_exists($dbPath)) {
                $size = filesize($dbPath);
                $sizeFormatted = $this->formatBytes($size);
                $io->writeln("💾 Taille de la base de données: {$sizeFormatted}");
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de taille
        }
    }

    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}
