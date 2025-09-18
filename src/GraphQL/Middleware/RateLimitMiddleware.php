<?php

namespace App\GraphQL\Middleware;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Middleware de limitation de taux pour GraphQL
 * Prévient les abus et attaques DDoS
 */
class RateLimitMiddleware
{
    private CacheItemPoolInterface $cache;
    private array $limits;

    public function __construct(CacheItemPoolInterface $cache = null)
    {
        $this->cache = $cache ?? new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        
        // Configuration des limites par défaut
        $this->limits = [
            'global' => ['requests' => 1000, 'window' => 3600], // 1000 req/heure
            'user' => ['requests' => 100, 'window' => 3600],     // 100 req/heure par utilisateur
            'ip' => ['requests' => 200, 'window' => 3600],       // 200 req/heure par IP
            'operations' => [
                'createLoanApplication' => ['requests' => 10, 'window' => 3600], // 10 créations/heure
                'changeLoanStatus' => ['requests' => 50, 'window' => 3600],      // 50 changements/heure
                'auditHistory' => ['requests' => 20, 'window' => 3600],          // 20 audits/heure
            ]
        ];
    }

    /**
     * Exécute le middleware de limitation de taux
     */
    public function __invoke(callable $next, $root, array $args, $context, ResolveInfo $info)
    {
        $operationName = $info->fieldName;
        $user = $context['user'] ?? [];
        $request = $context['request'] ?? [];
        
        $userId = $user['id'] ?? null;
        $ipAddress = $request['ip'] ?? 'unknown';
        
        // Vérification des limites globales
        $this->checkGlobalLimit();
        
        // Vérification par utilisateur
        if ($userId) {
            $this->checkUserLimit($userId);
        }
        
        // Vérification par IP
        $this->checkIpLimit($ipAddress);
        
        // Vérification par opération spécifique
        $this->checkOperationLimit($operationName, $userId, $ipAddress);
        
        // Enregistrement de la requête
        $this->recordRequest($userId, $ipAddress, $operationName);
        
        return $next($root, $args, $context, $info);
    }

    /**
     * Vérifie la limite globale
     */
    private function checkGlobalLimit(): void
    {
        $key = 'rate_limit:global';
        $limit = $this->limits['global'];
        
        if ($this->isLimitExceeded($key, $limit['requests'], $limit['window'])) {
            throw new UserError('Global rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Vérifie la limite par utilisateur
     */
    private function checkUserLimit(string $userId): void
    {
        $key = 'rate_limit:user:' . $userId;
        $limit = $this->limits['user'];
        
        if ($this->isLimitExceeded($key, $limit['requests'], $limit['window'])) {
            throw new UserError('User rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Vérifie la limite par IP
     */
    private function checkIpLimit(string $ipAddress): void
    {
        $key = 'rate_limit:ip:' . md5($ipAddress);
        $limit = $this->limits['ip'];
        
        if ($this->isLimitExceeded($key, $limit['requests'], $limit['window'])) {
            throw new UserError('IP rate limit exceeded. Please try again later.');
        }
    }

    /**
     * Vérifie la limite par opération
     */
    private function checkOperationLimit(string $operation, ?string $userId, string $ipAddress): void
    {
        if (!isset($this->limits['operations'][$operation])) {
            return; // Pas de limite spécifique pour cette opération
        }
        
        $limit = $this->limits['operations'][$operation];
        
        // Vérification par utilisateur pour cette opération
        if ($userId) {
            $key = "rate_limit:operation:{$operation}:user:{$userId}";
            if ($this->isLimitExceeded($key, $limit['requests'], $limit['window'])) {
                throw new UserError("Rate limit exceeded for operation '{$operation}'. Please try again later.");
            }
        }
        
        // Vérification par IP pour cette opération
        $key = "rate_limit:operation:{$operation}:ip:" . md5($ipAddress);
        if ($this->isLimitExceeded($key, $limit['requests'], $limit['window'])) {
            throw new UserError("Rate limit exceeded for operation '{$operation}' from this IP. Please try again later.");
        }
    }

    /**
     * Vérifie si une limite est dépassée
     */
    private function isLimitExceeded(string $key, int $maxRequests, int $windowSeconds): bool
    {
        try {
            $cacheItem = $this->cache->getItem($key);
            
            if (!$cacheItem->isHit()) {
                // Première requête dans cette fenêtre
                $data = ['count' => 0, 'reset_time' => time() + $windowSeconds];
            } else {
                $data = $cacheItem->get();
                
                // Vérification si la fenêtre a expiré
                if (time() >= $data['reset_time']) {
                    $data = ['count' => 0, 'reset_time' => time() + $windowSeconds];
                }
            }
            
            return $data['count'] >= $maxRequests;
            
        } catch (\Exception $e) {
            // En cas d'erreur de cache, on autorise la requête
            return false;
        }
    }

    /**
     * Enregistre une requête
     */
    private function recordRequest(?string $userId, string $ipAddress, string $operation): void
    {
        $keys = [
            'rate_limit:global',
            'rate_limit:ip:' . md5($ipAddress)
        ];
        
        if ($userId) {
            $keys[] = 'rate_limit:user:' . $userId;
        }
        
        if (isset($this->limits['operations'][$operation])) {
            if ($userId) {
                $keys[] = "rate_limit:operation:{$operation}:user:{$userId}";
            }
            $keys[] = "rate_limit:operation:{$operation}:ip:" . md5($ipAddress);
        }
        
        foreach ($keys as $key) {
            $this->incrementCounter($key);
        }
    }

    /**
     * Incrémente un compteur
     */
    private function incrementCounter(string $key): void
    {
        try {
            $cacheItem = $this->cache->getItem($key);
            
            if (!$cacheItem->isHit()) {
                $windowSeconds = $this->getWindowForKey($key);
                $data = ['count' => 1, 'reset_time' => time() + $windowSeconds];
            } else {
                $data = $cacheItem->get();
                
                // Vérification si la fenêtre a expiré
                if (time() >= $data['reset_time']) {
                    $windowSeconds = $this->getWindowForKey($key);
                    $data = ['count' => 1, 'reset_time' => time() + $windowSeconds];
                } else {
                    $data['count']++;
                }
            }
            
            $cacheItem->set($data);
            $cacheItem->expiresAfter($data['reset_time'] - time());
            $this->cache->save($cacheItem);
            
        } catch (\Exception $e) {
            // Ignorer les erreurs de cache pour ne pas bloquer l'application
        }
    }

    /**
     * Détermine la fenêtre de temps pour une clé
     */
    private function getWindowForKey(string $key): int
    {
        if (strpos($key, 'global') !== false) {
            return $this->limits['global']['window'];
        } elseif (strpos($key, 'user') !== false) {
            return $this->limits['user']['window'];
        } elseif (strpos($key, 'ip') !== false) {
            return $this->limits['ip']['window'];
        } elseif (strpos($key, 'operation') !== false) {
            // Extraire le nom de l'opération de la clé
            $parts = explode(':', $key);
            $operation = $parts[2] ?? null;
            if ($operation && isset($this->limits['operations'][$operation])) {
                return $this->limits['operations'][$operation]['window'];
            }
        }
        
        return 3600; // Par défaut : 1 heure
    }

    /**
     * Configure les limites personnalisées
     */
    public function setLimits(array $limits): void
    {
        $this->limits = array_merge($this->limits, $limits);
    }

    /**
     * Récupère les statistiques de limitation
     */
    public function getStats(): array
    {
        // TODO: Implémenter la récupération des statistiques
        return [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'active_limits' => count($this->limits)
        ];
    }
}
