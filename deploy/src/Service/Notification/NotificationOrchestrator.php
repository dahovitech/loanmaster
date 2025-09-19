<?php

namespace App\Service\Notification;

use App\Service\Notification\Channel\ChannelInterface;
use App\Service\Notification\Channel\EmailNotificationChannel;
use App\Service\Notification\Channel\SmsNotificationChannel;
use App\Service\Notification\Channel\PushNotificationChannel;
use App\Service\Notification\Channel\MercureNotificationChannel;
use App\Entity\Notification;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;

/**
 * Service orchestrateur de notifications multi-canaux
 * Gestion centralisée de tous les types de notifications
 */
class NotificationOrchestrator
{
    private array $channels = [];
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private array $configuration;

    public function __construct(
        EmailNotificationChannel $emailChannel,
        SmsNotificationChannel $smsChannel,
        PushNotificationChannel $pushChannel,
        MercureNotificationChannel $mercureChannel,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        array $configuration = []
    ) {
        $this->channels = [
            'email' => $emailChannel,
            'sms' => $smsChannel,
            'push' => $pushChannel,
            'mercure' => $mercureChannel
        ];
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->configuration = $configuration;
    }

    /**
     * Envoie une notification via les canaux configurés
     */
    public function sendNotification(
        string $type,
        array $recipients,
        array $data,
        array $options = []
    ): NotificationResult {
        $startTime = microtime(true);
        $notificationId = uniqid('notif_', true);
        
        $this->logger->info('Starting notification delivery', [
            'notification_id' => $notificationId,
            'type' => $type,
            'recipients_count' => count($recipients),
            'channels' => $options['channels'] ?? 'auto'
        ]);
        
        // Détermination des canaux à utiliser
        $channels = $this->determineChannels($type, $options);
        
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($channels as $channelName) {
            if (!isset($this->channels[$channelName])) {
                $this->logger->warning('Unknown notification channel', [
                    'channel' => $channelName,
                    'notification_id' => $notificationId
                ]);
                continue;
            }
            
            $channel = $this->channels[$channelName];
            
            try {
                $channelStartTime = microtime(true);
                
                $channelResult = $channel->send($type, $recipients, $data, $options);
                
                $channelExecutionTime = microtime(true) - $channelStartTime;
                
                $results[$channelName] = [
                    'success' => $channelResult->isSuccess(),
                    'delivered_count' => $channelResult->getDeliveredCount(),
                    'failed_count' => $channelResult->getFailedCount(),
                    'execution_time' => $channelExecutionTime,
                    'errors' => $channelResult->getErrors(),
                    'metadata' => $channelResult->getMetadata()
                ];
                
                if ($channelResult->isSuccess()) {
                    $successCount += $channelResult->getDeliveredCount();
                } else {
                    $failureCount += $channelResult->getFailedCount();
                }
                
                $this->logger->info('Channel notification completed', [
                    'channel' => $channelName,
                    'notification_id' => $notificationId,
                    'success' => $channelResult->isSuccess(),
                    'delivered' => $channelResult->getDeliveredCount(),
                    'execution_time' => $channelExecutionTime
                ]);
                
            } catch (\Exception $e) {
                $results[$channelName] = [
                    'success' => false,
                    'delivered_count' => 0,
                    'failed_count' => count($recipients),
                    'execution_time' => microtime(true) - $channelStartTime,
                    'errors' => [$e->getMessage()],
                    'metadata' => []
                ];
                
                $failureCount += count($recipients);
                
                $this->logger->error('Channel notification failed', [
                    'channel' => $channelName,
                    'notification_id' => $notificationId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $totalExecutionTime = microtime(true) - $startTime;
        
        // Enregistrement de la notification en base
        $notification = $this->persistNotification(
            $notificationId,
            $type,
            $recipients,
            $data,
            $results,
            $options
        );
        
        $result = new NotificationResult(
            $notificationId,
            $successCount > 0,
            $successCount,
            $failureCount,
            $results,
            $totalExecutionTime
        );
        
        $this->logger->info('Notification delivery completed', [
            'notification_id' => $notificationId,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total_execution_time' => $totalExecutionTime,
            'channels_used' => array_keys($results)
        ]);
        
        return $result;
    }

    /**
     * Envoie une notification de statut de prêt
     */
    public function sendLoanStatusNotification(
        string $loanId,
        string $customerId,
        string $customerEmail,
        ?string $customerPhone,
        string $previousStatus,
        string $newStatus,
        ?string $reason = null
    ): NotificationResult {
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customerEmail,
                'phone' => $customerPhone
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'customer_id' => $customerId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'status_display' => $this->getStatusDisplayName($newStatus),
            'next_steps' => $this->getNextSteps($newStatus),
            'support_contact' => $this->getSupportContact()
        ];
        
        $options = [
            'template' => 'loan_status_update',
            'priority' => $this->getStatusPriority($newStatus),
            'channels' => $this->getChannelsForStatus($newStatus)
        ];
        
        return $this->sendNotification('loan_status_update', $recipients, $data, $options);
    }

    /**
     * Envoie une alerte de risque
     */
    public function sendRiskAlert(
        string $loanId,
        string $customerId,
        string $riskLevel,
        int $riskScore,
        array $factors
    ): NotificationResult {
        // Destinataires selon le niveau de risque
        $recipients = $this->getRiskAlertRecipients($riskLevel);
        
        $data = [
            'loan_id' => $loanId,
            'customer_id' => $customerId,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'factors' => $factors,
            'severity' => $this->calculateRiskSeverity($riskLevel, $riskScore),
            'recommended_actions' => $this->getRecommendedActions($riskLevel)
        ];
        
        $options = [
            'template' => 'risk_alert',
            'priority' => $riskLevel === 'critical' ? 'urgent' : 'high',
            'channels' => ['mercure', 'email'] + ($riskLevel === 'critical' ? ['sms'] : [])
        ];
        
        return $this->sendNotification('risk_alert', $recipients, $data, $options);
    }

    /**
     * Envoie un rappel de paiement
     */
    public function sendPaymentReminder(
        string $loanId,
        string $customerId,
        string $customerEmail,
        ?string $customerPhone,
        float $amount,
        DateTimeImmutable $dueDate,
        int $daysOverdue = 0
    ): NotificationResult {
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customerEmail,
                'phone' => $customerPhone
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'amount' => $amount,
            'due_date' => $dueDate->format('d/m/Y'),
            'days_overdue' => $daysOverdue,
            'is_overdue' => $daysOverdue > 0,
            'late_fees' => $daysOverdue > 0 ? $amount * 0.05 : 0, // 5% frais de retard
            'payment_link' => $this->generatePaymentLink($loanId),
            'contact_support' => $this->getSupportContact()
        ];
        
        $urgency = $daysOverdue > 30 ? 'urgent' : ($daysOverdue > 0 ? 'high' : 'normal');
        
        $options = [
            'template' => 'payment_reminder',
            'priority' => $urgency,
            'channels' => $daysOverdue > 15 ? ['email', 'sms', 'mercure'] : ['email', 'mercure']
        ];
        
        return $this->sendNotification('payment_reminder', $recipients, $data, $options);
    }

    /**
     * Détermine les canaux à utiliser
     */
    private function determineChannels(string $type, array $options): array
    {
        if (!empty($options['channels'])) {
            return (array) $options['channels'];
        }
        
        // Configuration par défaut selon le type
        return match ($type) {
            'loan_status_update' => ['mercure', 'email', 'push'],
            'risk_alert' => ['mercure', 'email'],
            'payment_reminder' => ['email', 'mercure'],
            'audit_alert' => ['mercure', 'email'],
            'system_maintenance' => ['mercure', 'push'],
            default => ['mercure']
        };
    }

    /**
     * Persiste la notification en base de données
     */
    private function persistNotification(
        string $notificationId,
        string $type,
        array $recipients,
        array $data,
        array $results,
        array $options
    ): Notification {
        $notification = new Notification();
        $notification->setNotificationId($notificationId);
        $notification->setType($type);
        $notification->setRecipients($recipients);
        $notification->setData($data);
        $notification->setChannelResults($results);
        $notification->setOptions($options);
        $notification->setCreatedAt(new DateTimeImmutable());
        
        // Calcul des statistiques
        $deliveredCount = 0;
        $failedCount = 0;
        $errors = [];
        $executionTime = 0.0;
        
        foreach ($results as $channelName => $result) {
            if (is_array($result)) {
                $deliveredCount += $result['delivered_count'] ?? 0;
                $failedCount += $result['failed_count'] ?? 0;
                $executionTime += $result['execution_time'] ?? 0.0;
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
            }
        }
        
        $notification->setDeliveredCount($deliveredCount);
        $notification->setFailedCount($failedCount);
        $notification->setExecutionTime($executionTime);
        $notification->setErrors($errors);
        
        // Détermination du statut
        if ($deliveredCount > 0) {
            $notification->markAsSent();
        } else {
            $notification->markAsFailed($errors);
        }
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        return $notification;
    }

    /**
     * Récupère le nom d'affichage du statut
     */
    private function getStatusDisplayName(string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'under_review' => 'En cours d\'examen',
            'approved' => 'Approuvé',
            'rejected' => 'Rejeté',
            'funded' => 'Financé',
            'active' => 'Actif',
            'completed' => 'Complété',
            'defaulted' => 'En défaut',
            default => ucfirst($status)
        };
    }

    /**
     * Récupère les prochaines étapes selon le statut
     */
    private function getNextSteps(string $status): array
    {
        return match ($status) {
            'approved' => ['Consulter les termes du contrat', 'Signer électroniquement', 'Attendre le financement'],
            'requires_documents' => ['Uploader les documents manquants', 'Vérifier les exigences'],
            'funded' => ['Consulter le calendrier de paiement', 'Configurer les prélèvements automatiques'],
            'active' => ['Effectuer les paiements mensuels', 'Consulter le solde', 'Contacter le support si nécessaire'],
            default => ['Contacter notre équipe pour plus d\'informations']
        };
    }

    /**
     * Récupère la priorité selon le statut
     */
    private function getStatusPriority(string $status): string
    {
        return match ($status) {
            'approved', 'funded' => 'high',
            'rejected', 'defaulted' => 'urgent',
            default => 'normal'
        };
    }

    /**
     * Récupère les canaux selon le statut
     */
    private function getChannelsForStatus(string $status): array
    {
        return match ($status) {
            'approved', 'funded' => ['mercure', 'email', 'push', 'sms'],
            'rejected' => ['mercure', 'email'],
            'defaulted' => ['mercure', 'email', 'sms'],
            default => ['mercure', 'email']
        };
    }

    /**
     * Autres méthodes utilitaires...
     */
    private function getRiskAlertRecipients(string $riskLevel): array { return []; }
    private function calculateRiskSeverity(string $riskLevel, int $riskScore): string { return 'medium'; }
    private function getRecommendedActions(string $riskLevel): array { return []; }
    private function generatePaymentLink(string $loanId): string { return "https://app.loanmaster.local/payments/{$loanId}"; }
    private function getSupportContact(): array { return ['email' => 'support@loanmaster.local', 'phone' => '+33 1 23 45 67 89']; }
}
