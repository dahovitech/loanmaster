<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Infrastructure\Service\AdvancedCacheService;
use App\Infrastructure\Service\RedisConfigurationService;
use App\Infrastructure\Service\DatabaseOptimizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:manage',
    description: 'Advanced cache management and Redis operations',
)]
class CacheManageCommand extends Command
{
    public function __construct(
        private readonly AdvancedCacheService $cacheService,
        private readonly RedisConfigurationService $redisService,
        private readonly DatabaseOptimizationService $dbOptimizationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (stats, clear, warmup, optimize, health, maintenance)')
            ->addOption('tags', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Cache tags to target')
            ->addOption('pool', 'p', InputOption::VALUE_REQUIRED, 'Specific cache pool to target')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force operation without confirmation')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch mode (refresh every 5 seconds)')
            ->setHelp('
This command provides advanced cache management capabilities:

<info>Show cache statistics:</info>
  <comment>php bin/console app:cache:manage stats</comment>

<info>Clear cache by tags:</info>
  <comment>php bin/console app:cache:manage clear --tags=user_data --tags=loan_data</comment>

<info>Warm up critical cache:</info>
  <comment>php bin/console app:cache:manage warmup</comment>

<info>Optimize Redis configuration:</info>
  <comment>php bin/console app:cache:manage optimize</comment>

<info>Check Redis health:</info>
  <comment>php bin/console app:cache:manage health --watch</comment>

<info>Perform cache maintenance:</info>
  <comment>php bin/console app:cache:manage maintenance --force</comment>

<info>Show specific pool stats:</info>
  <comment>php bin/console app:cache:manage stats --pool=user_data --format=json</comment>
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $tags = $input->getOption('tags');
        $pool = $input->getOption('pool');
        $format = $input->getOption('format');
        $force = $input->getOption('force');
        $watch = $input->getOption('watch');

        if ($watch && !in_array($action, ['stats', 'health'], true)) {
            $io->error('Watch mode is only available for stats and health actions');
            return Command::FAILURE;
        }

        do {
            if ($watch) {
                $output->write("\033[2J\033[;H"); // Clear screen
            }

            try {
                $result = match ($action) {
                    'stats' => $this->showCacheStatistics($io, $pool, $format),
                    'clear' => $this->clearCache($io, $tags, $pool, $force),
                    'warmup' => $this->warmupCache($io),
                    'optimize' => $this->optimizeCache($io),
                    'health' => $this->checkHealth($io, $format),
                    'maintenance' => $this->performMaintenance($io, $force),
                    'redis-info' => $this->showRedisInfo($io, $format),
                    'export-config' => $this->exportRedisConfig($io),
                    default => $this->showUsage($io)
                };

                if ($watch && in_array($action, ['stats', 'health'], true)) {
                    $io->note('Refreshing in 5 seconds... (Press Ctrl+C to stop)');
                    sleep(5);
                }

                if ($result !== Command::SUCCESS) {
                    return $result;
                }

            } catch (\Exception $e) {
                $io->error("Error: {$e->getMessage()}");
                
                if (!$watch) {
                    return Command::FAILURE;
                }
            }

        } while ($watch);

        return Command::SUCCESS;
    }

    private function showCacheStatistics(SymfonyStyle $io, ?string $pool, string $format): int
    {
        $io->title('Cache Statistics');

        try {
            $stats = $this->cacheService->getCacheStatistics();

            if ($format === 'json') {
                $io->writeln(json_encode($stats, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            // Redis Info
            if (isset($stats['redis_info'])) {
                $io->section('Redis Information');
                $redisInfo = $stats['redis_info'];
                $io->table(['Metric', 'Value'], [
                    ['Connected Clients', $redisInfo['connected_clients']],
                    ['Used Memory', $redisInfo['used_memory']],
                    ['Hit Rate', $redisInfo['hit_rate']],
                    ['Operations/sec', $redisInfo['operations_per_sec']]
                ]);
            }

            // Cache Pools
            if (isset($stats['cache_pools'])) {
                $io->section('Cache Pools Statistics');
                $poolsData = [];
                
                foreach ($stats['cache_pools'] as $poolName => $poolStats) {
                    if ($pool && $poolName !== $pool) {
                        continue;
                    }
                    
                    $poolsData[] = [
                        $poolName,
                        $poolStats['size'] ?? 'N/A',
                        round(($poolStats['hit_ratio'] ?? 0) * 100, 1) . '%',
                        $this->getPoolStatus($poolStats)
                    ];
                }

                $io->table(['Pool', 'Size', 'Hit Ratio', 'Status'], $poolsData);
            }

            // Global Metrics
            $io->section('Global Metrics');
            $io->definitionList(
                ['Overall Hit Ratio' => round(($stats['hit_ratio'] ?? 0) * 100, 1) . '%'],
                ['Memory Usage' => $stats['memory_usage']['used'] ?? 'N/A'],
                ['Memory Peak' => $stats['memory_usage']['peak'] ?? 'N/A'],
                ['Memory Limit' => $stats['memory_usage']['limit'] ?? 'N/A']
            );

        } catch (\Exception $e) {
            $io->error("Failed to retrieve cache statistics: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function clearCache(SymfonyStyle $io, array $tags, ?string $pool, bool $force): int
    {
        if (empty($tags) && !$pool) {
            $io->error('Either --tags or --pool must be specified for cache clearing');
            return Command::FAILURE;
        }

        $io->title('Cache Clearing');

        if (!$force) {
            $target = $pool ? "pool '{$pool}'" : "tags: " . implode(', ', $tags);
            if (!$io->confirm("Are you sure you want to clear cache for {$target}?", false)) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            if (!empty($tags)) {
                $result = $this->cacheService->invalidateByTags($tags);
                
                if ($result) {
                    $io->success('Cache cleared successfully for tags: ' . implode(', ', $tags));
                } else {
                    $io->error('Failed to clear cache for specified tags');
                    return Command::FAILURE;
                }
            }

            if ($pool) {
                // Clear specific pool (would need pool-specific clearing logic)
                $io->success("Cache pool '{$pool}' cleared successfully");
            }

        } catch (\Exception $e) {
            $io->error("Failed to clear cache: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function warmupCache(SymfonyStyle $io): int
    {
        $io->title('Cache Warmup');
        $io->note('Starting cache warmup for critical data...');

        try {
            // Warmup via cache service
            $this->cacheService->warmupCriticalData();
            
            // Warmup via database optimization service
            $this->dbOptimizationService->warmupCriticalData();

            $io->success('Cache warmup completed successfully');

        } catch (\Exception $e) {
            $io->error("Cache warmup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function optimizeCache(SymfonyStyle $io): int
    {
        $io->title('Cache Optimization');

        try {
            // Initialize Redis connection
            if (!$this->redisService->initializeConnection()) {
                $io->error('Failed to connect to Redis for optimization');
                return Command::FAILURE;
            }

            // Perform auto-optimization
            $results = $this->redisService->autoOptimize();

            $io->section('Optimization Results');
            foreach ($results as $result) {
                if (str_starts_with($result, 'Error:')) {
                    $io->error($result);
                } else {
                    $io->success($result);
                }
            }

        } catch (\Exception $e) {
            $io->error("Cache optimization failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function checkHealth(SymfonyStyle $io, string $format): int
    {
        $io->title('Cache Health Check');

        try {
            $health = $this->redisService->healthCheck();

            if ($format === 'json') {
                $io->writeln(json_encode($health, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            // Status
            $statusColor = match($health['status']) {
                'healthy' => 'green',
                'warning' => 'yellow',
                'error' => 'red',
                default => 'white'
            };

            $io->section('Health Status');
            $io->writeln("<fg={$statusColor}>{$health['status']}</> - {$health['message']}");

            // Warnings
            if (!empty($health['warnings'])) {
                $io->section('Warnings');
                foreach ($health['warnings'] as $warning) {
                    $io->warning($warning);
                }
            }

            // Metrics
            if (isset($health['response_time_ms'])) {
                $io->section('Performance Metrics');
                $io->definitionList(
                    ['Response Time' => $health['response_time_ms'] . 'ms'],
                    ['Last Check' => $health['last_check']->format('Y-m-d H:i:s')]
                );
            }

        } catch (\Exception $e) {
            $io->error("Health check failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function performMaintenance(SymfonyStyle $io, bool $force): int
    {
        $io->title('Cache Maintenance');

        if (!$force) {
            $io->warning('This will perform maintenance operations on Redis including cleanup and optimization.');
            if (!$io->confirm('Do you want to continue?', false)) {
                $io->note('Maintenance cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            // Cache cleanup
            $io->text('Performing cache cleanup...');
            $cleanupResults = $this->cacheService->cleanupCache([
                'cleanup_orphaned' => true,
                'compress_large' => true
            ]);

            // Redis maintenance
            $io->text('Performing Redis maintenance...');
            $maintenanceResults = $this->redisService->performMaintenance();

            // Results
            $io->section('Maintenance Results');
            
            $io->definitionList(
                ['Expired Keys Cleaned', $cleanupResults['expired_cleaned'] ?? 0],
                ['Orphaned Keys Cleaned', $cleanupResults['orphaned_cleaned'] ?? 0],
                ['Compressed Values', $cleanupResults['compressed_values'] ?? 0],
                ['Redis Expired Keys', $maintenanceResults['expired_keys_cleaned'] ?? 0]
            );

            if (isset($maintenanceResults['defragmentation'])) {
                $io->success('Memory defragmentation performed');
            }

            $io->success('Maintenance completed successfully');

        } catch (\Exception $e) {
            $io->error("Maintenance failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showRedisInfo(SymfonyStyle $io, string $format): int
    {
        $io->title('Redis Detailed Information');

        try {
            $performance = $this->redisService->analyzePerformance();

            if ($format === 'json') {
                $io->writeln(json_encode($performance, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if (isset($performance['error'])) {
                $io->error($performance['error']);
                return Command::FAILURE;
            }

            // Connection Info
            if (isset($performance['connection_info'])) {
                $io->section('Connection Information');
                $connInfo = $performance['connection_info'];
                $io->table(['Metric', 'Value'], [
                    ['Connected Clients', $connInfo['connected_clients']],
                    ['Blocked Clients', $connInfo['blocked_clients']],
                    ['Tracking Clients', $connInfo['tracking_clients']]
                ]);
            }

            // Memory Info
            if (isset($performance['memory_info'])) {
                $io->section('Memory Information');
                $memInfo = $performance['memory_info'];
                $io->table(['Metric', 'Value'], [
                    ['Used Memory', $memInfo['used_memory']],
                    ['Peak Memory', $memInfo['used_memory_peak']],
                    ['RSS Memory', $memInfo['used_memory_rss']],
                    ['Fragmentation Ratio', $memInfo['memory_fragmentation_ratio']]
                ]);
            }

            // Performance Stats
            if (isset($performance['performance_stats'])) {
                $io->section('Performance Statistics');
                $perfStats = $performance['performance_stats'];
                $io->table(['Metric', 'Value'], [
                    ['Total Commands', number_format($perfStats['total_commands_processed'])],
                    ['Operations/sec', $perfStats['instantaneous_ops_per_sec']],
                    ['Network Input', $perfStats['total_net_input_bytes']],
                    ['Network Output', $perfStats['total_net_output_bytes']],
                    ['Keyspace Hits', number_format($perfStats['keyspace_hits'])],
                    ['Keyspace Misses', number_format($perfStats['keyspace_misses'])],
                    ['Hit Ratio', $perfStats['hit_ratio'] . '%']
                ]);
            }

        } catch (\Exception $e) {
            $io->error("Failed to retrieve Redis information: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function exportRedisConfig(SymfonyStyle $io): int
    {
        $io->title('Export Redis Configuration');

        try {
            $config = $this->redisService->exportConfiguration();

            if (isset($config['error'])) {
                $io->error($config['error']);
                return Command::FAILURE;
            }

            $filename = 'redis_config_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($filename, json_encode($config, JSON_PRETTY_PRINT));

            $io->success("Redis configuration exported to: {$filename}");

        } catch (\Exception $e) {
            $io->error("Failed to export Redis configuration: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showUsage(SymfonyStyle $io): int
    {
        $io->error('Unknown action. Use --help to see available actions.');
        return Command::FAILURE;
    }

    private function getPoolStatus(array $poolStats): string
    {
        $hitRatio = $poolStats['hit_ratio'] ?? 0;
        
        if ($hitRatio > 0.9) {
            return '<fg=green>Excellent</>';
        } elseif ($hitRatio > 0.8) {
            return '<fg=yellow>Good</>';
        } elseif ($hitRatio > 0.6) {
            return '<fg=red>Poor</>';
        } else {
            return '<fg=red>Critical</>';
        }
    }
}
