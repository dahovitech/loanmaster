<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Cette méthode ne sera jamais appelée car elle est gérée par le JWT authenticator
        // Elle sert juste à définir la route pour la documentation API
        return $this->json([
            'message' => 'Login endpoint - handled by JWT authenticator'
        ]);
    }
    
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        // Géré par le bundle JWT refresh token
        return $this->json([
            'message' => 'Token refresh endpoint'
        ]);
    }
    
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'firstName' => $user->getFirstName() ?? null,
                'lastName' => $user->getLastName() ?? null
            ]
        ]);
    }
    
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(): JsonResponse
    {
        // Avec JWT, la déconnexion se fait côté client en supprimant le token
        return $this->json([
            'success' => true,
            'message' => 'Logout successful. Please remove the token from client storage.'
        ]);
    }
}
