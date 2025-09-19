<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour les fonctionnalités PWA (Progressive Web App)
 */
class PWAController extends AbstractController
{
    /**
     * Service Worker endpoint
     */
    public function serviceWorker(): Response
    {
        $response = new Response(
            file_get_contents($this->getParameter('kernel.project_dir') . '/public/sw.js'),
            200,
            [
                'Content-Type' => 'application/javascript',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]
        );

        return $response;
    }

    /**
     * Share Target pour recevoir les contenus partagés vers l'app
     */
    public function shareTarget(Request $request): Response
    {
        $title = $request->request->get('title', '');
        $text = $request->request->get('text', '');
        $url = $request->request->get('url', '');
        $files = $request->files->get('documents', []);

        // Log de l'action de partage
        $this->addFlash('success', 'Contenu partagé reçu avec succès !');

        // Traitement des données partagées
        $sharedData = [
            'title' => $title,
            'text' => $text,
            'url' => $url,
            'files_count' => count($files),
            'received_at' => new \DateTime()
        ];

        // Ici vous pouvez traiter les données partagées
        // Par exemple, créer une nouvelle demande de prêt ou sauvegarder des documents

        return $this->render('pwa/share_target.html.twig', [
            'shared_data' => $sharedData
        ]);
    }

    /**
     * Gestion du prompt d'installation PWA
     */
    public function installPrompt(): JsonResponse
    {
        // Vérifier si l'utilisateur est connecté
        if (!$this->getUser()) {
            return new JsonResponse([
                'error' => 'Utilisateur non connecté'
            ], 401);
        }

        // Logique pour déterminer si on doit afficher le prompt
        $shouldShowPrompt = $this->shouldShowInstallPrompt();

        return new JsonResponse([
            'show_prompt' => $shouldShowPrompt,
            'user_id' => $this->getUser()->getId(),
            'install_benefits' => [
                'Accès hors ligne aux dernières données',
                'Notifications push des mises à jour de prêts',
                'Interface optimisée pour mobile',
                'Démarrage plus rapide'
            ]
        ]);
    }

    /**
     * API endpoint pour vérifier la santé de l'application (utilisé par offline.html)
     */
    #[Route('/api/health/ping', name: 'api_health_ping', methods: ['GET'])]
    public function healthPing(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => time(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Obtenir les statistiques PWA pour l'utilisateur
     */
    #[Route('/api/pwa/stats', name: 'api_pwa_stats', methods: ['GET'])]
    public function pwaStats(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }

        // Collecter les statistiques PWA
        $stats = [
            'user_id' => $user->getId(),
            'features' => [
                'offline_support' => true,
                'push_notifications' => true,
                'background_sync' => true,
                'share_target' => true
            ],
            'cache_status' => [
                'static_cache' => 'active',
                'dynamic_cache' => 'active',
                'images_cache' => 'active'
            ],
            'last_sync' => new \DateTime()
        ];

        return new JsonResponse($stats);
    }

    /**
     * Endpoint pour enregistrer les métriques PWA
     */
    #[Route('/api/pwa/metrics', name: 'api_pwa_metrics', methods: ['POST'])]
    public function recordMetrics(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Log des métriques de performance PWA
        $this->getLogger('pwa')->info('PWA Metrics recorded', [
            'user_id' => $this->getUser()?->getId(),
            'metrics' => $data,
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => new \DateTime()
        ]);

        return new JsonResponse(['status' => 'recorded']);
    }

    /**
     * Détermine s'il faut afficher le prompt d'installation
     */
    private function shouldShowInstallPrompt(): bool
    {
        // Logique métier pour déterminer quand afficher le prompt
        // Par exemple : après X visites, pour certains types d'utilisateurs, etc.
        
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        // Ne pas afficher trop souvent
        // Ici vous pourriez vérifier en base de données la dernière fois que le prompt a été affiché
        
        return true; // Pour la démonstration
    }

    /**
     * Obtenir le logger
     */
    private function getLogger(string $channel = 'app'): \Psr\Log\LoggerInterface
    {
        return $this->container->get('logger');
    }
}
