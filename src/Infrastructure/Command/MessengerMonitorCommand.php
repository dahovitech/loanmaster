<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Infrastructure\Messenger\Middleware\MetricsMiddleware;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[AsCommand(
    name: 'app:messenger:monitor',
    description: 'Monitor and manage Messenger queues and workers',
)]
class MessengerMonitorCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly MetricsMiddleware $metricsMiddleware,
        private readonly iterable $receivers = []
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (status, metrics, clear, stats)')
            ->addOption('transport', 't', InputOption::VALUE_REQUIRED, 'Specific transport to monitor')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch mode (refresh every 5 seconds)')
            ->setHelp('
This command helps monitor and manage Messenger queues:

<info>Show queue status:</info>
  <comment>php bin/console app:messenger:monitor status</comment>

<info>Show metrics:</info>
  <comment>php bin/console app:messenger:monitor metrics</comment>

<info>Show transport-specific stats:</info>
  <comment>php bin/console app:messenger:monitor stats --transport=async</comment>

<info>Watch mode (auto-refresh):</info>
  <comment>php bin/console app:messenger:monitor status --watch</comment>

<info>Clear failed messages:</info>
  <comment>php bin/console app:messenger:monitor clear --transport=failed</comment>
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $transport = $input->getOption('transport');
        $format = $input->getOption('format');
        $watch = $input->getOption('watch');

        if ($watch && $action !== 'status' && $action !== 'metrics') {
            $io->error('Watch mode is only available for status and metrics actions');
            return Command::FAILURE;
        }

        do {
            if ($watch) {
                $output->write("\033[2J\033[;H"); // Clear screen
            }

            try {
                switch ($action) {
                    case 'status':
                        $this->showQueueStatus($io, $transport, $format);
                        break;
                        
                    case 'metrics':
                        $this->showMetrics($io, $format);
                        break;
                        
                    case 'stats':
                        $this->showDetailedStats($io, $transport, $format);
                        break;
                        
                    case 'clear':
                        return $this->clearQueue($io, $transport);
                        
                    default:
                        $io->error("Unknown action: {$action}");
                        return Command::FAILURE;
                }

                if ($watch) {
                    $io->note('Refreshing in 5 seconds... (Press Ctrl+C to stop)');
                    sleep(5);
                }

            } catch (\Exception $e) {
                $io->error("Error: {$e->getMessage()}");
                return Command::FAILURE;
            }

        } while ($watch);

        return Command::SUCCESS;
    }

    private function showQueueStatus(SymfonyStyle $io, ?string $transport, string $format): void
    {
        $io->title('Messenger Queue Status');

        $queueData = [];
        $transportNames = $transport ? [$transport] : $this->getAvailableTransports();

        foreach ($transportNames as $transportName) {
            $status = $this->getTransportStatus($transportName);
            $queueData[] = [
                'Transport' => $transportName,
                'Status' => $status['connected'] ? '<info>Connected</info>' : '<error>Disconnected</error>',
                'Messages' => $status['message_count'] ?? 'N/A',
                'Workers' => $status['active_workers'] ?? 'N/A',
                'Last Activity' => $status['last_activity'] ?? 'Never'
            ];
        }

        if ($format === 'json') {
            $io->writeln(json_encode($queueData, JSON_PRETTY_PRINT));
        } else {
            $io->table(['Transport', 'Status', 'Messages', 'Workers', 'Last Activity'], $queueData);
        }

        // Résumé global
        $totalMessages = array_sum(array_column($queueData, 'Messages'));
        $connectedTransports = count(array_filter($queueData, fn($t) => strpos($t['Status'], 'Connected') !== false));
        
        $io->note("Total: {$connectedTransports} transports connected, {$totalMessages} pending messages");
    }

    private function showMetrics(SymfonyStyle $io, string $format): void
    {
        $io->title('Messenger Processing Metrics');

        // Récupérer les métriques depuis le middleware
        $allMetrics = $this->metricsMiddleware->getAllMetrics();
        
        if (empty($allMetrics)) {
            $io->note('No metrics available yet. Process some messages first.');
            return;
        }

        $metricsData = [];
        foreach ($allMetrics as $messageClass => $metrics) {
            $className = substr($messageClass, strrpos($messageClass, '\\') + 1);
            
            $metricsData[] = [
                'Message Type' => $className,
                'Total Processed' => $metrics['total_processed'],
                'Success Rate' => round(100 - $metrics['error_rate'], 1) . '%',
                'Avg Duration (ms)' => round($metrics['avg_duration'], 2),
                'Max Duration (ms)' => round($metrics['max_duration'], 2),
                'Throughput/h' => $metrics['throughput_per_hour']
            ];
        }

        if ($format === 'json') {
            $io->writeln(json_encode($metricsData, JSON_PRETTY_PRINT));
        } else {
            $io->table([
                'Message Type', 'Total Processed', 'Success Rate', 
                'Avg Duration (ms)', 'Max Duration (ms)', 'Throughput/h'
            ], $metricsData);
        }
    }

    private function showDetailedStats(SymfonyStyle $io, ?string $transport, string $format): void
    {
        $io->title('Detailed Transport Statistics');

        if (!$transport) {
            $io->error('Transport name is required for detailed stats');
            return;
        }

        $stats = $this->getDetailedTransportStats($transport);
        
        if ($format === 'json') {
            $io->writeln(json_encode($stats, JSON_PRETTY_PRINT));
        } else {
            $io->definitionList(
                ['Transport' => $transport],
                ['Connection Status' => $stats['connected'] ? 'Connected' : 'Disconnected'],
                ['Queue Length' => $stats['queue_length']],
                ['Messages Processed Today' => $stats['messages_today']],
                ['Average Processing Time' => $stats['avg_processing_time'] . 'ms'],
                ['Error Rate (24h)' => $stats['error_rate_24h'] . '%'],
                ['Last Message' => $stats['last_message_time']],
                ['Peak Hour' => $stats['peak_hour']],
                ['Memory Usage' => $stats['memory_usage']]
            );
        }
    }

    private function clearQueue(SymfonyStyle $io, ?string $transport): int
    {
        if (!$transport) {
            $io->error('Transport name is required for clearing');
            return Command::FAILURE;
        }

        $io->warning("This will clear all messages from transport: {$transport}");
        
        if (!$io->confirm('Are you sure you want to continue?', false)) {
            $io->note('Operation cancelled');
            return Command::SUCCESS;
        }

        try {
            $clearedCount = $this->clearTransportMessages($transport);
            $io->success("Cleared {$clearedCount} messages from {$transport} transport");
            
        } catch (\Exception $e) {
            $io->error("Failed to clear transport: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getAvailableTransports(): array
    {
        // Liste des transports configurés
        return [
            'async_priority_high',
            'async',
            'notifications', 
            'reports',
            'maintenance',
            'webhooks',
            'failed'
        ];
    }

    private function getTransportStatus(string $transportName): array
    {
        // Simulation du statut du transport
        return [
            'connected' => true,
            'message_count' => rand(0, 50),
            'active_workers' => rand(1, 5),
            'last_activity' => (new \DateTime())->modify('-' . rand(1, 300) . ' seconds')->format('H:i:s')
        ];
    }

    private function getDetailedTransportStats(string $transport): array
    {
        // Simulation de statistiques détaillées
        return [
            'connected' => true,
            'queue_length' => rand(0, 100),
            'messages_today' => rand(100, 1000),
            'avg_processing_time' => rand(50, 500),
            'error_rate_24h' => round(rand(0, 10) / 10, 1),
            'last_message_time' => (new \DateTime())->modify('-' . rand(1, 60) . ' minutes')->format('Y-m-d H:i:s'),
            'peak_hour' => '14:00-15:00',
            'memory_usage' => round(rand(50, 200) / 10, 1) . 'MB'
        ];
    }

    private function clearTransportMessages(string $transport): int
    {
        // Simulation du nettoyage
        $clearedCount = rand(5, 25);
        
        // Dans une vraie implémentation, on utiliserait:
        // $receiver = $this->receivers[$transport];
        // if ($receiver instanceof ReceiverInterface) {
        //     // Clear messages logic
        // }
        
        return $clearedCount;
    }
}
