<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de gestion des notifications push pour PWA
 */
class PushNotificationService
{
    private const VAPID_PUBLIC_KEY = 'BMqSvZWghHKGgqKP3hT1jvQC-xqGnEu8dM6f3McyJA_3UspO8ZoF_Nj0ijZzoyC8aEJkgkj_aXxA9sjLfQ1yLkk';
    private const VAPID_PRIVATE_KEY = 'your_vapid_private_key_here';
    private const VAPID_SUBJECT = 'mailto:admin@loanmaster.com';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Enregistre un abonnement push pour un utilisateur
     */
    public function subscribe(int $userId, array $subscription, ?string $userAgent = null): array
    {
        $this->logger->info('Registering push subscription', [
            'user_id' => $userId,
            'endpoint' => $subscription['endpoint'] ?? 'unknown'
        ]);

        try {
            // Vérifier si l'abonnement existe déjà
            $existingSubscription = $this->findExistingSubscription($userId, $subscription['endpoint']);
            
            if ($existingSubscription) {
                // Mettre à jour l'abonnement existant
                $this->updateSubscription($existingSubscription['id'], $subscription, $userAgent);
                $subscriptionId = $existingSubscription['id'];
            } else {
                // Créer un nouvel abonnement
                $subscriptionId = $this->createSubscription($userId, $subscription, $userAgent);
            }

            return [
                'id' => $subscriptionId,
                'success' => true
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to register push subscription', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Désabonne un utilisateur des notifications push
     */
    public function unsubscribe(int $userId, ?string $endpoint = null): bool
    {
        try {
            if ($endpoint) {
                // Supprimer un abonnement spécifique
                $this->deleteSubscriptionByEndpoint($userId, $endpoint);
            } else {
                // Supprimer tous les abonnements de l'utilisateur
                $this->deleteAllUserSubscriptions($userId);
            }

            $this->logger->info('Push subscription removed', [
                'user_id' => $userId,
                'endpoint' => $endpoint
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to unsubscribe from push notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Envoie une notification push à un utilisateur spécifique
     */
    public function sendToUser(int $userId, array $notification): array
    {
        $subscriptions = $this->getUserSubscriptions($userId);
        
        if (empty($subscriptions)) {
            $this->logger->warning('No push subscriptions found for user', ['user_id' => $userId]);
            return ['sent_count' => 0, 'failed_count' => 0];
        }

        $results = [
            'sent_count' => 0,
            'failed_count' => 0,
            'details' => []
        ];

        foreach ($subscriptions as $subscription) {
            try {
                $success = $this->sendPushNotification($subscription, $notification);
                
                if ($success) {
                    $results['sent_count']++;
                } else {
                    $results['failed_count']++;
                }

                $results['details'][] = [
                    'subscription_id' => $subscription['id'],
                    'success' => $success
                ];

            } catch (\Exception $e) {
                $results['failed_count']++;
                $results['details'][] = [
                    'subscription_id' => $subscription['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                $this->logger->error('Failed to send push notification', [
                    'user_id' => $userId,
                    'subscription_id' => $subscription['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Push notification batch sent', [
            'user_id' => $userId,
            'sent_count' => $results['sent_count'],
            'failed_count' => $results['failed_count']
        ]);

        return $results;
    }

    /**
     * Envoie une notification push à plusieurs utilisateurs
     */
    public function sendToUsers(array $userIds, array $notification): array
    {
        $globalResults = [
            'total_users' => count($userIds),
            'successful_users' => 0,
            'failed_users' => 0,
            'total_sent' => 0,
            'total_failed' => 0
        ];

        foreach ($userIds as $userId) {
            try {
                $userResults = $this->sendToUser($userId, $notification);
                
                if ($userResults['sent_count'] > 0) {
                    $globalResults['successful_users']++;
                } else {
                    $globalResults['failed_users']++;
                }

                $globalResults['total_sent'] += $userResults['sent_count'];
                $globalResults['total_failed'] += $userResults['failed_count'];

            } catch (\Exception $e) {
                $globalResults['failed_users']++;
                
                $this->logger->error('Failed to send notification to user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $globalResults;
    }

    /**
     * Envoie une notification push à tous les utilisateurs abonnés
     */
    public function sendToAll(array $notification): array
    {
        $allSubscriptions = $this->getAllActiveSubscriptions();
        
        $results = [
            'total_subscriptions' => count($allSubscriptions),
            'sent_count' => 0,
            'failed_count' => 0
        ];

        foreach ($allSubscriptions as $subscription) {
            try {
                $success = $this->sendPushNotification($subscription, $notification);
                
                if ($success) {
                    $results['sent_count']++;
                } else {
                    $results['failed_count']++;
                }

            } catch (\Exception $e) {
                $results['failed_count']++;
                
                $this->logger->error('Failed to send push notification', [
                    'subscription_id' => $subscription['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Envoie une notification push à un abonnement spécifique
     */
    private function sendPushNotification(array $subscription, array $notification): bool
    {
        try {
            // Préparer les headers VAPID
            $headers = $this->generateVapidHeaders($subscription['endpoint']);
            
            // Préparer le payload
            $payload = json_encode($notification);
            $encryptedPayload = $this->encryptPayload($payload, $subscription);

            // Envoyer la requête HTTP
            $response = $this->httpClient->request('POST', $subscription['endpoint'], [
                'headers' => array_merge($headers, [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => strlen($encryptedPayload)
                ]),
                'body' => $encryptedPayload
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 201) {
                return true;
            } elseif ($statusCode === 410 || $statusCode === 404) {
                // Abonnement expiré ou invalide
                $this->markSubscriptionAsInvalid($subscription['id']);
                return false;
            } else {
                $this->logger->warning('Push notification failed with status', [
                    'status_code' => $statusCode,
                    'subscription_id' => $subscription['id']
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Push notification send error', [
                'subscription_id' => $subscription['id'],
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Génère les headers VAPID pour l'authentification
     */
    private function generateVapidHeaders(string $endpoint): array
    {
        // Extraire l'audience de l'endpoint
        $parsedUrl = parse_url($endpoint);
        $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        // Générer le JWT VAPID
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256'
        ];

        $payload = [
            'aud' => $audience,
            'exp' => time() + 12 * 60 * 60, // 12 heures
            'sub' => self::VAPID_SUBJECT
        ];

        // Dans une vraie implémentation, utilisez une bibliothèque JWT comme firebase/jwt
        $jwt = $this->generateJWT($header, $payload, self::VAPID_PRIVATE_KEY);

        return [
            'Authorization' => 'vapid t=' . $jwt . ', k=' . self::VAPID_PUBLIC_KEY,
            'Crypto-Key' => 'p256ecdsa=' . self::VAPID_PUBLIC_KEY
        ];
    }

    /**
     * Chiffre le payload de la notification
     */
    private function encryptPayload(string $payload, array $subscription): string
    {
        // Implémentation simplifiée du chiffrement Web Push
        // Dans une vraie application, utilisez une bibliothèque comme web-push/web-push-php
        
        $keys = $subscription['keys'];
        $p256dh = base64_decode($keys['p256dh']);
        $auth = base64_decode($keys['auth']);

        // Pour la démonstration, on retourne le payload non chiffré
        // En production, implémentez le chiffrement AES-GCM approprié
        return $payload;
    }

    /**
     * Génère un JWT simple (à remplacer par une vraie bibliothèque)
     */
    private function generateJWT(array $header, array $payload, string $privateKey): string
    {
        // Implémentation simplifiée - utilisez firebase/jwt en production
        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        
        $signature = 'mock_signature'; // Remplacez par une vraie signature ECDSA
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Récupère le nombre d'abonnements d'un utilisateur
     */
    public function getUserSubscriptionCount(int $userId): int
    {
        // Simulation - remplacez par une vraie requête en base
        return 1;
    }

    /**
     * Vérifie si les notifications sont activées pour un utilisateur
     */
    public function areNotificationsEnabled(int $userId): bool
    {
        // Simulation - remplacez par une vraie vérification
        return true;
    }

    /**
     * Nettoie les abonnements expirés
     */
    public function cleanupExpiredSubscriptions(): int
    {
        $this->logger->info('Starting cleanup of expired push subscriptions');
        
        // Simulation du nettoyage
        $deletedCount = 0;
        
        // Dans une vraie implémentation :
        // 1. Récupérer tous les abonnements
        // 2. Tester chaque endpoint
        // 3. Supprimer ceux qui retournent 410 ou 404
        
        $this->logger->info('Expired push subscriptions cleanup completed', [
            'deleted_count' => $deletedCount
        ]);
        
        return $deletedCount;
    }

    // Méthodes privées de gestion des données

    private function findExistingSubscription(int $userId, string $endpoint): ?array
    {
        // Simulation - remplacez par une vraie requête
        return null;
    }

    private function createSubscription(int $userId, array $subscription, ?string $userAgent): int
    {
        // Simulation de création d'abonnement
        $subscriptionId = time(); // ID temporaire
        
        $this->logger->debug('Push subscription created', [
            'subscription_id' => $subscriptionId,
            'user_id' => $userId
        ]);
        
        return $subscriptionId;
    }

    private function updateSubscription(int $subscriptionId, array $subscription, ?string $userAgent): void
    {
        // Simulation de mise à jour
        $this->logger->debug('Push subscription updated', [
            'subscription_id' => $subscriptionId
        ]);
    }

    private function deleteSubscriptionByEndpoint(int $userId, string $endpoint): void
    {
        // Simulation de suppression
        $this->logger->debug('Push subscription deleted by endpoint', [
            'user_id' => $userId,
            'endpoint' => $endpoint
        ]);
    }

    private function deleteAllUserSubscriptions(int $userId): void
    {
        // Simulation de suppression
        $this->logger->debug('All push subscriptions deleted for user', [
            'user_id' => $userId
        ]);
    }

    private function getUserSubscriptions(int $userId): array
    {
        // Simulation - retourner des abonnements factices
        return [
            [
                'id' => 1,
                'user_id' => $userId,
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/mock-endpoint',
                'keys' => [
                    'p256dh' => base64_encode('mock-p256dh-key'),
                    'auth' => base64_encode('mock-auth-key')
                ]
            ]
        ];
    }

    private function getAllActiveSubscriptions(): array
    {
        // Simulation
        return [];
    }

    private function markSubscriptionAsInvalid(int $subscriptionId): void
    {
        $this->logger->info('Marking push subscription as invalid', [
            'subscription_id' => $subscriptionId
        ]);
    }
}
