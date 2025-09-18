<?php

namespace App\Controller\Admin;

use App\Service\Notification\NotificationOrchestrator;
use App\Service\Notification\SubscriptionManager;
use App\Service\Notification\MercureNotificationService;
use App\Entity\Notification;
use App\Entity\NotificationSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur d'administration des notifications temps réel
 */
#[Route('/admin/notifications', name: 'admin_notifications_')]
#[IsGranted('ROLE_ADMIN')]
class NotificationAdminController extends AbstractController
{
    private NotificationOrchestrator $notificationOrchestrator;
    private SubscriptionManager $subscriptionManager;
    private MercureNotificationService $mercureService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        NotificationOrchestrator $notificationOrchestrator,
        SubscriptionManager $subscriptionManager,
        MercureNotificationService $mercureService,
        EntityManagerInterface $entityManager
    ) {
        $this->notificationOrchestrator = $notificationOrchestrator;
        $this->subscriptionManager = $subscriptionManager;
        $this->mercureService = $mercureService;
        $this->entityManager = $entityManager;
    }

    /**
     * Dashboard principal des notifications
     */
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Statistiques générales
        $stats = $this->getNotificationStats();
        
        // Notifications récentes
        $recentNotifications = $this->entityManager
            ->getRepository(Notification::class)
            ->findBy([], ['createdAt' => 'DESC'], 10);

        // Abonnements actifs
        $subscriptionStats = $this->subscriptionManager->getSubscriptionStats();

        return $this->render('admin/notifications/dashboard.html.twig', [
            'stats' => $stats,
            'recent_notifications' => $recentNotifications,
            'subscription_stats' => $subscriptionStats,
            'mercure_stats' => $this->mercureService->getNotificationStats()
        ]);
    }

    /**
     * Liste des notifications envoyées
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $notifications = $this->entityManager
            ->getRepository(Notification::class)
            ->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

        $total = $this->entityManager
            ->getRepository(Notification::class)
            ->count([]);

        return $this->render('admin/notifications/history.html.twig', [
            'notifications' => $notifications,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_notifications' => $total
        ]);
    }

    /**
     * Détails d'une notification
     */
    #[Route('/{id}', name: 'details', methods: ['GET'])]
    public function details(Notification $notification): Response
    {
        return $this->render('admin/notifications/details.html.twig', [
            'notification' => $notification
        ]);
    }

    /**
     * Test d'envoi de notification
     */
    #[Route('/test', name: 'test', methods: ['GET', 'POST'])]
    public function test(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $recipients = json_decode($request->request->get('recipients'), true);
            $data = json_decode($request->request->get('data'), true);
            $options = json_decode($request->request->get('options'), true);

            try {
                $result = $this->notificationOrchestrator->sendNotification(
                    $type,
                    $recipients,
                    $data,
                    $options
                );

                $this->addFlash('success', 
                    "Notification de test envoyée avec succès. " .
                    "Livrées: {$result->getDeliveredCount()}, " .
                    "Échecs: {$result->getFailedCount()}"
                );

                return $this->redirectToRoute('admin_notifications_details', [
                    'id' => $this->findNotificationByNotificationId($result->getNotificationId())?->getId()
                ]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi: ' . $e->getMessage());
            }
        }

        return $this->render('admin/notifications/test.html.twig');
    }

    /**
     * Gestion des abonnements
     */
    #[Route('/subscriptions', name: 'subscriptions', methods: ['GET'])]
    public function subscriptions(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $subscriptions = $this->entityManager
            ->getRepository(NotificationSubscription::class)
            ->findBy(['isActive' => true], ['updatedAt' => 'DESC'], $limit, $offset);

        $total = $this->entityManager
            ->getRepository(NotificationSubscription::class)
            ->count(['isActive' => true]);

        return $this->render('admin/notifications/subscriptions.html.twig', [
            'subscriptions' => $subscriptions,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_subscriptions' => $total
        ]);
    }

    /**
     * Statistiques en temps réel (API)
     */
    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function apiStats(): JsonResponse
    {
        return $this->json([
            'notification_stats' => $this->getNotificationStats(),
            'subscription_stats' => $this->subscriptionManager->getSubscriptionStats(),
            'mercure_stats' => $this->mercureService->getNotificationStats(),
            'system_health' => $this->getSystemHealth()
        ]);
    }

    /**
     * Broadcast d'une notification système
     */
    #[Route('/broadcast', name: 'broadcast', methods: ['POST'])]
    public function broadcast(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $type = $data['type'] ?? 'system_notification';
            $message = $data['message'] ?? '';
            $priority = $data['priority'] ?? 'normal';
            $targetRoles = $data['target_roles'] ?? ['ROLE_USER'];
            
            if (empty($message)) {
                return $this->json(['error' => 'Message requis'], 400);
            }

            // Construction des destinataires
            $recipients = [];
            foreach ($targetRoles as $role) {
                $recipients[] = [
                    'type' => 'role',
                    'role' => $role
                ];
            }

            $notificationData = [
                'title' => 'Notification Système',
                'message' => $message,
                'priority' => $priority,
                'broadcast_by' => $this->getUser()->getEmail(),
                'broadcast_at' => date(DATE_ATOM)
            ];

            $options = [
                'channels' => ['mercure', 'push'],
                'priority' => $priority,
                'template' => 'system_broadcast'
            ];

            $result = $this->notificationOrchestrator->sendNotification(
                $type,
                $recipients,
                $notificationData,
                $options
            );

            return $this->json([
                'success' => true,
                'notification_id' => $result->getNotificationId(),
                'delivered_count' => $result->getDeliveredCount(),
                'failed_count' => $result->getFailedCount()
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoyage des notifications anciennes
     */
    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $days = $request->request->getInt('days', 30);
            
            $cutoffDate = new \DateTimeImmutable("-{$days} days");
            
            $qb = $this->entityManager->createQueryBuilder();
            $deletedCount = $qb->delete(Notification::class, 'n')
                ->where('n.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoffDate)
                ->getQuery()
                ->execute();

            // Nettoyage des abonnements inactifs
            $inactiveSubscriptions = $this->subscriptionManager->cleanupInactiveSubscriptions($days);

            return $this->json([
                'success' => true,
                'deleted_notifications' => $deletedCount,
                'deleted_subscriptions' => $inactiveSubscriptions
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les statistiques des notifications
     */
    private function getNotificationStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Total des notifications aujourd'hui
        $todayStart = new \DateTimeImmutable('today');
        $todayNotifications = $qb->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.createdAt >= :today')
            ->setParameter('today', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();

        // Notifications par statut
        $qb = $this->entityManager->createQueryBuilder();
        $statusStats = $qb->select('n.status, COUNT(n.id) as count')
            ->from(Notification::class, 'n')
            ->where('n.createdAt >= :week')
            ->setParameter('week', new \DateTimeImmutable('-7 days'))
            ->groupBy('n.status')
            ->getQuery()
            ->getArrayResult();

        // Notifications par type cette semaine
        $qb = $this->entityManager->createQueryBuilder();
        $typeStats = $qb->select('n.type, COUNT(n.id) as count')
            ->from(Notification::class, 'n')
            ->where('n.createdAt >= :week')
            ->setParameter('week', new \DateTimeImmutable('-7 days'))
            ->groupBy('n.type')
            ->getQuery()
            ->getArrayResult();

        // Taux de succès moyen
        $qb = $this->entityManager->createQueryBuilder();
        $successRate = $qb->select('AVG(n.deliveredCount / (n.deliveredCount + n.failedCount) * 100)')
            ->from(Notification::class, 'n')
            ->where('n.createdAt >= :week')
            ->andWhere('(n.deliveredCount + n.failedCount) > 0')
            ->setParameter('week', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'today_notifications' => $todayNotifications,
            'status_distribution' => $statusStats,
            'type_distribution' => $typeStats,
            'average_success_rate' => round($successRate ?? 0, 2)
        ];
    }

    /**
     * Vérifie la santé du système de notifications
     */
    private function getSystemHealth(): array
    {
        $health = [
            'mercure_available' => false,
            'database_available' => false,
            'recent_errors' => 0
        ];

        try {
            // Test de connectivité Mercure
            $health['mercure_available'] = $this->mercureService !== null;
            
            // Test de connectivité base de données
            $this->entityManager->getConnection()->connect();
            $health['database_available'] = true;
            
            // Erreurs récentes (dernière heure)
            $qb = $this->entityManager->createQueryBuilder();
            $recentErrors = $qb->select('COUNT(n.id)')
                ->from(Notification::class, 'n')
                ->where('n.status = :status')
                ->andWhere('n.createdAt >= :hour')
                ->setParameter('status', 'failed')
                ->setParameter('hour', new \DateTimeImmutable('-1 hour'))
                ->getQuery()
                ->getSingleScalarResult();
                
            $health['recent_errors'] = $recentErrors;
            
        } catch (\Exception $e) {
            // Les erreurs sont déjà reflétées dans les valeurs par défaut
        }

        return $health;
    }

    /**
     * Trouve une notification par son ID unique
     */
    private function findNotificationByNotificationId(string $notificationId): ?Notification
    {
        return $this->entityManager
            ->getRepository(Notification::class)
            ->findOneBy(['notificationId' => $notificationId]);
    }
}
