<?php

namespace App\Service\Security;

use Psr\Log\LoggerInterface;

/**
 * Secure logging service that filters sensitive data
 */
class SecureLoggerService
{
    private const SENSITIVE_FIELDS = [
        'password',
        'token',
        'secret',
        'key',
        'auth',
        'credential',
        'session',
        'cookie',
        'api_key',
        'private_key',
        'access_token',
        'refresh_token',
        'authorization',
        'x-api-key',
        'ssn',
        'social_security',
        'credit_card',
        'card_number',
        'cvv',
        'pin',
        'bank_account',
        'iban',
        'swift'
    ];

    private const SENSITIVE_PATTERNS = [
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',  // Credit card numbers
        '/\b\d{3}-\d{2}-\d{4}\b/',                        // SSN format
        '/\b[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}([A-Z0-9]?){0,16}\b/', // IBAN
        '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/',           // Bearer tokens
        '/Basic\s+[A-Za-z0-9\+\/]+=*/',                  // Basic auth
    ];

    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Log emergency with sanitized data
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $this->sanitizeContext($context));
    }

    /**
     * Log alert with sanitized data
     */
    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $this->sanitizeContext($context));
    }

    /**
     * Log critical with sanitized data
     */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $this->sanitizeContext($context));
    }

    /**
     * Log error with sanitized data
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->sanitizeContext($context));
    }

    /**
     * Log warning with sanitized data
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->sanitizeContext($context));
    }

    /**
     * Log notice with sanitized data
     */
    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $this->sanitizeContext($context));
    }

    /**
     * Log info with sanitized data
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->sanitizeContext($context));
    }

    /**
     * Log debug with sanitized data
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->sanitizeContext($context));
    }

    /**
     * Log user action with automatic context sanitization
     */
    public function logUserAction(string $action, array $userData = [], array $context = []): void
    {
        $sanitizedContext = array_merge($context, [
            'action' => $action,
            'user_data' => $this->sanitizeUserData($userData),
            'timestamp' => date('c'),
        ]);

        $this->info("User action: {$action}", $sanitizedContext);
    }

    /**
     * Log security event with enhanced context
     */
    public function logSecurityEvent(string $event, string $severity = 'warning', array $context = []): void
    {
        $securityContext = array_merge($context, [
            'event_type' => 'security',
            'severity' => $severity,
            'timestamp' => date('c'),
        ]);

        match ($severity) {
            'critical' => $this->critical("Security event: {$event}", $securityContext),
            'error' => $this->error("Security event: {$event}", $securityContext),
            'warning' => $this->warning("Security event: {$event}", $securityContext),
            default => $this->info("Security event: {$event}", $securityContext),
        };
    }

    /**
     * Sanitize context data by removing or masking sensitive information
     */
    private function sanitizeContext(array $context): array
    {
        return $this->sanitizeArray($context);
    }

    /**
     * Recursively sanitize array data
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if key is sensitive
            if ($this->isSensitiveField($lowerKey)) {
                $sanitized[$key] = $this->maskSensitiveValue($value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = $this->sanitizeObject($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize object by converting to array first
     */
    private function sanitizeObject(object $object): array
    {
        // Convert object to array, excluding sensitive methods/properties
        if (method_exists($object, 'toArray')) {
            return $this->sanitizeArray($object->toArray());
        }

        // For other objects, just log the class name
        return ['__class' => get_class($object)];
    }

    /**
     * Sanitize string content by removing sensitive patterns
     */
    private function sanitizeString(string $value): string
    {
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '[FILTERED]', $value);
        }

        return $value;
    }

    /**
     * Check if field name is sensitive
     */
    private function isSensitiveField(string $fieldName): bool
    {
        foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
            if (str_contains($fieldName, $sensitiveField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask sensitive value
     */
    private function maskSensitiveValue(mixed $value): string
    {
        if (is_string($value)) {
            $length = strlen($value);
            if ($length <= 4) {
                return '[FILTERED]';
            }
            return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
        }

        return '[FILTERED]';
    }

    /**
     * Sanitize user-specific data
     */
    private function sanitizeUserData(array $userData): array
    {
        $sanitized = $this->sanitizeArray($userData);

        // Additional user-specific sanitization
        if (isset($sanitized['email'])) {
            $sanitized['email'] = $this->maskEmail($sanitized['email']);
        }

        if (isset($sanitized['phone'])) {
            $sanitized['phone'] = $this->maskPhone($sanitized['phone']);
        }

        return $sanitized;
    }

    /**
     * Mask email address
     */
    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '[INVALID_EMAIL]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLength = strlen($local);

        if ($localLength <= 2) {
            $maskedLocal = str_repeat('*', $localLength);
        } else {
            $maskedLocal = $local[0] . str_repeat('*', $localLength - 2) . $local[-1];
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask phone number
     */
    private function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);
        $length = strlen($cleaned);

        if ($length < 4) {
            return str_repeat('*', $length);
        }

        return substr($cleaned, 0, 2) . str_repeat('*', $length - 4) . substr($cleaned, -2);
    }
}