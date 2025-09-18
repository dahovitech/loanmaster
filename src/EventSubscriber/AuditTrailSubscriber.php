<?php

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Service\Audit\AuditLoggerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class AuditTrailSubscriber implements EventSubscriberInterface
{
    // Entités à exclure de l'audit automatique
    private const EXCLUDED_ENTITIES = [
        AuditLog::class,
        // Ajouter d'autres entités à exclure si nécessaire
    ];

    // Routes à exclure de l'audit automatique
    private const EXCLUDED_ROUTES = [
        '_profiler',
        '_wdt',
        'api_doc',
        'health_check',
        // Ajouter d'autres routes à exclure si nécessaire
    ];

    public function __construct(
        private AuditLoggerService $auditLogger,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private bool $auditDoctrine = true,
        private bool $auditRequests = false, // Désactivé par défaut pour éviter le spam
        private array $auditableEntities = []
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            LoginSuccessEvent::class => ['onLoginSuccess', 0],
            LoginFailureEvent::class => ['onLoginFailure', 0],
            LogoutEvent::class => ['onLogout', 0],
        ];
    }

    /**
     * Écoute les événements de création d'entité
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!$this->auditDoctrine) {
            return;
        }

        $entity = $args->getObject();
        
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        try {
            $newData = $this->extractEntityData($entity);
            
            $this->auditLogger->logEntityChange(
                AuditLoggerService::ACTION_CREATE,
                $entity,
                null,
                $newData,
                "Création de l'entité {$this->getEntityName($entity)}"
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log entity creation', [
                'entity' => $this->getEntityName($entity),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les événements de mise à jour d'entité
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!$this->auditDoctrine) {
            return;
        }

        $entity = $args->getObject();
        
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        try {
            $changeSet = $args->getEntityChangeSet();
            
            if (empty($changeSet)) {
                return; // Aucun changement réel
            }

            $oldData = [];
            $newData = [];
            
            foreach ($changeSet as $field => $changes) {
                $oldData[$field] = $changes[0];
                $newData[$field] = $changes[1];
            }

            // Extraire toutes les données actuelles de l'entité
            $currentData = $this->extractEntityData($entity);
            $newData = array_merge($currentData, $newData);

            $this->auditLogger->logEntityChange(
                AuditLoggerService::ACTION_UPDATE,
                $entity,
                $oldData,
                $newData,
                "Modification de l'entité {$this->getEntityName($entity)}"
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log entity update', [
                'entity' => $this->getEntityName($entity),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les événements de suppression d'entité
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        if (!$this->auditDoctrine) {
            return;
        }

        $entity = $args->getObject();
        
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        try {
            $oldData = $this->extractEntityData($entity);
            
            $this->auditLogger->logEntityChange(
                AuditLoggerService::ACTION_DELETE,
                $entity,
                $oldData,
                null,
                "Suppression de l'entité {$this->getEntityName($entity)}"
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log entity deletion', [
                'entity' => $this->getEntityName($entity),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les événements de requête HTTP
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->auditRequests || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($this->shouldSkipRoute($route)) {
            return;
        }

        try {
            // Log des requêtes sensibles seulement
            if ($this->isSensitiveRoute($route)) {
                $this->auditLogger->log(
                    'http_request',
                    'Request',
                    null,
                    null,
                    [
                        'method' => $request->getMethod(),
                        'path' => $request->getPathInfo(),
                        'route' => $route,
                        'parameters' => $this->sanitizeParameters($request->request->all())
                    ],
                    "Requête HTTP : {$request->getMethod()} {$request->getPathInfo()}",
                    $this->getRequestSeverity($request)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to log HTTP request', [
                'route' => $route,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les événements de réponse HTTP
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->auditRequests || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        if ($this->shouldSkipRoute($route)) {
            return;
        }

        try {
            $statusCode = $response->getStatusCode();
            
            // Log seulement les réponses avec erreurs ou les routes sensibles
            if ($statusCode >= 400 || $this->isSensitiveRoute($route)) {
                $severity = match(true) {
                    $statusCode >= 500 => AuditLoggerService::SEVERITY_CRITICAL,
                    $statusCode >= 400 => AuditLoggerService::SEVERITY_HIGH,
                    $statusCode >= 300 => AuditLoggerService::SEVERITY_MEDIUM,
                    default => AuditLoggerService::SEVERITY_INFO
                };

                $this->auditLogger->log(
                    'http_response',
                    'Response',
                    null,
                    null,
                    [
                        'statusCode' => $statusCode,
                        'method' => $request->getMethod(),
                        'path' => $request->getPathInfo(),
                        'route' => $route
                    ],
                    "Réponse HTTP : {$statusCode} pour {$request->getMethod()} {$request->getPathInfo()}",
                    $severity
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to log HTTP response', [
                'route' => $route,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les exceptions non gérées
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        try {
            $exception = $event->getThrowable();
            $request = $event->getRequest();
            
            $this->auditLogger->log(
                'exception',
                'Exception',
                null,
                null,
                [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'route' => $request->attributes->get('_route'),
                    'method' => $request->getMethod(),
                    'path' => $request->getPathInfo()
                ],
                "Exception non gérée : {$exception->getMessage()}",
                AuditLoggerService::SEVERITY_ERROR
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log exception', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les connexions réussies
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        try {
            $user = $event->getUser();
            $userId = method_exists($user, 'getId') ? $user->getId() : null;
            $userName = $user->getUserIdentifier();

            $this->auditLogger->logLogin($userId, $userName, true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log login success', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les échecs de connexion
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        try {
            $exception = $event->getException();
            $passport = $event->getPassport();
            
            // Essayer d'obtenir le nom d'utilisateur depuis le passport
            $userName = 'unknown';
            if ($passport && method_exists($passport, 'getUser')) {
                $user = $passport->getUser();
                $userName = $user ? $user->getUserIdentifier() : 'unknown';
            }

            $this->auditLogger->logLogin(
                null,
                $userName,
                false,
                $exception->getMessage()
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log login failure', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Écoute les déconnexions
     */
    public function onLogout(LogoutEvent $event): void
    {
        try {
            $token = $event->getToken();
            if ($token) {
                $user = $token->getUser();
                $userId = method_exists($user, 'getId') ? $user->getId() : null;
                $userName = $user->getUserIdentifier();

                $this->auditLogger->logLogout($userId, $userName);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to log logout', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Vérifie si une entité doit être exclue de l'audit
     */
    private function shouldSkipEntity(object $entity): bool
    {
        $entityClass = get_class($entity);
        
        // Exclure les entités de la liste d'exclusion
        if (in_array($entityClass, self::EXCLUDED_ENTITIES)) {
            return true;
        }

        // Si une liste d'entités auditables est définie, n'auditer que celles-ci
        if (!empty($this->auditableEntities) && !in_array($entityClass, $this->auditableEntities)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si une route doit être exclue de l'audit
     */
    private function shouldSkipRoute(?string $route): bool
    {
        if (!$route) {
            return true;
        }

        foreach (self::EXCLUDED_ROUTES as $excludedRoute) {
            if (str_starts_with($route, $excludedRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si une route est sensible et doit être auditée
     */
    private function isSensitiveRoute(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        $sensitivePatterns = [
            'admin',
            'api',
            'login',
            'logout',
            'user',
            'gdpr',
            'audit',
            'loan',
            'scoring',
            'consent'
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains(strtolower($route), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrait les données d'une entité
     */
    private function extractEntityData(object $entity): array
    {
        try {
            // Utiliser la réflexion pour extraire les données
            $reflection = new \ReflectionClass($entity);
            $data = [];

            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($entity);
                
                // Convertir les objets DateTime en chaînes
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_object($value) && method_exists($value, 'getId')) {
                    // Référence d'entité - stocker seulement l'ID
                    $value = $value->getId();
                } elseif (is_object($value)) {
                    // Autres objets - convertir en chaîne si possible
                    $value = method_exists($value, '__toString') ? (string)$value : '[Object]';
                }

                $data[$property->getName()] = $value;
            }

            return $this->sanitizeData($data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract entity data', [
                'entity' => $this->getEntityName($entity),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtient le nom court de l'entité
     */
    private function getEntityName(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    /**
     * Détermine la sévérité d'une requête
     */
    private function getRequestSeverity($request): string
    {
        $method = $request->getMethod();
        $route = $request->attributes->get('_route', '');

        return match(true) {
            str_contains($route, 'delete') || $method === 'DELETE' => AuditLoggerService::SEVERITY_HIGH,
            str_contains($route, 'admin') => AuditLoggerService::SEVERITY_MEDIUM,
            in_array($method, ['POST', 'PUT', 'PATCH']) => AuditLoggerService::SEVERITY_MEDIUM,
            default => AuditLoggerService::SEVERITY_INFO
        };
    }

    /**
     * Assainit les paramètres de requête
     */
    private function sanitizeParameters(array $parameters): array
    {
        return $this->sanitizeData($parameters);
    }

    /**
     * Assainit les données sensibles
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'plainPassword',
            'token',
            'secret',
            'apiKey',
            'creditCard',
            'ssn',
            'socialSecurityNumber',
            'bankAccount',
            'iban',
            'bic'
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), array_map('strtolower', $sensitiveFields))) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }

    /**
     * Active ou désactive l'audit Doctrine
     */
    public function setAuditDoctrine(bool $enabled): void
    {
        $this->auditDoctrine = $enabled;
    }

    /**
     * Active ou désactive l'audit des requêtes
     */
    public function setAuditRequests(bool $enabled): void
    {
        $this->auditRequests = $enabled;
    }

    /**
     * Définit la liste des entités auditables
     */
    public function setAuditableEntities(array $entities): void
    {
        $this->auditableEntities = $entities;
    }
}
