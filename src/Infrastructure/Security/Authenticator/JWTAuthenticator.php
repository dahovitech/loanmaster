<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Authenticator;

use App\Infrastructure\Security\Service\SecurityService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JWTAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'api_auth_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private JWTTokenManagerInterface $jwtManager,
        private SecurityService $securityService
    ) {}

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $request->getSession()->set('_security.last_username', $email);
        
        // Vérifier les tentatives de connexion
        $this->securityService->checkLoginAttempts($email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        // Logger la connexion réussie
        $this->securityService->logSuccessfulLogin($user);
        
        // Générer le token JWT
        $jwtToken = $this->jwtManager->create($user);
        
        return new JsonResponse([
            'token' => $jwtToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'twoFactorEnabled' => $user->isTwoFactorEnabled()
            ]
        ]);
    }
    
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? 'unknown';
        
        // Logger l'échec de connexion
        $this->securityService->logFailedLogin($email, $exception->getMessage());
        
        return new JsonResponse([
            'error' => 'Invalid credentials'
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
