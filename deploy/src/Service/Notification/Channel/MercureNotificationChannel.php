<?php

namespace App\Service\Notification\Channel;

use App\Service\Notification\MercureNotificationService;
use App\Service\Notification\NotificationResult;
use Psr\Log\LoggerInterface;

/**
 * Canal de notification Mercure pour temps réel
 */
class MercureNotificationChannel implements ChannelInterface
{
    private MercureNotificationService $mercureService;
    private LoggerInterface $logger;
    private array $configuration;

    public function __construct(
        MercureNotificationService $mercureService,
        LoggerInterface $logger,
        array $configuration = []
    ) {
        $this->mercureService = $mercureService;
        $this->logger = $logger;
        $this->configuration = $configuration;
    }

    public function send(string $type, array $recipients, array $data, array $options = []): NotificationResult
    {
        $startTime = microtime(true);
        $deliveredCount = 0;
        $failedCount = 0;
        $errors = [];

        try {
            // Construction des topics et targets selon le type
            $topic = $this->getTopicForType($type);
            $targets = $this->buildTargets($recipients, $options);
            
            // Formatage des données
            $formattedData = $this->formatData($data, $options);
            
            // Publication via Mercure
            $success = $this->mercureService->publishNotification(
                $topic,
                $formattedData,
                $targets,
                $this->buildMercureOptions($type, $options)
            );
            
            if ($success) {
                $deliveredCount = count($recipients);
                $this->logger->info('Mercure notification sent successfully', [
                    'type' => $type,
                    'topic' => $topic,
                    'recipients_count' => count($recipients)
                ]);
            } else {
                $failedCount = count($recipients);
                $errors[] = 'Failed to publish Mercure notification';
            }
            
        } catch (\Exception $e) {
            $failedCount = count($recipients);
            $errors[] = $e->getMessage();
            
            $this->logger->error('Mercure channel error', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $executionTime = microtime(true) - $startTime;

        return new NotificationResult(
            uniqid('mercure_', true),
            $deliveredCount > 0,
            $deliveredCount,
            $failedCount,
            [
                'channel' => 'mercure',
                'topic' => $topic ?? null,
                'targets_count' => count($targets ?? []),
                'execution_time' => $executionTime
            ],
            $executionTime,
            $errors
        );
    }

    public function isAvailable(): bool
    {
        try {
            // Vérification simple de disponibilité du service Mercure
            return $this->mercureService !== null;
        } catch (\Exception $e) {
            $this->logger->warning('Mercure channel availability check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getName(): string
    {
        return 'mercure';
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function validateData(array $data): bool
    {
        // Validation basique - les données doivent être sérialisables en JSON
        try {
            json_encode($data, JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException $e) {
            $this->logger->warning('Mercure data validation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    public function formatData(array $data, array $options = []): array
    {
        // Ajout de métadonnées spécifiques au canal Mercure
        return array_merge($data, [
            'channel' => 'mercure',
            'timestamp' => date(DATE_ATOM),
            'notification_id' => $options['notification_id'] ?? uniqid('notif_', true),
            'real_time' => true,
            'interactive' => $options['interactive'] ?? true
        ]);
    }

    /**
     * Détermine le topic Mercure selon le type de notification
     */
    private function getTopicForType(string $type): string
    {
        return match ($type) {
            'loan_status_update' => 'loan_status_updates',
            'risk_alert' => 'risk_alerts', 
            'payment_reminder' => 'payment_notifications',
            'audit_alert' => 'audit_alerts',
            'system_maintenance' => 'system_notifications',
            default => 'general_notifications'
        };
    }

    /**
     * Construit les targets Mercure à partir des recipients
     */
    private function buildTargets(array $recipients, array $options): array
    {
        $targets = [];
        
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                // Format structuré
                if (isset($recipient['id'])) {
                    $targets[] = "/users/{$recipient['id']}";
                }
                
                if (isset($recipient['type']) && $recipient['type'] === 'role') {
                    $targets[] = "/roles/{$recipient['role']}";
                }
            } else {
                // Format simple - ID utilisateur
                $targets[] = "/users/{$recipient}";
            }
        }
        
        // Ajout de targets supplémentaires depuis les options
        if (!empty($options['additional_targets'])) {
            $targets = array_merge($targets, (array) $options['additional_targets']);
        }
        
        return array_unique($targets);
    }

    /**
     * Construit les options Mercure
     */
    private function buildMercureOptions(string $type, array $options): array
    {
        $mercureOptions = [
            'type' => $type,
            'priority' => $options['priority'] ?? 'normal',
            'category' => $options['category'] ?? 'general'
        ];
        
        // Options spécifiques selon le type
        switch ($type) {
            case 'risk_alert':
                $mercureOptions['requiresAction'] = true;
                $mercureOptions['dismissible'] = false;
                break;
                
            case 'payment_reminder':
                $mercureOptions['actionable'] = true;
                $mercureOptions['actions'] = ['pay_now', 'view_details', 'contact_support'];
                break;
                
            case 'loan_status_update':
                $mercureOptions['persistent'] = true;
                $mercureOptions['actions'] = $options['actions'] ?? [];
                break;
        }
        
        return $mercureOptions;
    }
}
