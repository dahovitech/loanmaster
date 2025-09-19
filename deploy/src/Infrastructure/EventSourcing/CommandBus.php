<?php

namespace App\Infrastructure\EventSourcing;

use App\Infrastructure\EventSourcing\Command\CommandInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Command Bus pour l'Event Sourcing
 * Gestion centralisée des commandes avec audit et métriques
 */
class CommandBus
{
    private MessageBusInterface $messageBus;
    private EventStore $eventStore;
    private AuditService $auditService;
    private MetricsCollector $metricsCollector;

    public function __construct(
        MessageBusInterface $messageBus,
        EventStore $eventStore,
        AuditService $auditService = null,
        MetricsCollector $metricsCollector = null
    ) {
        $this->messageBus = $messageBus;
        $this->eventStore = $eventStore;
        $this->auditService = $auditService;
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Exécute une commande
     */
    public function dispatch(CommandInterface $command): mixed
    {
        $startTime = microtime(true);
        $commandName = get_class($command);
        
        try {
            // Audit du début de la commande
            $this->auditService?->recordAuditEntry(
                'command',
                $command->getCommandId(),
                'command_started',
                null,
                ['command_type' => $commandName],
                $command->getUserId(),
                $command->getIpAddress(),
                null,
                $command->getCorrelationId()
            );
            
            // Exécution de la commande
            $envelope = $this->messageBus->dispatch($command);
            
            // Récupération du résultat
            $handledStamp = $envelope->last(HandledStamp::class);
            $result = $handledStamp?->getResult();
            
            // Métriques de performance
            $executionTime = microtime(true) - $startTime;
            $this->metricsCollector?->recordMetric(
                'command_execution_time',
                $executionTime,
                ['command_type' => $commandName, 'status' => 'success']
            );
            
            // Audit de la réussite
            $this->auditService?->recordAuditEntry(
                'command',
                $command->getCommandId(),
                'command_completed',
                null,
                [
                    'command_type' => $commandName,
                    'execution_time' => $executionTime,
                    'result_type' => $result ? get_class($result) : null
                ],
                $command->getUserId(),
                $command->getIpAddress(),
                null,
                $command->getCorrelationId()
            );
            
            return $result;
            
        } catch (HandlerFailedException $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Métriques d'échec
            $this->metricsCollector?->recordMetric(
                'command_execution_time',
                $executionTime,
                ['command_type' => $commandName, 'status' => 'failed']
            );
            
            // Audit de l'échec
            $this->auditService?->recordAuditEntry(
                'command',
                $command->getCommandId(),
                'command_failed',
                null,
                [
                    'command_type' => $commandName,
                    'execution_time' => $executionTime,
                    'error' => $e->getMessage(),
                    'stack_trace' => $e->getTraceAsString()
                ],
                $command->getUserId(),
                $command->getIpAddress(),
                null,
                $command->getCorrelationId()
            );
            
            throw $e;
        }
    }

    /**
     * Vérifie si un handler existe pour une commande
     */
    public function canHandle(CommandInterface $command): bool
    {
        try {
            // Tentative de dispatch en mode "dry run"
            $this->messageBus->dispatch($command);
            return true;
        } catch (HandlerFailedException $e) {
            return false;
        }
    }
}
