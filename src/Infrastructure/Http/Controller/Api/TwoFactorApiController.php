<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Infrastructure\Security\Service\TwoFactorService;
use App\Infrastructure\Security\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/2fa', name: 'api_2fa_')]
final class TwoFactorApiController extends AbstractController
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private SecurityService $securityService
    ) {}
    
    #[Route('/enable', name: 'enable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $method = $data['method'] ?? 'google';
        
        try {
            $result = $this->twoFactorService->enableTwoFactor($user, $method);
            
            return $this->json([
                'success' => true,
                'data' => [
                    'qrCode' => $result['qrCode'],
                    'backupCodes' => $result['backupCodes'],
                    'method' => $result['method']
                ],
                'message' => 'Two-factor authentication has been enabled. Please save your backup codes in a secure location.'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/verify', name: 'verify', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';
        
        if (!$code) {
            return $this->json([
                'success' => false,
                'error' => 'Verification code is required'
            ], 400);
        }
        
        $isValid = $this->twoFactorService->verifyCode($user, $code);
        
        if ($isValid) {
            // Activer la 2FA si c'est la première vérification
            if (!$user->isTwoFactorEnabled()) {
                $user->setTwoFactorEnabled(true);
                // TODO: Persister l'utilisateur
            }
            
            return $this->json([
                'success' => true,
                'message' => 'Code verified successfully'
            ]);
        }
        
        return $this->json([
            'success' => false,
            'error' => 'Invalid verification code'
        ], 400);
    }
    
    #[Route('/disable', name: 'disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? '';
        
        // Vérifier le mot de passe avant de désactiver la 2FA
        // TODO: Implémenter la vérification du mot de passe
        
        // Vérifier si la 2FA est obligatoire pour ce rôle
        if ($this->twoFactorService->isTwoFactorRequired($user)) {
            return $this->json([
                'success' => false,
                'error' => 'Two-factor authentication is required for your role and cannot be disabled'
            ], 403);
        }
        
        $this->twoFactorService->disableTwoFactor($user);
        
        return $this->json([
            'success' => true,
            'message' => 'Two-factor authentication has been disabled'
        ]);
    }
    
    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTwoFactorStatus(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'success' => true,
            'data' => [
                'enabled' => $user->isTwoFactorEnabled(),
                'required' => $this->twoFactorService->isTwoFactorRequired($user),
                'methods' => [
                    'google' => !empty($user->getGoogleAuthenticatorSecret()),
                    'totp' => !empty($user->getTotpSecret())
                ],
                'backupCodesRemaining' => count($user->getBackupCodes())
            ]
        ]);
    }
    
    #[Route('/backup-codes/regenerate', name: 'regenerate_backup_codes', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? '';
        
        if (!$user->isTwoFactorEnabled()) {
            return $this->json([
                'success' => false,
                'error' => 'Two-factor authentication is not enabled'
            ], 400);
        }
        
        // TODO: Vérifier le mot de passe
        
        // Générer de nouveaux codes de secours
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
        }
        
        $user->setBackupCodes($backupCodes);
        // TODO: Persister l'utilisateur
        
        return $this->json([
            'success' => true,
            'data' => [
                'backupCodes' => $backupCodes
            ],
            'message' => 'Backup codes have been regenerated. Please save them in a secure location.'
        ]);
    }
}
