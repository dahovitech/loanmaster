<?php

namespace App\Service\Notification;

use App\Entity\User;
use App\Entity\NotificationSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;

/**
 * Service de gestion des abonnements aux notifications
 */
class SubscriptionManager
{
    private MercureNotificationService $mercureService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        MercureNotificationService $mercureService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->mercureService = $mercureService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Abonne un utilisateur à un topic de notification
     */
    public function subscribeUserToTopic(
        string $userId,
        string $topic,
        array $options = []
    ): bool {
        try {
            // Vérification de l'existence de l'utilisateur
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                $this->logger->warning('Cannot subscribe non-existent user to topic', [
                    'user_id' => $userId,
                    'topic' => $topic
                ]);
                return false;
            }

            // Vérification si l'abonnement existe déjà
            $existingSubscription = $this->entityManager
                ->getRepository(NotificationSubscription::class)
                ->findOneBy(['user' => $user, 'topic' => $topic]);

            if ($existingSubscription) {
                // Mise à jour des options si nécessaire
                $existingSubscription->setOptions($options);
                $existingSubscription->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->flush();

                $this->logger->info('Updated existing subscription', [
                    'user_id' => $userId,
                    'topic' => $topic
                ]);

                return true;
            }

            // Création de l'abonnement en base
            $subscription = new NotificationSubscription();
            $subscription->setUser($user);
            $subscription->setTopic($topic);
            $subscription->setOptions($options);
            $subscription->setCreatedAt(new DateTimeImmutable());
            $subscription->setUpdatedAt(new DateTimeImmutable());
            $subscription->setIsActive(true);

            $this->entityManager->persist($subscription);
            $this->entityManager->flush();

            // Abonnement via Mercure
            $mercureSuccess = $this->mercureService->subscribeUser($userId, $topic, $options);

            if ($mercureSuccess) {
                $this->logger->info('User subscribed to topic successfully', [
                    'user_id' => $userId,
                    'topic' => $topic
                ]);
                return true;
            } else {
                // Rollback si l'abonnement Mercure échoue
                $this->entityManager->remove($subscription);
                $this->entityManager->flush();

                $this->logger->error('Failed to subscribe user to Mercure topic', [
                    'user_id' => $userId,
                    'topic' => $topic
                ]);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error('Error subscribing user to topic', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Désabonne un utilisateur d'un topic
     */
    public function unsubscribeUserFromTopic(string $userId, string $topic): bool
    {
        try {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                return false;
            }

            // Suppression de l'abonnement en base
            $subscription = $this->entityManager
                ->getRepository(NotificationSubscription::class)
                ->findOneBy(['user' => $user, 'topic' => $topic]);

            if ($subscription) {
                $this->entityManager->remove($subscription);
                $this->entityManager->flush();
            }

            // Désabonnement via Mercure
            $mercureSuccess = $this->mercureService->unsubscribeUser($userId, $topic);

            $this->logger->info('User unsubscribed from topic', [
                'user_id' => $userId,
                'topic' => $topic,
                'mercure_success' => $mercureSuccess
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error unsubscribing user from topic', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupère tous les abonnements d'un utilisateur
     */
    public function getUserSubscriptions(string $userId): array
    {
        try {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                return [];
            }

            $subscriptions = $this->entityManager
                ->getRepository(NotificationSubscription::class)
                ->findBy(['user' => $user, 'isActive' => true]);

            $result = [];
            foreach ($subscriptions as $subscription) {
                $result[] = [
                    'topic' => $subscription->getTopic(),
                    'options' => $subscription->getOptions(),
                    'created_at' => $subscription->getCreatedAt()->format(DATE_ATOM),
                    'updated_at' => $subscription->getUpdatedAt()->format(DATE_ATOM)
                ];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving user subscriptions', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Abonne automatiquement un utilisateur selon son rôle
     */
    public function subscribeUserBasedOnRole(User $user): bool
    {
        $userId = $user->getId();
        $roles = $user->getRoles();
        
        $subscriptions = [
            'ROLE_CUSTOMER' => ['loan_status_updates', 'payment_notifications'],
            'ROLE_ANALYST' => ['risk_alerts', 'loan_status_updates'],
            'ROLE_MANAGER' => ['risk_alerts', 'audit_alerts', 'system_notifications'],
            'ROLE_ADMIN' => ['audit_alerts', 'system_notifications', 'risk_alerts'],
            'ROLE_FINANCE' => ['payment_notifications', 'system_notifications']
        ];

        $successCount = 0;
        $totalCount = 0;

        foreach ($roles as $role) {
            if (isset($subscriptions[$role])) {
                foreach ($subscriptions[$role] as $topic) {
                    $totalCount++;
                    if ($this->subscribeUserToTopic($userId, $topic)) {
                        $successCount++;
                    }
                }
            }
        }

        $this->logger->info('Auto-subscribed user based on roles', [
            'user_id' => $userId,
            'roles' => $roles,
            'success_count' => $successCount,
            'total_count' => $totalCount
        ]);

        return $successCount === $totalCount;
    }

    /**
     * Met à jour les préférences de notification d'un utilisateur
     */
    public function updateUserNotificationPreferences(
        string $userId,
        array $preferences
    ): bool {
        try {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                return false;
            }

            // Mise à jour des préférences utilisateur
            $user->setNotificationPreferences($preferences);
            $this->entityManager->flush();

            // Réabonnement selon les nouvelles préférences
            if (isset($preferences['topics']) && is_array($preferences['topics'])) {
                // Désabonnement de tous les topics actuels
                $currentSubscriptions = $this->getUserSubscriptions($userId);
                foreach ($currentSubscriptions as $subscription) {
                    $this->unsubscribeUserFromTopic($userId, $subscription['topic']);
                }

                // Abonnement aux nouveaux topics
                foreach ($preferences['topics'] as $topic => $enabled) {
                    if ($enabled) {
                        $this->subscribeUserToTopic($userId, $topic, $preferences);
                    }
                }
            }

            $this->logger->info('Updated user notification preferences', [
                'user_id' => $userId,
                'preferences' => $preferences
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error updating user notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Nettoie les abonnements inactifs
     */
    public function cleanupInactiveSubscriptions(int $inactiveDays = 30): int
    {
        try {
            $cutoffDate = new DateTimeImmutable("-{$inactiveDays} days");
            
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(NotificationSubscription::class, 's')
               ->where('s.updatedAt < :cutoff')
               ->andWhere('s.isActive = :active')
               ->setParameter('cutoff', $cutoffDate)
               ->setParameter('active', false);

            $deletedCount = $qb->getQuery()->execute();

            $this->logger->info('Cleaned up inactive subscriptions', [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->format(DATE_ATOM)
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up inactive subscriptions', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Récupère les statistiques des abonnements
     */
    public function getSubscriptionStats(): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            
            // Total des abonnements actifs
            $totalActive = $qb->select('COUNT(s.id)')
                ->from(NotificationSubscription::class, 's')
                ->where('s.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult();

            // Abonnements par topic
            $qb = $this->entityManager->createQueryBuilder();
            $topicStats = $qb->select('s.topic, COUNT(s.id) as count')
                ->from(NotificationSubscription::class, 's')
                ->where('s.isActive = :active')
                ->setParameter('active', true)
                ->groupBy('s.topic')
                ->getQuery()
                ->getArrayResult();

            return [
                'total_active_subscriptions' => $totalActive,
                'subscriptions_by_topic' => $topicStats,
                'mercure_stats' => $this->mercureService->getNotificationStats()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving subscription stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
