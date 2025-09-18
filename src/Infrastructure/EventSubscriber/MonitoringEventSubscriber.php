<?php

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Service\MonitoringService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Event Subscriber pour le monitoring et l'audit automatique
 */
class MonitoringEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MonitoringService $monitoringService,
        private LoggerInterface $securityLogger,
        private LoggerInterface $performanceLogger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            LoginSuccessEvent::class => ['onLoginSuccess', 0],
            LoginFailureEvent::class => ['onLoginFailure', 0],
            LogoutEvent::class => ['onLogout', 0],
        ];
    }

    /**
     * Monitoring au début de la requête
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Démarrer le timer de la requête
        $this->monitoringService->startTimer('request', 'http');
        
        // Log de la requête entrante
        $this->performanceLogger->info('HTTP Request started', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('Referer'),
            'request_id' => $request->attributes->get('request_id', uniqid('req_', true)),
            'timestamp' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ]);

        // Incrémenter le compteur de requêtes
        $this->monitoringService->incrementCounter('http.requests.total');
        $this->monitoringService->incrementCounter('http.requests.' . strtolower($request->getMethod()));
        
        // Monitoring spécifique aux routes sensibles
        $route = $request->attributes->get('_route');
        if ($route && $this->isSensitiveRoute($route)) {
            $this->monitoringService->monitorSecurityEvent('sensitive_route_access', [
                'route' => $route,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
        }
    }

    /**
     * Monitoring à la fin de la requête
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        
        // Arrêter le timer de la requête
        $duration = $this->monitoringService->stopTimer('request');
        
        // Log de la réponse
        $this->performanceLogger->info('HTTP Request completed', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'status_code' => $response->getStatusCode(),
            'duration' => $duration,
            'memory_peak' => memory_get_peak_usage(true),
            'request_id' => $request->attributes->get('request_id'),
            'content_length' => $response->headers->get('Content-Length'),
            'cache_control' => $response->headers->get('Cache-Control')
        ]);

        // Métriques par code de statut
        $statusCode = $response->getStatusCode();
        $this->monitoringService->incrementCounter("http.responses.$statusCode");
        
        if ($statusCode >= 400) {
            $this->monitoringService->incrementCounter('http.errors.total');
        }
        
        if ($statusCode >= 500) {
            $this->monitoringService->incrementCounter('http.errors.server');
        }

        // Alert si la requête est lente
        if ($duration > 2000) { // Plus de 2 secondes
            $this->performanceLogger->warning('Slow HTTP request detected', [
                'duration' => $duration,
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'status_code' => $statusCode,
                'request_id' => $request->attributes->get('request_id')
            ]);
        }
    }

    /**
     * Monitoring des exceptions
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        
        // Log de l'exception
        $this->performanceLogger->error('HTTP Request exception', [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'request_id' => $request->attributes->get('request_id'),
            'stack_trace' => $exception->getTraceAsString()
        ]);

        // Métriques d'exceptions
        $this->monitoringService->incrementCounter('http.exceptions.total');
        $this->monitoringService->incrementCounter('http.exceptions.' . $this->getExceptionType($exception));
    }

    /**
     * Monitoring des connexions réussies
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        
        $this->monitoringService->monitorSecurityEvent('login_success', [
            'user_identifier' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'authentication_method' => $event->getAuthenticator()::class
        ], $user->getUserIdentifier());

        $this->securityLogger->info('User login successful', [
            'user_identifier' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => new \DateTime(),
            'request_id' => $request->attributes->get('request_id')
        ]);

        $this->monitoringService->incrementCounter('auth.login.success');
    }

    /**
     * Monitoring des échecs de connexion
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getException();
        
        $this->monitoringService->monitorSecurityEvent('login_failure', [
            'attempted_username' => $request->request->get('_username', 'unknown'),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'failure_reason' => $exception->getMessage()
        ]);

        $this->securityLogger->warning('User login failed', [
            'attempted_username' => $request->request->get('_username', 'unknown'),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'failure_reason' => $exception->getMessage(),
            'timestamp' => new \DateTime(),
            'request_id' => $request->attributes->get('request_id')
        ]);

        $this->monitoringService->incrementCounter('auth.login.failure');
    }

    /**
     * Monitoring des déconnexions
     */
    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $token = $event->getToken();
        
        if ($token && $token->getUser()) {
            $user = $token->getUser();
            
            $this->monitoringService->monitorSecurityEvent('logout', [
                'user_identifier' => $user->getUserIdentifier(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ], $user->getUserIdentifier());

            $this->securityLogger->info('User logout', [
                'user_identifier' => $user->getUserIdentifier(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new \DateTime(),
                'request_id' => $request->attributes->get('request_id')
            ]);
        }

        $this->monitoringService->incrementCounter('auth.logout');
    }

    /**
     * Détermine si une route est sensible
     */
    private function isSensitiveRoute(string $route): bool
    {
        $sensitiveRoutes = [
            'app_login',
            'app_logout',
            'app_register',
            'admin_',
            'loan_create',
            'loan_approve',
            'payment_',
            'api_'
        ];

        foreach ($sensitiveRoutes as $pattern) {
            if (str_starts_with($route, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtient le type d'exception pour les métriques
     */
    private function getExceptionType(\Throwable $exception): string
    {
        $class = get_class($exception);
        $parts = explode('\\', $class);
        return strtolower(end($parts));
    }
}
