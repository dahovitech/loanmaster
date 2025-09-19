<?php

namespace App\GraphQL\Middleware;

use App\Infrastructure\EventSourcing\AuditService;
use GraphQL\Type\Definition\ResolveInfo;
use DateTimeImmutable;

/**
 * Middleware d'audit pour GraphQL
 * Enregistre toutes les opérations GraphQL pour conformité
 */
class AuditMiddleware
{
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Exécute le middleware d'audit
     */
    public function __invoke(callable $next, $root, array $args, $context, ResolveInfo $info)
    {
        $startTime = microtime(true);
        $operationName = $info->fieldName;
        $operationType = $info->parentType->name; // Query, Mutation, Subscription
        
        // Informations de contexte
        $user = $context['user'] ?? [];
        $request = $context['request'] ?? [];
        
        $correlationId = $this->generateCorrelationId();
        
        // Audit du début de l'opération
        $this->auditService->recordAuditEntry(
            'graphql_operation',
            $correlationId,
            'operation_started',
            null,
            [
                'operation_name' => $operationName,
                'operation_type' => $operationType,
                'query' => $this->sanitizeQuery($info->operation ?? null),
                'variables' => $this->sanitizeVariables($args)
            ],
            $user['id'] ?? null,
            $request['ip'] ?? null,
            $request['userAgent'] ?? null,
            $correlationId,
            [
                'graphql' => true,
                'operation_path' => $info->path ?? []
            ]
        );
        
        try {
            // Exécution de l'opération
            $result = $next($root, $args, $context, $info);
            
            $executionTime = microtime(true) - $startTime;
            
            // Audit de la réussite
            $this->auditService->recordAuditEntry(
                'graphql_operation',
                $correlationId,
                'operation_completed',
                null,
                [
                    'operation_name' => $operationName,
                    'operation_type' => $operationType,
                    'execution_time' => $executionTime,
                    'result_type' => $this->getResultType($result),
                    'result_size' => $this->estimateResultSize($result)
                ],
                $user['id'] ?? null,
                $request['ip'] ?? null,
                $request['userAgent'] ?? null,
                $correlationId
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Audit de l'échec
            $this->auditService->recordAuditEntry(
                'graphql_operation',
                $correlationId,
                'operation_failed',
                null,
                [
                    'operation_name' => $operationName,
                    'operation_type' => $operationType,
                    'execution_time' => $executionTime,
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ],
                $user['id'] ?? null,
                $request['ip'] ?? null,
                $request['userAgent'] ?? null,
                $correlationId
            );
            
            throw $e;
        }
    }

    /**
     * Nettoie la requête GraphQL pour l'audit
     */
    private function sanitizeQuery($operation): ?string
    {
        if (!$operation) {
            return null;
        }
        
        // Enlever les données sensibles de la requête
        $query = (string) $operation;
        
        // Masquer les mots de passe, tokens, etc.
        $query = preg_replace('/password\s*:\s*"[^"]*"/', 'password: "***"', $query);
        $query = preg_replace('/token\s*:\s*"[^"]*"/', 'token: "***"', $query);
        
        return $query;
    }

    /**
     * Nettoie les variables pour l'audit
     */
    private function sanitizeVariables(array $variables): array
    {
        $sanitized = $variables;
        
        // Masquer les données sensibles
        $sensitiveFields = ['password', 'token', 'ssn', 'creditCard', 'bankAccount'];
        
        array_walk_recursive($sanitized, function(&$value, $key) use ($sensitiveFields) {
            if (in_array($key, $sensitiveFields) && is_string($value)) {
                $value = '***';
            }
        });
        
        return $sanitized;
    }

    /**
     * Détermine le type de résultat
     */
    private function getResultType($result): string
    {
        if (is_array($result)) {
            return 'array';
        } elseif (is_object($result)) {
            return get_class($result);
        } elseif (is_null($result)) {
            return 'null';
        } else {
            return gettype($result);
        }
    }

    /**
     * Estime la taille du résultat
     */
    private function estimateResultSize($result): int
    {
        if (is_array($result)) {
            return count($result);
        } elseif (is_string($result)) {
            return strlen($result);
        } else {
            return 1;
        }
    }

    /**
     * Génère un ID de corrélation unique
     */
    private function generateCorrelationId(): string
    {
        return 'gql_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
}
