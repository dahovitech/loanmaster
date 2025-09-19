<?php

namespace App\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:pwa:manage',
    description: 'Gestion des fonctionnalités PWA (cache, service worker, notifications)'
)]
class PWAManageCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, 'Vider tous les caches PWA')
            ->addOption('generate-icons', null, InputOption::VALUE_NONE, 'Regénérer les icônes PWA')
            ->addOption('validate-sw', null, InputOption::VALUE_NONE, 'Valider le service worker')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Afficher les statistiques PWA')
            ->addOption('update-manifest', null, InputOption::VALUE_NONE, 'Mettre à jour le manifest')
            ->setHelp('
Cette commande permet de gérer les fonctionnalités PWA de l\'application.

Exemples d\'utilisation:
  <info>php bin/console app:pwa:manage --clear-cache</info>     Vider les caches PWA
  <info>php bin/console app:pwa:manage --generate-icons</info>  Regénérer les icônes
  <info>php bin/console app:pwa:manage --stats</info>           Voir les statistiques
  <info>php bin/console app:pwa:manage --validate-sw</info>     Valider le service worker
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        $io->title('🚀 Gestionnaire PWA LoanMaster');

        // Clear cache
        if ($input->getOption('clear-cache')) {
            $this->clearPWACache($io, $projectDir);
        }

        // Generate icons
        if ($input->getOption('generate-icons')) {
            $this->generateIcons($io, $projectDir);
        }

        // Validate service worker
        if ($input->getOption('validate-sw')) {
            $this->validateServiceWorker($io, $projectDir);
        }

        // Show stats
        if ($input->getOption('stats')) {
            $this->showPWAStats($io, $projectDir);
        }

        // Update manifest
        if ($input->getOption('update-manifest')) {
            $this->updateManifest($io, $projectDir);
        }

        // Si aucune option spécifiée, afficher le menu interactif
        if (!$input->getOption('clear-cache') && 
            !$input->getOption('generate-icons') && 
            !$input->getOption('validate-sw') && 
            !$input->getOption('stats') &&
            !$input->getOption('update-manifest')) {
            $this->showInteractiveMenu($io, $projectDir);
        }

        return Command::SUCCESS;
    }

    private function clearPWACache(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('🧹 Nettoyage des caches PWA');

        $publicDir = $projectDir . '/public';
        $cacheFiles = [
            'sw.js',
            'manifest.json'
        ];

        foreach ($cacheFiles as $file) {
            $filePath = $publicDir . '/' . $file;
            if (file_exists($filePath)) {
                // Ajouter un timestamp pour forcer la mise à jour
                $content = file_get_contents($filePath);
                $content = preg_replace('/CACHE_VERSION = [\'"].*?[\'"]/', 
                    "CACHE_VERSION = 'loanmaster-v" . date('Y.m.d.His') . "'", $content);
                file_put_contents($filePath, $content);
                $io->writeln("✅ Mis à jour: <info>$file</info>");
            }
        }

        $io->success('Cache PWA nettoyé avec succès !');
    }

    private function generateIcons(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('🎨 Génération des icônes PWA');

        $scriptPath = $projectDir . '/generate_pwa_icons.sh';
        
        if (!file_exists($scriptPath)) {
            $io->error('Script de génération d\'icônes non trouvé');
            return;
        }

        $io->writeln('Exécution du script de génération...');
        
        $process = proc_open(
            "bash $scriptPath",
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $projectDir
        );

        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            
            if ($returnCode === 0) {
                $io->writeln($output);
                $io->success('Icônes générées avec succès !');
            } else {
                $io->error('Erreur lors de la génération: ' . $error);
            }
        } else {
            $io->error('Impossible d\'exécuter le script de génération');
        }
    }

    private function validateServiceWorker(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('🔍 Validation du Service Worker');

        $swPath = $projectDir . '/public/sw.js';
        
        if (!file_exists($swPath)) {
            $io->error('Service Worker non trouvé: ' . $swPath);
            return;
        }

        $content = file_get_contents($swPath);
        $errors = [];
        $warnings = [];

        // Vérifications basiques
        if (!str_contains($content, 'CACHE_VERSION')) {
            $errors[] = 'Variable CACHE_VERSION manquante';
        }

        if (!str_contains($content, 'addEventListener(\'install\'')) {
            $errors[] = 'Event listener install manquant';
        }

        if (!str_contains($content, 'addEventListener(\'activate\'')) {
            $errors[] = 'Event listener activate manquant';
        }

        if (!str_contains($content, 'addEventListener(\'fetch\'')) {
            $errors[] = 'Event listener fetch manquant';
        }

        // Vérifications avancées
        if (!str_contains($content, 'skipWaiting')) {
            $warnings[] = 'skipWaiting() non utilisé - les mises à jour peuvent être retardées';
        }

        if (!str_contains($content, 'clients.claim')) {
            $warnings[] = 'clients.claim() non utilisé - contrôle immédiat non activé';
        }

        // Affichage des résultats
        if (empty($errors) && empty($warnings)) {
            $io->success('✅ Service Worker valide !');
        } else {
            if (!empty($errors)) {
                $io->error('Erreurs trouvées:');
                foreach ($errors as $error) {
                    $io->writeln("  ❌ $error");
                }
            }

            if (!empty($warnings)) {
                $io->warning('Avertissements:');
                foreach ($warnings as $warning) {
                    $io->writeln("  ⚠️  $warning");
                }
            }
        }

        // Taille du fichier
        $size = filesize($swPath);
        $io->writeln("📏 Taille du Service Worker: " . $this->formatBytes($size));
    }

    private function showPWAStats(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('📊 Statistiques PWA');

        $publicDir = $projectDir . '/public';
        
        // Vérifier les fichiers PWA essentiels
        $pwaFiles = [
            'manifest.json' => 'Manifest PWA',
            'sw.js' => 'Service Worker',
            'offline.html' => 'Page hors ligne',
            'icons/icon-192x192.png' => 'Icône 192x192',
            'icons/icon-512x512.png' => 'Icône 512x512',
        ];

        $io->writeln('<info>📁 Fichiers PWA:</info>');
        foreach ($pwaFiles as $file => $description) {
            $filePath = $publicDir . '/' . $file;
            if (file_exists($filePath)) {
                $size = $this->formatBytes(filesize($filePath));
                $io->writeln("  ✅ $description: <comment>$size</comment>");
            } else {
                $io->writeln("  ❌ $description: <error>Manquant</error>");
            }
        }

        // Compter les icônes
        $iconDir = $publicDir . '/icons';
        if (is_dir($iconDir)) {
            $iconCount = count(glob($iconDir . '/*.png'));
            $io->writeln("  🎨 Total icônes: <comment>$iconCount</comment>");
        }

        // Vérifier les templates PWA
        $templateDir = $projectDir . '/templates/pwa';
        if (is_dir($templateDir)) {
            $templateCount = count(glob($templateDir . '/*.twig'));
            $io->writeln("  📄 Templates PWA: <comment>$templateCount</comment>");
        }

        $io->newLine();
        $io->success('Analyse terminée !');
    }

    private function updateManifest(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('📱 Mise à jour du manifest');

        $manifestPath = $projectDir . '/public/manifest.json';
        
        if (!file_exists($manifestPath)) {
            $io->error('Manifest non trouvé');
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        // Mettre à jour la version ou d'autres métadonnées
        $manifest['version'] = date('Y.m.d.H.i');
        $manifest['updated'] = date('c');

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $io->success('Manifest mis à jour !');
    }

    private function showInteractiveMenu(SymfonyStyle $io, string $projectDir): void
    {
        $choice = $io->choice(
            'Que souhaitez-vous faire ?',
            [
                '1' => 'Afficher les statistiques PWA',
                '2' => 'Valider le Service Worker',
                '3' => 'Regénérer les icônes',
                '4' => 'Vider les caches PWA',
                '5' => 'Mettre à jour le manifest',
                '0' => 'Quitter'
            ],
            '1'
        );

        switch ($choice) {
            case '1':
                $this->showPWAStats($io, $projectDir);
                break;
            case '2':
                $this->validateServiceWorker($io, $projectDir);
                break;
            case '3':
                $this->generateIcons($io, $projectDir);
                break;
            case '4':
                $this->clearPWACache($io, $projectDir);
                break;
            case '5':
                $this->updateManifest($io, $projectDir);
                break;
            case '0':
                $io->writeln('Au revoir ! 👋');
                break;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}
