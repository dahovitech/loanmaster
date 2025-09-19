<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Service\PushNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pwa', name: 'pwa_')]
class PWAController extends AbstractController
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService
    ) {}

    /**
     * Serve the PWA manifest
     */
    #[Route('/manifest.json', name: 'manifest', methods: ['GET'])]
    public function manifest(): JsonResponse
    {
        $manifestPath = $this->getParameter('kernel.project_dir') . '/public/manifest.json';
        
        if (!file_exists($manifestPath)) {
            return $this->json(['error' => 'Manifest not found'], 404);
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        $response = $this->json($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');
        $response->setSharedMaxAge(86400); // Cache 24h
        
        return $response;
    }

    /**
     * Subscribe to push notifications
     */
    #[Route('/push/subscribe', name: 'push_subscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function subscribeToPush(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['subscription'])) {
            return $this->json(['error' => 'Subscription data required'], 400);
        }
        
        try {
            $user = $this->getUser();
            $subscription = $data['subscription'];
            $userAgent = $data['userAgent'] ?? null;
            
            $result = $this->pushNotificationService->subscribe(
                $user->getId(),
                $subscription,
                $userAgent
            );
            
            return $this->json([
                'success' => true,
                'subscription_id' => $result['id'],
                'message' => 'Successfully subscribed to push notifications'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to subscribe to push notifications',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    #[Route('/push/unsubscribe', name: 'push_unsubscribe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unsubscribeFromPush(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true);
            $endpoint = $data['endpoint'] ?? null;
            
            $this->pushNotificationService->unsubscribe($user->getId(), $endpoint);
            
            return $this->json([
                'success' => true,
                'message' => 'Successfully unsubscribed from push notifications'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to unsubscribe from push notifications',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a test push notification
     */
    #[Route('/push/test', name: 'push_test', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendTestPush(): JsonResponse
    {
        try {
            $user = $this->getUser();
            
            $notification = [
                'title' => 'Test de notification - LoanMaster',
                'body' => 'Ceci est une notification de test pour vérifier que les notifications push fonctionnent correctement.',
                'icon' => '/icons/icon-192x192.png',
                'tag' => 'test-notification',
                'data' => [
                    'url' => '/profile',
                    'type' => 'test'
                ]
            ];
            
            $result = $this->pushNotificationService->sendToUser($user->getId(), $notification);
            
            return $this->json([
                'success' => true,
                'message' => 'Test notification sent',
                'sent_count' => $result['sent_count']
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to send test notification',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PWA installation status and metrics
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getStatus(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'user_id' => $user->getId(),
            'push_subscriptions' => $this->pushNotificationService->getUserSubscriptionCount($user->getId()),
            'notifications_enabled' => $this->pushNotificationService->areNotificationsEnabled($user->getId()),
            'pwa_capabilities' => [
                'service_worker' => true,
                'push_notifications' => true,
                'background_sync' => true,
                'offline_support' => true,
                'installation' => true
            ],
            'cache_info' => $this->getCacheInfo()
        ]);
    }

    /**
     * Handle PWA share target
     */
    #[Route('/share-target', name: 'share_target', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function handleShareTarget(Request $request): Response
    {
        $title = $request->request->get('title', '');
        $text = $request->request->get('text', '');
        $url = $request->request->get('url', '');
        $files = $request->files->get('documents', []);
        
        // Traitement des données partagées
        $sharedData = [
            'title' => $title,
            'text' => $text,
            'url' => $url,
            'files_count' => count($files)
        ];
        
        // Rediriger vers une page appropriée avec les données
        return $this->render('pwa/share_target.html.twig', [
            'shared_data' => $sharedData,
            'files' => $files
        ]);
    }

    /**
     * Analytics tracking for PWA events
     */
    #[Route('/analytics/track', name: 'analytics_track', methods: ['POST'])]
    public function trackAnalytics(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $event = $data['event'] ?? 'unknown';
        $eventData = $data['data'] ?? [];
        $timestamp = $data['timestamp'] ?? time();
        $userAgent = $data['userAgent'] ?? $request->headers->get('User-Agent');
        
        // Log l'événement pour analytics
        // Dans une vraie application, vous enverriez vers votre système d'analytics
        $this->container->get('logger')->info('PWA Analytics Event', [
            'event' => $event,
            'data' => $eventData,
            'timestamp' => $timestamp,
            'user_agent' => $userAgent,
            'user_id' => $this->getUser()?->getId(),
            'ip' => $request->getClientIp()
        ]);
        
        return $this->json(['success' => true]);
    }

    /**
     * Sync data for offline usage
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function syncData(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $syncTypes = $data['types'] ?? ['loans', 'profile', 'notifications'];
        
        $syncResult = [];
        
        foreach ($syncTypes as $type) {
            try {
                $syncResult[$type] = $this->syncDataType($type, $user);
            } catch (\Exception $e) {
                $syncResult[$type] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->json([
            'success' => true,
            'sync_result' => $syncResult,
            'timestamp' => time()
        ]);
    }

    /**
     * Get offline-capable data
     */
    #[Route('/offline-data', name: 'offline_data', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getOfflineData(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'user_profile' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName()
            ],
            'recent_loans' => $this->getRecentLoansForOffline($user),
            'notifications' => $this->getRecentNotificationsForOffline($user),
            'settings' => $this->getUserSettingsForOffline($user),
            'cache_timestamp' => time()
        ]);
    }

    /**
     * Health check endpoint for PWA
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
            'version' => $this->getParameter('app.version') ?? '1.0.0'
        ]);
    }

    /**
     * Handle protocol handlers
     */
    #[Route('/handle', name: 'protocol_handler', methods: ['GET'])]
    public function handleProtocol(Request $request): Response
    {
        $protocol = $request->query->get('protocol', '');
        
        // Parser le protocole personnalisé web+loanmaster://
        if (str_starts_with($protocol, 'web+loanmaster://')) {
            $action = str_replace('web+loanmaster://', '', $protocol);
            
            return match ($action) {
                'new-loan' => $this->redirectToRoute('loan_new'),
                'my-loans' => $this->redirectToRoute('loan_list'),
                'profile' => $this->redirectToRoute('user_profile'),
                default => $this->redirectToRoute('app_home')
            };
        }
        
        return $this->redirectToRoute('app_home');
    }

    // Méthodes privées

    private function getCacheInfo(): array
    {
        return [
            'version' => 'loanmaster-v1.0.0',
            'last_updated' => time(),
            'available_offline' => [
                'user_profile',
                'recent_loans',
                'notifications',
                'static_assets'
            ]
        ];
    }

    private function syncDataType(string $type, $user): array
    {
        return match ($type) {
            'loans' => $this->syncLoansData($user),
            'profile' => $this->syncProfileData($user),
            'notifications' => $this->syncNotificationsData($user),
            default => ['success' => false, 'error' => "Unknown sync type: {$type}"]
        };
    }

    private function syncLoansData($user): array
    {
        // Simuler la synchronisation des données de prêts
        return [
            'success' => true,
            'count' => 5,
            'last_sync' => time()
        ];
    }

    private function syncProfileData($user): array
    {
        return [
            'success' => true,
            'user_id' => $user->getId(),
            'last_sync' => time()
        ];
    }

    private function syncNotificationsData($user): array
    {
        return [
            'success' => true,
            'count' => 3,
            'last_sync' => time()
        ];
    }

    private function getRecentLoansForOffline($user): array
    {
        // Retourner les données essentielles des prêts récents
        return [
            [
                'id' => 1,
                'amount' => 5000,
                'status' => 'APPROVED',
                'created_at' => '2024-01-15'
            ],
            [
                'id' => 2,
                'amount' => 3000,
                'status' => 'PENDING',
                'created_at' => '2024-01-10'
            ]
        ];
    }

    private function getRecentNotificationsForOffline($user): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Prêt approuvé',
                'message' => 'Votre prêt de 5000€ a été approuvé',
                'created_at' => '2024-01-16'
            ]
        ];
    }

    private function getUserSettingsForOffline($user): array
    {
        return [
            'notifications_enabled' => true,
            'theme' => 'light',
            'language' => 'fr'
        ];
    }
}
