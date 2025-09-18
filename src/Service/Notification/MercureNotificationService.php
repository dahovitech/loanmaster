<?php

namespace App\Service\Notification;

use App\Domain\Event\DomainEventInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;

/**
 * Service principal de notifications temps réel
 * Intégration Mercure pour push notifications
 */
class MercureNotificationService
{
    private HubInterface $hub;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private array $topicConfiguration;
    private array $userSubscriptions = [];

    public function __construct(
        HubInterface $hub,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        array $topicConfiguration = []
    ) {
        $this->hub = $hub;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->topicConfiguration = $topicConfiguration;
    }

    /**
     * Publie une notification via Mercure
     */
    public function publishNotification(
        string $topic,
        array $data,
        array $targets = [],
        array $options = []
    ): bool {
        try {
            // Préparation des données
            $payload = [
                'type' => $topic,
                'data' => $data,
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
                'version' => '1.0',
                'source' => 'loanmaster-api'
            ];
            
            // Ajout des métadonnées
            if (!empty($options)) {
                $payload['metadata'] = $options;
            }
            
            // Sérialisation
            $jsonData = $this->serializer->serialize($payload, 'json');
            
            // Création de l'update Mercure
            $update = new Update(
                $this->buildTopicUrl($topic),
                $jsonData,
                $targets, // IRI des utilisateurs ciblés
                null,     // ID de l'update
                $options['type'] ?? 'notification',
                $options['retry'] ?? null
            );
            
            // Publication via Mercure
            $id = $this->hub->publish($update);
            
            $this->logger->info('Notification published via Mercure', [
                'topic' => $topic,
                'update_id' => $id,
                'targets_count' => count($targets),
                'data_size' => strlen($jsonData)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish Mercure notification', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Publie une notification de changement de statut de prêt
     */
    public function publishLoanStatusUpdate(
        string $loanId,
        string $customerId,
        string $previousStatus,
        string $newStatus,
        ?string $reason = null,
        ?array $additionalData = null
    ): bool {
        $data = [
            'loanId' => $loanId,
            'customerId' => $customerId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'reason' => $reason,
            'additionalData' => $additionalData,
            'changedAt' => new DateTimeImmutable()
        ];
        
        // Ciblage spécifique du client
        $targets = [
            "/users/{$customerId}",
            "/loans/{$loanId}/subscribers"
        ];
        
        return $this->publishNotification(
            'loan_status_updates',
            $data,
            $targets,
            [
                'priority' => 'normal',
                'category' => 'loan_management',
                'actions' => $this->getLoanStatusActions($newStatus)
            ]
        );
    }

    /**
     * Publie une alerte de risque
     */
    public function publishRiskAlert(
        string $loanId,
        string $customerId,
        string $riskLevel,
        int $riskScore,
        array $factors,
        string $recommendation
    ): bool {
        $data = [
            'loanId' => $loanId,
            'customerId' => $customerId,
            'riskLevel' => $riskLevel,
            'riskScore' => $riskScore,
            'factors' => $factors,
            'recommendation' => $recommendation,
            'severity' => $this->calculateSeverity($riskLevel, $riskScore),
            'alertedAt' => new DateTimeImmutable()
        ];
        
        // Ciblage des analystes de risque et managers
        $targets = [
            '/roles/ROLE_RISK_ANALYST',
            '/roles/ROLE_MANAGER',
            "/users/{$customerId}"
        ];
        
        return $this->publishNotification(
            'risk_alerts',
            $data,
            $targets,
            [
                'priority' => $riskLevel === 'critical' ? 'urgent' : 'high',
                'category' => 'risk_management',
                'requiresAction' => $riskLevel === 'critical'
            ]
        );
    }

    /**
     * Publie une notification de paiement
     */
    public function publishPaymentNotification(
        string $loanId,
        string $customerId,
        string $paymentType,
        float $amount,
        string $status,
        ?DateTimeImmutable $dueDate = null
    ): bool {
        $data = [
            'loanId' => $loanId,
            'customerId' => $customerId,
            'paymentType' => $paymentType,
            'amount' => $amount,
            'status' => $status,
            'dueDate' => $dueDate?->format(DATE_ATOM),
            'processedAt' => new DateTimeImmutable()
        ];
        
        $targets = [
            "/users/{$customerId}",
            '/roles/ROLE_FINANCE'
        ];
        
        return $this->publishNotification(
            'payment_notifications',
            $data,
            $targets,
            [
                'priority' => $status === 'failed' ? 'high' : 'normal',
                'category' => 'payment_management'
            ]
        );
    }

    /**
     * Publie une notification d'audit pour sécurité
     */
    public function publishAuditAlert(
        string $entityType,
        string $entityId,
        string $eventType,
        string $severity,
        ?string $userId = null,
        ?array $suspiciousActivity = null
    ): bool {
        $data = [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'eventType' => $eventType,
            'severity' => $severity,
            'userId' => $userId,
            'suspiciousActivity' => $suspiciousActivity,
            'detectedAt' => new DateTimeImmutable()
        ];
        
        // Notification aux auditeurs et administrateurs seulement
        $targets = [
            '/roles/ROLE_AUDITOR',
            '/roles/ROLE_ADMIN',
            '/security/alerts'
        ];
        
        return $this->publishNotification(
            'audit_alerts',
            $data,
            $targets,
            [
                'priority' => $severity === 'critical' ? 'urgent' : 'high',
                'category' => 'security',
                'confidential' => true
            ]
        );
    }

    /**
     * Souscrit un utilisateur à un topic
     */
    public function subscribeUser(string $userId, string $topic, array $options = []): bool
    {
        try {
            $subscriptionKey = "{$userId}:{$topic}";
            $this->userSubscriptions[$subscriptionKey] = [
                'userId' => $userId,
                'topic' => $topic,
                'subscribedAt' => new DateTimeImmutable(),
                'options' => $options
            ];
            
            $this->logger->info('User subscribed to topic', [
                'userId' => $userId,
                'topic' => $topic
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to subscribe user to topic', [
                'userId' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Désouscrit un utilisateur d'un topic
     */
    public function unsubscribeUser(string $userId, string $topic): bool
    {
        $subscriptionKey = "{$userId}:{$topic}";
        
        if (isset($this->userSubscriptions[$subscriptionKey])) {
            unset($this->userSubscriptions[$subscriptionKey]);
            
            $this->logger->info('User unsubscribed from topic', [
                'userId' => $userId,
                'topic' => $topic
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Récupère les souscriptions d'un utilisateur
     */
    public function getUserSubscriptions(string $userId): array
    {
        return array_filter(
            $this->userSubscriptions,
            fn($subscription) => $subscription['userId'] === $userId
        );
    }

    /**
     * Construit l'URL du topic Mercure
     */
    private function buildTopicUrl(string $topic): string
    {
        return "https://loanmaster.local/notifications/{$topic}";
    }

    /**
     * Détermine les actions disponibles selon le statut
     */
    private function getLoanStatusActions(string $status): array
    {
        return match ($status) {
            'approved' => ['view_terms', 'accept_loan', 'decline_loan'],
            'requires_documents' => ['upload_documents', 'view_requirements'],
            'funded' => ['view_payment_schedule', 'setup_autopay'],
            'active' => ['make_payment', 'view_balance', 'payment_history'],
            'completed' => ['download_certificate', 'rate_experience'],
            default => ['view_details']
        };
    }

    /**
     * Calcule la sévérité d'une alerte de risque
     */
    private function calculateSeverity(string $riskLevel, int $riskScore): string
    {
        return match ($riskLevel) {
            'critical' => 'urgent',
            'high' => $riskScore < 400 ? 'urgent' : 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'medium'
        };
    }

    /**
     * Récupère les statistiques des notifications
     */
    public function getNotificationStats(): array
    {
        return [
            'active_subscriptions' => count($this->userSubscriptions),
            'topics' => array_unique(array_column($this->userSubscriptions, 'topic')),
            'users' => array_unique(array_column($this->userSubscriptions, 'userId'))
        ];
    }
}
