<?php

namespace App\GraphQL\Middleware;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Middleware d'authentification pour GraphQL
 * Vérifie les permissions et l'authentification
 */
class AuthenticationMiddleware
{
    /**
     * Exécute le middleware d'authentification
     */
    public function __invoke(callable $next, $root, array $args, $context, ResolveInfo $info)
    {
        // Liste des opérations qui ne nécessitent pas d'authentification
        $publicOperations = [
            'loanStatistics', // Statistiques publiques
            '__schema',       // Introspection GraphQL
            '__type'          // Introspection GraphQL
        ];
        
        $operationName = $info->fieldName;
        
        // Skip l'authentification pour les opérations publiques
        if (in_array($operationName, $publicOperations)) {
            return $next($root, $args, $context, $info);
        }
        
        // Vérification de l'authentification
        if (!$this->isAuthenticated($context)) {
            throw new UserError('Authentication required');
        }
        
        // Vérification des permissions spécifiques
        if (!$this->hasPermission($context, $operationName, $args)) {
            throw new UserError('Insufficient permissions');
        }
        
        return $next($root, $args, $context, $info);
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    private function isAuthenticated($context): bool
    {
        return isset($context['user']) && !empty($context['user']['id']);
    }

    /**
     * Vérifie les permissions spécifiques
     */
    private function hasPermission($context, string $operation, array $args): bool
    {
        $user = $context['user'] ?? [];
        $userRoles = $user['roles'] ?? [];
        
        // Permissions par opération
        $permissions = [
            // Requêtes (lecture)
            'loans' => ['ROLE_USER', 'ROLE_ADMIN'],
            'loan' => ['ROLE_USER', 'ROLE_ADMIN'],
            'auditHistory' => ['ROLE_ADMIN', 'ROLE_AUDITOR'],
            'eventHistory' => ['ROLE_ADMIN'],
            'reconstructState' => ['ROLE_ADMIN'],
            
            // Mutations (modification)
            'createLoanApplication' => ['ROLE_USER', 'ROLE_ADMIN'],
            'changeLoanStatus' => ['ROLE_ADMIN', 'ROLE_LOAN_OFFICER'],
            'assessLoanRisk' => ['ROLE_ADMIN', 'ROLE_RISK_ANALYST'],
            'fundLoan' => ['ROLE_ADMIN', 'ROLE_FINANCE'],
            'processPayment' => ['ROLE_ADMIN', 'ROLE_FINANCE'],
        ];
        
        $requiredRoles = $permissions[$operation] ?? ['ROLE_ADMIN'];
        
        // Vérification si l'utilisateur a au moins un des rôles requis
        return !empty(array_intersect($userRoles, $requiredRoles));
    }

    /**
     * Vérifications spécifiques par contexte
     */
    private function hasContextualPermission($context, string $operation, array $args): bool
    {
        $user = $context['user'] ?? [];
        $userId = $user['id'] ?? null;
        
        switch ($operation) {
            case 'loan':
            case 'loans':
                // Un utilisateur peut voir ses propres prêts
                if (isset($args['filters']['customerId'])) {
                    return $args['filters']['customerId'] === $userId || 
                           in_array('ROLE_ADMIN', $user['roles'] ?? []);
                }
                break;
                
            case 'auditHistory':
                // Un utilisateur peut voir son propre audit
                if (isset($args['userId'])) {
                    return $args['userId'] === $userId || 
                           in_array('ROLE_ADMIN', $user['roles'] ?? []);
                }
                break;
        }
        
        return true;
    }
}
