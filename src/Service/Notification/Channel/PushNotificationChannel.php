<?php

namespace App\Service\Notification\Channel;

use App\Service\Notification\NotificationResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Canal de notification Push (Firebase/Web Push)
 */
class PushNotificationChannel implements ChannelInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private array $configuration;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        array $configuration = []
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->configuration = array_merge([
            'provider' => 'firebase',
            'server_key' => $_ENV['FIREBASE_SERVER_KEY'] ?? '',
            'vapid_public_key' => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
            'vapid_private_key' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
            'firebase_project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
            'enabled' => $_ENV['PUSH_NOTIFICATIONS_ENABLED'] ?? false,
            'icon' => '/assets/icons/notification-icon.png',
            'badge' => '/assets/icons/badge-icon.png'
        ], $configuration);
    }

    public function send(string $type, array $recipients, array $data, array $options = []): NotificationResult
    {
        $startTime = microtime(true);
        $deliveredCount = 0;
        $failedCount = 0;
        $errors = [];

        if (!$this->isAvailable()) {
            return new NotificationResult(
                uniqid('push_', true),
                false,
                0,
                count($recipients),
                ['channel' => 'push', 'error' => 'Push notifications not available'],
                microtime(true) - $startTime,
                ['Push notifications service not configured or disabled']
            );
        }

        try {
            // Préparation du message push
            $pushMessage = $this->formatPushMessage($type, $data, $options);
            
            foreach ($recipients as $recipient) {
                $pushTokens = $this->extractPushTokens($recipient);
                
                if (empty($pushTokens)) {
                    $failedCount++;
                    $errors[] = "No push tokens for recipient: " . json_encode($recipient);
                    continue;
                }
                
                foreach ($pushTokens as $token) {
                    try {
                        $success = $this->sendPushNotification($token, $pushMessage, $options);
                        
                        if ($success) {
                            $deliveredCount++;
                            $this->logger->info('Push notification sent', [
                                'token' => $this->maskToken($token),
                                'type' => $type
                            ]);
                        } else {
                            $failedCount++;
                            $errors[] = "Failed to send push to token: {$this->maskToken($token)}";
                        }
                        
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errors[] = "Push error for token {$this->maskToken($token)}: {$e->getMessage()}";
                        
                        $this->logger->error('Push notification failed', [
                            'token' => $this->maskToken($token),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
        } catch (\Exception $e) {
            $failedCount = count($recipients);
            $errors[] = $e->getMessage();
            
            $this->logger->error('Push channel general error', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $executionTime = microtime(true) - $startTime;

        return new NotificationResult(
            uniqid('push_', true),
            $deliveredCount > 0,
            $deliveredCount,
            $failedCount,
            [
                'channel' => 'push',
                'execution_time' => $executionTime
            ],
            $executionTime,
            $errors
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->configuration['enabled']) && 
               !empty($this->configuration['server_key']) && 
               !empty($this->configuration['firebase_project_id']);
    }

    public function getName(): string
    {
        return 'push';
    }

    public function getConfiguration(): array
    {
        $config = $this->configuration;
        // Masquer les clés sensibles
        if (isset($config['server_key'])) {
            $config['server_key'] = '***masked***';
        }
        if (isset($config['vapid_private_key'])) {
            $config['vapid_private_key'] = '***masked***';
        }
        return $config;
    }

    public function validateData(array $data): bool
    {
        // Vérifier qu'on peut créer un message push valide
        try {
            $pushMessage = $this->formatPushMessage('test', $data, []);
            return !empty($pushMessage['title']) && !empty($pushMessage['body']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function formatData(array $data, array $options = []): array
    {
        return $this->formatPushMessage('generic', $data, $options);
    }

    /**
     * Formate le message push selon le type de notification
     */
    private function formatPushMessage(string $type, array $data, array $options): array
    {
        $baseMessage = [
            'icon' => $this->configuration['icon'],
            'badge' => $this->configuration['badge'],
            'timestamp' => time() * 1000, // JavaScript timestamp
            'requireInteraction' => false,
            'silent' => false
        ];

        return array_merge($baseMessage, match ($type) {
            'loan_status_update' => $this->formatLoanStatusPush($data, $options),
            'risk_alert' => $this->formatRiskAlertPush($data, $options),
            'payment_reminder' => $this->formatPaymentReminderPush($data, $options),
            'audit_alert' => $this->formatAuditAlertPush($data, $options),
            'system_maintenance' => $this->formatSystemMaintenancePush($data, $options),
            default => $this->formatGenericPush($data, $options)
        });
    }

    private function formatLoanStatusPush(array $data, array $options): array
    {
        $status = $data['status_display'] ?? $data['new_status'] ?? 'mis à jour';
        
        return [
            'title' => 'Mise à jour de votre prêt',
            'body' => "Votre demande de prêt #{$data['loan_id']} est maintenant '{$status}'",
            'icon' => '/assets/icons/loan-update.png',
            'data' => [
                'type' => 'loan_status_update',
                'loan_id' => $data['loan_id'],
                'new_status' => $data['new_status'] ?? null,
                'url' => "/loans/{$data['loan_id']}"
            ],
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'Voir les détails',
                    'icon' => '/assets/icons/view.png'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Ignorer',
                    'icon' => '/assets/icons/close.png'
                ]
            ],
            'requireInteraction' => true
        ];
    }

    private function formatRiskAlertPush(array $data, array $options): array
    {
        $riskLevel = $data['risk_level'] ?? 'unknown';
        
        return [
            'title' => 'Alerte de Risque',
            'body' => "Niveau {$riskLevel} détecté sur le prêt #{$data['loan_id']}",
            'icon' => '/assets/icons/risk-alert.png',
            'tag' => 'risk-alert',
            'data' => [
                'type' => 'risk_alert',
                'loan_id' => $data['loan_id'],
                'risk_level' => $riskLevel,
                'url' => "/admin/risk-alerts/{$data['loan_id']}"
            ],
            'requireInteraction' => true,
            'silent' => false,
            'vibrate' => [200, 100, 200] // Pattern de vibration
        ];
    }

    private function formatPaymentReminderPush(array $data, array $options): array
    {
        $amount = number_format($data['amount'], 2, ',', ' ');
        $isOverdue = $data['is_overdue'] ?? false;
        
        return [
            'title' => $isOverdue ? 'Paiement en retard' : 'Rappel de paiement',
            'body' => $isOverdue 
                ? "Paiement en retard de {$amount}€ pour votre prêt #{$data['loan_id']}"
                : "Paiement de {$amount}€ dû le {$data['due_date']}",
            'icon' => $isOverdue ? '/assets/icons/payment-overdue.png' : '/assets/icons/payment-reminder.png',
            'tag' => 'payment-reminder',
            'data' => [
                'type' => 'payment_reminder',
                'loan_id' => $data['loan_id'],
                'amount' => $data['amount'],
                'is_overdue' => $isOverdue,
                'url' => "/payments/{$data['loan_id']}"
            ],
            'actions' => [
                [
                    'action' => 'pay',
                    'title' => 'Payer maintenant',
                    'icon' => '/assets/icons/pay.png'
                ],
                [
                    'action' => 'remind_later',
                    'title' => 'Rappeler plus tard',
                    'icon' => '/assets/icons/clock.png'
                ]
            ],
            'requireInteraction' => $isOverdue
        ];
    }

    private function formatAuditAlertPush(array $data, array $options): array
    {
        return [
            'title' => 'Alerte de Sécurité',
            'body' => "Activité {$data['severity']} détectée: {$data['event_type']}",
            'icon' => '/assets/icons/security-alert.png',
            'tag' => 'audit-alert',
            'data' => [
                'type' => 'audit_alert',
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'url' => '/admin/audit-logs'
            ],
            'requireInteraction' => true,
            'silent' => false
        ];
    }

    private function formatSystemMaintenancePush(array $data, array $options): array
    {
        return [
            'title' => 'Maintenance Système',
            'body' => $data['message'] ?? 'Maintenance programmée du système',
            'icon' => '/assets/icons/maintenance.png',
            'tag' => 'system-maintenance',
            'data' => [
                'type' => 'system_maintenance',
                'url' => '/system-status'
            ],
            'requireInteraction' => false
        ];
    }

    private function formatGenericPush(array $data, array $options): array
    {
        return [
            'title' => $data['title'] ?? 'LoanMaster',
            'body' => $data['message'] ?? $data['body'] ?? 'Nouvelle notification',
            'data' => [
                'type' => 'generic',
                'url' => $data['url'] ?? '/dashboard'
            ]
        ];
    }

    /**
     * Extrait les tokens push du recipient
     */
    private function extractPushTokens($recipient): array
    {
        if (is_string($recipient)) {
            // Format simple: token direct
            return [$recipient];
        }
        
        if (is_array($recipient)) {
            $tokens = [];
            
            // Format structuré
            if (isset($recipient['push_token'])) {
                $tokens[] = $recipient['push_token'];
            }
            
            if (isset($recipient['push_tokens']) && is_array($recipient['push_tokens'])) {
                $tokens = array_merge($tokens, $recipient['push_tokens']);
            }
            
            if (isset($recipient['devices']) && is_array($recipient['devices'])) {
                foreach ($recipient['devices'] as $device) {
                    if (isset($device['push_token'])) {
                        $tokens[] = $device['push_token'];
                    }
                }
            }
            
            return array_filter($tokens);
        }
        
        return [];
    }

    /**
     * Masque le token pour les logs
     */
    private function maskToken(string $token): string
    {
        if (strlen($token) < 10) {
            return '***';
        }
        
        return substr($token, 0, 8) . '***' . substr($token, -8);
    }

    /**
     * Envoie la notification push via Firebase
     */
    private function sendPushNotification(string $token, array $message, array $options): bool
    {
        try {
            $payload = [
                'to' => $token,
                'notification' => [
                    'title' => $message['title'],
                    'body' => $message['body'],
                    'icon' => $message['icon'] ?? $this->configuration['icon'],
                    'badge' => $message['badge'] ?? $this->configuration['badge'],
                    'tag' => $message['tag'] ?? null,
                    'requireInteraction' => $message['requireInteraction'] ?? false,
                    'silent' => $message['silent'] ?? false
                ],
                'data' => $message['data'] ?? [],
                'webpush' => [
                    'headers' => [
                        'TTL' => '86400' // 24 heures
                    ]
                ]
            ];
            
            // Ajout des actions si présentes
            if (!empty($message['actions'])) {
                $payload['webpush']['notification'] = [
                    'actions' => $message['actions']
                ];
            }
            
            // Ajout du pattern de vibration si présent
            if (!empty($message['vibrate'])) {
                $payload['webpush']['notification']['vibrate'] = $message['vibrate'];
            }
            
            $response = $this->httpClient->request('POST', 'https://fcm.googleapis.com/fcm/send', [
                'headers' => [
                    'Authorization' => 'key=' . $this->configuration['server_key'],
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $responseData = $response->toArray();
                return ($responseData['success'] ?? 0) > 0;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('Firebase push notification error', [
                'error' => $e->getMessage(),
                'token' => $this->maskToken($token)
            ]);
            
            return false;
        }
    }
}
