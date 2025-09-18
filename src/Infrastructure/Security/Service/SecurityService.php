<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Service;

use App\Domain\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityService
{
    public function __construct(
        private RateLimiterFactory $loginAttemptLimiter,
        private RequestStack $requestStack,
        private LoggerInterface $securityLogger
    ) {}

    public function checkLoginAttempts(string $identifier): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request?->getClientIp() ?? 'unknown';
        
        // Limiter par IP
        $ipLimiter = $this->loginAttemptLimiter->create($clientIp);
        
        // Limiter par identifiant (email)
        $userLimiter = $this->loginAttemptLimiter->create($identifier);
        
        if (!$ipLimiter->consume()->isAccepted() || !$userLimiter->consume()->isAccepted()) {
            $this->securityLogger->warning('Too many login attempts', [
                'ip' => $clientIp,
                'identifier' => $identifier,
                'timestamp' => new \DateTimeImmutable()
            ]);
            
            throw new TooManyLoginAttemptsAuthenticationException(
                'Too many login attempts. Please try again later.'
            );
        }
    }
    
    public function logSuccessfulLogin(UserInterface $user): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $this->securityLogger->info('Successful login', [
            'user_id' => $user->getUserIdentifier(),
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'timestamp' => new \DateTimeImmutable()
        ]);
    }
    
    public function logFailedLogin(string $identifier, string $reason): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        $this->securityLogger->warning('Failed login attempt', [
            'identifier' => $identifier,
            'reason' => $reason,
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent'),
            'timestamp' => new \DateTimeImmutable()
        ]);
    }
    
    public function isPasswordSecure(string $password): bool
    {
        $minLength = $_ENV['SECURITY_PASSWORD_MIN_LENGTH'] ?? 12;
        
        // Vérifications de base
        if (strlen($password) < $minLength) {
            return false;
        }
        
        // Au moins une majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Au moins une minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Au moins un caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    public function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    public function hashSensitiveData(string $data): string
    {
        return hash('sha256', $data . $_ENV['APP_SECRET']);
    }
}
