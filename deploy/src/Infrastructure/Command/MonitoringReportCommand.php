<?php

namespace App\Infrastructure\Command;

use App\Infrastructure\Service\MonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:monitoring:report',
    description: 'Génère des rapports de monitoring et analyse les métriques'
)]
class MonitoringReportCommand extends Command
{
    public function __construct(
        private MonitoringService $monitoringService,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Format de sortie (json, table, csv)', 'table')
            ->addOption('period', 'p', InputOption::VALUE_OPTIONAL, 'Période d\'analyse (1h, 24h, 7d, 30d)', '24h')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Fichier de sortie', null)
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type de rapport (performance, security, business, all)', 'all')
            ->addOption('live', 'l', InputOption::VALUE_NONE, 'Mode monitoring en temps réel')
            ->setHelp('
Cette commande génère des rapports détaillés de monitoring pour LoanMaster.

Exemples d\'utilisation:
  <info>php bin/console app:monitoring:report</info>                        Rapport complet dernières 24h
  <info>php bin/console app:monitoring:report --type=security --period=7d</info>  Rapport sécurité 7 jours
  <info>php bin/console app:monitoring:report --format=json --output=report.json</info>  Export JSON
  <info>php bin/console app:monitoring:report --live</info>                 Monitoring temps réel
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        
        $format = $input->getOption('format');
        $period = $input->getOption('period');
        $outputFile = $input->getOption('output');
        $type = $input->getOption('type');
        $isLive = $input->getOption('live');

        $io->title('📊 Rapport de Monitoring LoanMaster');

        if ($isLive) {
            return $this->runLiveMonitoring($io);
        }

        // Générer le rapport
        $report = $this->generateReport($type, $period, $projectDir);

        // Afficher ou sauvegarder le rapport
        $this->outputReport($io, $report, $format, $outputFile);

        return Command::SUCCESS;
    }

    private function generateReport(string $type, string $period, string $projectDir): array
    {
        $logDir = $projectDir . '/var/log';
        $endTime = new \DateTime();
        $startTime = $this->calculateStartTime($period, $endTime);

        $report = [
            'metadata' => [
                'type' => $type,
                'period' => $period,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'generated_at' => new \DateTime(),
            ],
            'summary' => [],
            'details' => []
        ];

        // Analyser les logs selon le type
        if ($type === 'all' || $type === 'performance') {
            $report['details']['performance'] = $this->analyzePerformanceLogs($logDir, $startTime, $endTime);
        }

        if ($type === 'all' || $type === 'security') {
            $report['details']['security'] = $this->analyzeSecurityLogs($logDir, $startTime, $endTime);
        }

        if ($type === 'all' || $type === 'business') {
            $report['details']['business'] = $this->analyzeBusinessLogs($logDir, $startTime, $endTime);
        }

        // Générer le résumé
        $report['summary'] = $this->generateSummary($report['details']);

        return $report;
    }

    private function analyzePerformanceLogs(string $logDir, \DateTime $startTime, \DateTime $endTime): array
    {
        $performanceFile = $logDir . '/performance.log';
        
        if (!file_exists($performanceFile)) {
            return ['error' => 'Fichier de log performance non trouvé'];
        }

        $metrics = [
            'total_requests' => 0,
            'avg_response_time' => 0,
            'slow_requests' => 0,
            'memory_usage' => [],
            'database_queries' => [],
            'cache_operations' => [],
            'errors' => 0
        ];

        $lines = $this->readLogFile($performanceFile, $startTime, $endTime);
        $responseTimes = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;

            if (isset($data['context']['metric'])) {
                $metric = $data['context']['metric'];
                
                if ($metric === 'request') {
                    $metrics['total_requests']++;
                    $duration = $data['context']['value'] ?? 0;
                    $responseTimes[] = $duration;
                    
                    if ($duration > 2000) {
                        $metrics['slow_requests']++;
                    }
                }
                
                if (str_starts_with($metric, 'database_query')) {
                    $metrics['database_queries'][] = $data['context'];
                }
                
                if (str_starts_with($metric, 'cache_operation')) {
                    $metrics['cache_operations'][] = $data['context'];
                }
            }

            if ($data['level_name'] === 'ERROR') {
                $metrics['errors']++;
            }
        }

        if (!empty($responseTimes)) {
            $metrics['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
            $metrics['min_response_time'] = min($responseTimes);
            $metrics['max_response_time'] = max($responseTimes);
            $metrics['p95_response_time'] = $this->calculatePercentile($responseTimes, 95);
        }

        return $metrics;
    }

    private function analyzeSecurityLogs(string $logDir, \DateTime $startTime, \DateTime $endTime): array
    {
        $securityFile = $logDir . '/security.log';
        
        if (!file_exists($securityFile)) {
            return ['error' => 'Fichier de log sécurité non trouvé'];
        }

        $metrics = [
            'login_attempts' => 0,
            'login_successes' => 0,
            'login_failures' => 0,
            'suspicious_activities' => 0,
            'blocked_ips' => [],
            'failed_login_by_ip' => [],
            'security_events' => []
        ];

        $lines = $this->readLogFile($securityFile, $startTime, $endTime);

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;

            if (isset($data['context']['action'])) {
                $action = $data['context']['action'];
                
                if ($action === 'security.login_success') {
                    $metrics['login_successes']++;
                    $metrics['login_attempts']++;
                }
                
                if ($action === 'security.login_failure') {
                    $metrics['login_failures']++;
                    $metrics['login_attempts']++;
                    
                    $ip = $data['context']['data']['ip'] ?? 'unknown';
                    $metrics['failed_login_by_ip'][$ip] = ($metrics['failed_login_by_ip'][$ip] ?? 0) + 1;
                }
                
                if (str_starts_with($action, 'security.')) {
                    $metrics['security_events'][] = [
                        'action' => $action,
                        'timestamp' => $data['datetime'],
                        'data' => $data['context']['data'] ?? []
                    ];
                }
            }
        }

        // Détecter les IPs suspectes (plus de 10 échecs)
        foreach ($metrics['failed_login_by_ip'] as $ip => $failures) {
            if ($failures > 10) {
                $metrics['blocked_ips'][] = $ip;
                $metrics['suspicious_activities']++;
            }
        }

        return $metrics;
    }

    private function analyzeBusinessLogs(string $logDir, \DateTime $startTime, \DateTime $endTime): array
    {
        $businessFile = $logDir . '/business.log';
        
        if (!file_exists($businessFile)) {
            return ['error' => 'Fichier de log business non trouvé'];
        }

        $metrics = [
            'loan_events' => [],
            'payment_events' => [],
            'user_activities' => [],
            'total_events' => 0
        ];

        $lines = $this->readLogFile($businessFile, $startTime, $endTime);

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;

            $metrics['total_events']++;

            if (isset($data['context']['event'])) {
                $event = $data['context']['event'];
                
                if (str_starts_with($event, 'loan.')) {
                    $metrics['loan_events'][] = [
                        'event' => $event,
                        'timestamp' => $data['datetime'],
                        'data' => $data['context']['data'] ?? []
                    ];
                }
                
                if (str_starts_with($event, 'payment.')) {
                    $metrics['payment_events'][] = [
                        'event' => $event,
                        'timestamp' => $data['datetime'],
                        'data' => $data['context']['data'] ?? []
                    ];
                }
            }
        }

        return $metrics;
    }

    private function generateSummary(array $details): array
    {
        $summary = [
            'status' => 'healthy',
            'alerts' => [],
            'recommendations' => []
        ];

        // Analyser les performances
        if (isset($details['performance'])) {
            $perf = $details['performance'];
            
            if ($perf['slow_requests'] > 0) {
                $summary['alerts'][] = "⚠️ {$perf['slow_requests']} requêtes lentes détectées";
                $summary['status'] = 'warning';
            }
            
            if ($perf['errors'] > 10) {
                $summary['alerts'][] = "🔴 {$perf['errors']} erreurs détectées";
                $summary['status'] = 'critical';
            }
            
            if (isset($perf['avg_response_time']) && $perf['avg_response_time'] > 1000) {
                $summary['recommendations'][] = "Optimiser les performances - temps de réponse moyen élevé";
            }
        }

        // Analyser la sécurité
        if (isset($details['security'])) {
            $sec = $details['security'];
            
            if ($sec['suspicious_activities'] > 0) {
                $summary['alerts'][] = "🚨 {$sec['suspicious_activities']} activités suspectes détectées";
                $summary['status'] = 'critical';
            }
            
            if ($sec['login_failures'] > $sec['login_successes']) {
                $summary['recommendations'][] = "Vérifier la sécurité des comptes - nombreux échecs de connexion";
            }
        }

        return $summary;
    }

    private function outputReport(SymfonyStyle $io, array $report, string $format, ?string $outputFile): void
    {
        switch ($format) {
            case 'json':
                $content = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'csv':
                $content = $this->convertToCSV($report);
                break;
            default:
                $this->displayTableReport($io, $report);
                return;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $content);
            $io->success("Rapport sauvegardé dans: $outputFile");
        } else {
            $io->writeln($content);
        }
    }

    private function displayTableReport(SymfonyStyle $io, array $report): void
    {
        $io->section('📋 Résumé');
        
        $summary = $report['summary'];
        $statusIcon = match($summary['status']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '🔴',
            default => '❓'
        };
        
        $io->writeln("Statut global: $statusIcon {$summary['status']}");
        
        if (!empty($summary['alerts'])) {
            $io->warning('Alertes:');
            foreach ($summary['alerts'] as $alert) {
                $io->writeln("  $alert");
            }
        }
        
        if (!empty($summary['recommendations'])) {
            $io->note('Recommandations:');
            foreach ($summary['recommendations'] as $rec) {
                $io->writeln("  💡 $rec");
            }
        }

        // Afficher les détails
        foreach ($report['details'] as $section => $data) {
            if (isset($data['error'])) {
                $io->warning("$section: {$data['error']}");
                continue;
            }
            
            $io->section(ucfirst($section));
            $this->displaySectionData($io, $data);
        }
    }

    private function displaySectionData(SymfonyStyle $io, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) < 10) {
                    $io->writeln("$key: " . json_encode($value, JSON_PRETTY_PRINT));
                } else {
                    $io->writeln("$key: " . count($value) . " éléments");
                }
            } else {
                $io->writeln("$key: $value");
            }
        }
    }

    private function runLiveMonitoring(SymfonyStyle $io): int
    {
        $io->title('📊 Monitoring en temps réel');
        $io->writeln('Appuyez sur Ctrl+C pour arrêter');

        while (true) {
            $report = $this->monitoringService->generateReport();
            
            $io->writeln("\n" . (new \DateTime())->format('Y-m-d H:i:s') . " - Métriques système:");
            $io->writeln("  Mémoire: " . $this->formatBytes($report['memory_usage']['current']));
            $io->writeln("  Pic mémoire: " . $this->formatBytes($report['memory_usage']['peak']));
            
            if (!empty($report['application_metrics'])) {
                $io->writeln("  Métriques app: " . json_encode($report['application_metrics']));
            }

            sleep(5);
        }

        return Command::SUCCESS;
    }

    private function readLogFile(string $filePath, \DateTime $startTime, \DateTime $endTime): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $data = json_decode($line, true);
                if ($data && isset($data['datetime'])) {
                    $logTime = new \DateTime($data['datetime']);
                    if ($logTime >= $startTime && $logTime <= $endTime) {
                        $lines[] = $line;
                    }
                }
            }
            fclose($handle);
        }

        return $lines;
    }

    private function calculateStartTime(string $period, \DateTime $endTime): \DateTime
    {
        $startTime = clone $endTime;
        
        return match($period) {
            '1h' => $startTime->modify('-1 hour'),
            '24h' => $startTime->modify('-1 day'),
            '7d' => $startTime->modify('-7 days'),
            '30d' => $startTime->modify('-30 days'),
            default => $startTime->modify('-1 day')
        };
    }

    private function calculatePercentile(array $values, float $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        } else {
            $lower = $values[floor($index)];
            $upper = $values[ceil($index)];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function convertToCSV(array $report): string
    {
        // Implémentation simplifiée pour CSV
        $csv = "Section,Metric,Value\n";
        
        foreach ($report['details'] as $section => $data) {
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $csv .= "$section,$key,$value\n";
                }
            }
        }
        
        return $csv;
    }
}
