<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Twig\Environment;

/**
 * Global exception handler for better error management and security
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private string $environment
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log the exception with context
        $this->logException($exception, $request);

        // Create appropriate response based on request type
        if ($this->isApiRequest($request)) {
            $response = $this->createApiErrorResponse($exception);
        } else {
            $response = $this->createHtmlErrorResponse($exception);
        }

        $event->setResponse($response);
    }

    private function logException(\Throwable $exception, $request): void
    {
        $context = [
            'url' => $request->getUri(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('referer'),
        ];

        // Don't log sensitive data in production
        if ($this->environment !== 'prod') {
            $context['request_data'] = $this->sanitizeRequestData($request);
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            $this->logger->warning($exception->getMessage(), $context);
        } else {
            $this->logger->error($exception->getMessage(), array_merge($context, [
                'exception' => $exception,
            ]));
        }
    }

    private function isApiRequest($request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/') ||
               $request->headers->get('Content-Type') === 'application/json' ||
               $request->headers->get('Accept') === 'application/json';
    }

    private function createApiErrorResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = 500;
        $error = 'Internal Server Error';
        $message = 'An unexpected error occurred';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $error = Response::$statusTexts[$statusCode] ?? 'Error';
        }

        if ($exception instanceof ValidationFailedException) {
            $statusCode = 400;
            $error = 'Validation Failed';
            $message = 'The provided data is invalid';
        }

        if ($exception instanceof AccessDeniedException) {
            $statusCode = 403;
            $error = 'Access Denied';
            $message = 'You do not have permission to access this resource';
        }

        $data = [
            'error' => $error,
            'message' => $message,
            'status' => $statusCode,
            'timestamp' => date('c'),
        ];

        // Include detailed error info in development
        if ($this->environment === 'dev') {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice($exception->getTrace(), 0, 5), // Limited trace
            ];
        }

        return new JsonResponse($data, $statusCode);
    }

    private function createHtmlErrorResponse(\Throwable $exception): Response
    {
        $statusCode = 500;
        
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        // Use custom error templates
        $templates = [
            404 => 'errors/404.html.twig',
            403 => 'errors/403.html.twig',
            500 => 'errors/500.html.twig',
        ];

        $template = $templates[$statusCode] ?? 'errors/default.html.twig';

        try {
            $content = $this->twig->render($template, [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
                'exception' => $this->environment === 'dev' ? $exception : null,
            ]);

            return new Response($content, $statusCode);
        } catch (\Exception $e) {
            // Fallback to simple response if template rendering fails
            return new Response(
                sprintf('<h1>Error %d</h1><p>%s</p>', $statusCode, Response::$statusTexts[$statusCode] ?? 'Error'),
                $statusCode
            );
        }
    }

    private function sanitizeRequestData($request): array
    {
        $data = $request->request->all();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'auth'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[FILTERED]';
            }
        }

        return $data;
    }
}