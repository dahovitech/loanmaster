<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Middleware;

use App\Infrastructure\Service\AuditLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware pour l'audit des messages Messenger
 */
class AuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LoggerInterface $logger
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageClass = get_class($message);
        $isReceived = $envelope->last(ReceivedStamp::class) !== null;
        
        // Log au début du traitement
        if ($isReceived) {
            $this->logger->info('Message processing started', [
                'message_class' => $messageClass,
                'message_id' => $this->getMessageId($envelope),
                'bus' => $this->getBusName($envelope)
            ]);
        }

        $startTime = microtime(true);
        
        try {
            // Traitement du message
            $envelope = $stack->next()->handle($envelope, $stack);
            
            // Log de succès
            if ($isReceived) {
                $duration = (microtime(true) - $startTime) * 1000; // en millisecondes
                
                $this->logger->info('Message processed successfully', [
                    'message_class' => $messageClass,
                    'message_id' => $this->getMessageId($envelope),
                    'duration_ms' => round($duration, 2)
                ]);

                // Audit log pour les messages critiques
                if ($this->isCriticalMessage($messageClass)) {
                    $this->auditLogService->logAdminAction(
                        'critical_message_processed',
                        0, // ID système
                        [
                            'message_class' => $messageClass,
                            'duration_ms' => round($duration, 2),
                            'success' => true
                        ]
                    );
                }
            }
            
            return $envelope;
            
        } catch (\Throwable $exception) {
            // Log d'erreur
            if ($isReceived) {
                $duration = (microtime(true) - $startTime) * 1000;
                
                $this->logger->error('Message processing failed', [
                    'message_class' => $messageClass,
                    'message_id' => $this->getMessageId($envelope),
                    'duration_ms' => round($duration, 2),
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString()
                ]);

                // Audit log pour les échecs critiques
                if ($this->isCriticalMessage($messageClass)) {
                    $this->auditLogService->logAdminAction(
                        'critical_message_failed',
                        0, // ID système
                        [
                            'message_class' => $messageClass,
                            'duration_ms' => round($duration, 2),
                            'error' => $exception->getMessage(),
                            'success' => false
                        ]
                    );
                }
            }
            
            throw $exception;
        }
    }

    private function getMessageId(Envelope $envelope): string
    {
        // Essayer de récupérer un ID unique du message
        $stamps = $envelope->all();
        
        foreach ($stamps as $stampClass => $stampInstances) {
            if (method_exists($stampInstances[0], 'getId')) {
                return $stampInstances[0]->getId();
            }
        }
        
        // Fallback: utiliser un hash du message
        return substr(md5(serialize($envelope->getMessage())), 0, 8);
    }

    private function getBusName(Envelope $envelope): string
    {
        $busNameStamp = $envelope->last(BusNameStamp::class);
        return $busNameStamp ? $busNameStamp->getBusName() : 'default';
    }

    private function isCriticalMessage(string $messageClass): bool
    {
        $criticalMessages = [
            'App\\Application\\Message\\LoanApprovalMessage',
            'App\\Application\\Message\\LoanDisbursementMessage',
            'App\\Application\\Message\\PaymentProcessingMessage',
            'App\\Application\\Message\\KycVerificationMessage'
        ];
        
        return in_array($messageClass, $criticalMessages, true);
    }
}
