<?php

namespace App\EventSubscriber;

use App\Domain\Loan\Event\LoanApplicationCreated;
use App\Domain\Loan\Event\LoanStatusChanged;
use App\Domain\Loan\Event\PaymentProcessed;
use App\Domain\Loan\Event\RiskScoreUpdated;
use App\Service\Notification\NotificationOrchestrator;
use App\Entity\Loan;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Event Subscriber pour les notifications temps réel
 * Écoute les événements de domaine et déclenche les notifications appropriées
 */
class NotificationEventSubscriber implements EventSubscriberInterface
{
    private NotificationOrchestrator $notificationOrchestrator;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        NotificationOrchestrator $notificationOrchestrator,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationOrchestrator = $notificationOrchestrator;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoanApplicationCreated::class => 'onLoanApplicationCreated',
            LoanStatusChanged::class => 'onLoanStatusChanged',
            PaymentProcessed::class => 'onPaymentProcessed',
            RiskScoreUpdated::class => 'onRiskScoreUpdated'
        ];
    }

    /**
     * Gestion de la création d'une demande de prêt
     */
    public function onLoanApplicationCreated(LoanApplicationCreated $event): void
    {
        try {
            $loanId = $event->getLoanId();
            $customerId = $event->getCustomerId();
            
            // Récupération des informations nécessaires
            $loan = $this->entityManager->getRepository(Loan::class)->find($loanId);
            $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
            
            if (!$loan || !$customer) {
                $this->logger->warning('Loan or customer not found for notification', [
                    'loan_id' => $loanId,
                    'customer_id' => $customerId
                ]);
                return;
            }
            
            // Notification de confirmation de réception
            $result = $this->notificationOrchestrator->sendLoanStatusNotification(
                $loanId,
                $customerId,
                $customer->getEmail(),
                $customer->getPhone(),
                'none',
                'submitted',
                'Demande reçue et en cours de traitement'
            );
            
            $this->logger->info('Loan application notification sent', [
                'loan_id' => $loanId,
                'customer_id' => $customerId,
                'notification_success' => $result->isSuccess(),
                'delivered_count' => $result->getDeliveredCount()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan application notification', [
                'loan_id' => $event->getLoanId(),
                'customer_id' => $event->getCustomerId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Gestion des changements de statut de prêt
     */
    public function onLoanStatusChanged(LoanStatusChanged $event): void
    {
        try {
            $loanId = $event->getLoanId();
            $customerId = $event->getCustomerId();
            $previousStatus = $event->getPreviousStatus();
            $newStatus = $event->getNewStatus();
            $reason = $event->getReason();
            
            // Récupération des informations client
            $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
            
            if (!$customer) {
                $this->logger->warning('Customer not found for status change notification', [
                    'loan_id' => $loanId,
                    'customer_id' => $customerId
                ]);
                return;
            }
            
            // Notification de changement de statut
            $result = $this->notificationOrchestrator->sendLoanStatusNotification(
                $loanId,
                $customerId,
                $customer->getEmail(),
                $customer->getPhone(),
                $previousStatus,
                $newStatus,
                $reason
            );
            
            $this->logger->info('Loan status change notification sent', [
                'loan_id' => $loanId,
                'customer_id' => $customerId,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'notification_success' => $result->isSuccess(),
                'delivered_count' => $result->getDeliveredCount()
            ]);
            
            // Notifications spéciales selon le statut
            $this->handleSpecialStatusNotifications($loanId, $customerId, $newStatus, $customer);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send loan status change notification', [
                'loan_id' => $event->getLoanId(),
                'customer_id' => $event->getCustomerId(),
                'new_status' => $event->getNewStatus(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Gestion des notifications de paiement
     */
    public function onPaymentProcessed(PaymentProcessed $event): void
    {
        try {
            $loanId = $event->getLoanId();
            $customerId = $event->getCustomerId();
            $amount = $event->getAmount();
            $paymentType = $event->getPaymentType();
            $status = $event->getStatus();
            
            // Récupération des informations client
            $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
            
            if (!$customer) {
                $this->logger->warning('Customer not found for payment notification', [
                    'loan_id' => $loanId,
                    'customer_id' => $customerId
                ]);
                return;
            }
            
            // Notification de paiement selon le statut
            $recipients = [
                [
                    'type' => 'customer',
                    'id' => $customerId,
                    'email' => $customer->getEmail(),
                    'phone' => $customer->getPhone()
                ]
            ];
            
            $data = [
                'loan_id' => $loanId,
                'amount' => $amount,
                'payment_type' => $paymentType,
                'status' => $status,
                'processed_at' => $event->getProcessedAt()->format('d/m/Y H:i'),
                'payment_reference' => $event->getPaymentReference(),
                'next_payment_due' => $this->getNextPaymentDue($loanId)
            ];
            
            $notificationType = match ($status) {
                'completed' => 'payment_confirmation',
                'failed' => 'payment_failed',
                'pending' => 'payment_pending',
                default => 'payment_update'
            };
            
            $options = [
                'template' => $notificationType,
                'priority' => $status === 'failed' ? 'high' : 'normal',
                'channels' => $status === 'failed' ? ['mercure', 'email', 'sms'] : ['mercure', 'email']
            ];
            
            $result = $this->notificationOrchestrator->sendNotification(
                $notificationType,
                $recipients,
                $data,
                $options
            );
            
            $this->logger->info('Payment notification sent', [
                'loan_id' => $loanId,
                'customer_id' => $customerId,
                'payment_status' => $status,
                'notification_success' => $result->isSuccess(),
                'delivered_count' => $result->getDeliveredCount()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment notification', [
                'loan_id' => $event->getLoanId(),
                'customer_id' => $event->getCustomerId(),
                'payment_status' => $event->getStatus(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Gestion des alertes de risque
     */
    public function onRiskScoreUpdated(RiskScoreUpdated $event): void
    {
        try {
            $loanId = $event->getLoanId();
            $customerId = $event->getCustomerId();
            $newScore = $event->getNewScore();
            $previousScore = $event->getPreviousScore();
            $factors = $event->getFactors();
            
            // Détermination du niveau de risque
            $riskLevel = $this->calculateRiskLevel($newScore);
            
            // Seuil d'alerte - seulement si le risque a augmenté significativement
            if ($newScore > $previousScore + 50 || $riskLevel === 'critical') {
                
                $result = $this->notificationOrchestrator->sendRiskAlert(
                    $loanId,
                    $customerId,
                    $riskLevel,
                    $newScore,
                    $factors
                );
                
                $this->logger->info('Risk alert notification sent', [
                    'loan_id' => $loanId,
                    'customer_id' => $customerId,
                    'risk_level' => $riskLevel,
                    'new_score' => $newScore,
                    'previous_score' => $previousScore,
                    'notification_success' => $result->isSuccess(),
                    'delivered_count' => $result->getDeliveredCount()
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send risk alert notification', [
                'loan_id' => $event->getLoanId(),
                'customer_id' => $event->getCustomerId(),
                'new_score' => $event->getNewScore(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Notifications spéciales selon le statut
     */
    private function handleSpecialStatusNotifications(
        string $loanId,
        string $customerId,
        string $newStatus,
        Customer $customer
    ): void {
        try {
            switch ($newStatus) {
                case 'approved':
                    // Notification spéciale d'approbation avec documents à signer
                    $this->sendApprovalNotification($loanId, $customerId, $customer);
                    break;
                    
                case 'rejected':
                    // Notification de rejet avec explications
                    $this->sendRejectionNotification($loanId, $customerId, $customer);
                    break;
                    
                case 'requires_documents':
                    // Notification de documents manquants
                    $this->sendDocumentRequestNotification($loanId, $customerId, $customer);
                    break;
                    
                case 'funded':
                    // Notification de déblocage des fonds
                    $this->sendFundingNotification($loanId, $customerId, $customer);
                    break;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send special status notification', [
                'loan_id' => $loanId,
                'customer_id' => $customerId,
                'status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendApprovalNotification(string $loanId, string $customerId, Customer $customer): void
    {
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone()
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'customer_name' => $customer->getFullName(),
            'signing_deadline' => (new \DateTimeImmutable('+7 days'))->format('d/m/Y'),
            'document_signing_link' => "/loans/{$loanId}/sign-documents",
            'terms_and_conditions_link' => "/loans/{$loanId}/terms"
        ];
        
        $this->notificationOrchestrator->sendNotification(
            'loan_approved',
            $recipients,
            $data,
            [
                'template' => 'loan_approved',
                'priority' => 'high',
                'channels' => ['mercure', 'email', 'push']
            ]
        );
    }

    private function sendRejectionNotification(string $loanId, string $customerId, Customer $customer): void
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($loanId);
        
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone()
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'customer_name' => $customer->getFullName(),
            'rejection_reason' => $loan?->getRejectionReason() ?? 'Critères non remplis',
            'appeal_deadline' => (new \DateTimeImmutable('+30 days'))->format('d/m/Y'),
            'appeal_link' => "/loans/{$loanId}/appeal",
            'support_contact' => 'support@loanmaster.com'
        ];
        
        $this->notificationOrchestrator->sendNotification(
            'loan_rejected',
            $recipients,
            $data,
            [
                'template' => 'loan_rejected',
                'priority' => 'normal',
                'channels' => ['mercure', 'email']
            ]
        );
    }

    private function sendDocumentRequestNotification(string $loanId, string $customerId, Customer $customer): void
    {
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone()
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'customer_name' => $customer->getFullName(),
            'required_documents' => $this->getRequiredDocuments($loanId),
            'upload_deadline' => (new \DateTimeImmutable('+15 days'))->format('d/m/Y'),
            'upload_link' => "/loans/{$loanId}/upload-documents"
        ];
        
        $this->notificationOrchestrator->sendNotification(
            'documents_required',
            $recipients,
            $data,
            [
                'template' => 'documents_required',
                'priority' => 'high',
                'channels' => ['mercure', 'email', 'sms']
            ]
        );
    }

    private function sendFundingNotification(string $loanId, string $customerId, Customer $customer): void
    {
        $loan = $this->entityManager->getRepository(Loan::class)->find($loanId);
        
        $recipients = [
            [
                'type' => 'customer',
                'id' => $customerId,
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone()
            ]
        ];
        
        $data = [
            'loan_id' => $loanId,
            'customer_name' => $customer->getFullName(),
            'funded_amount' => $loan?->getAmount() ?? 0,
            'first_payment_date' => $this->getFirstPaymentDate($loanId),
            'payment_schedule_link' => "/loans/{$loanId}/payment-schedule",
            'account_access_link' => "/loans/{$loanId}/dashboard"
        ];
        
        $this->notificationOrchestrator->sendNotification(
            'loan_funded',
            $recipients,
            $data,
            [
                'template' => 'loan_funded',
                'priority' => 'high',
                'channels' => ['mercure', 'email', 'push']
            ]
        );
    }

    private function calculateRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 800 => 'critical',
            $score >= 600 => 'high',
            $score >= 400 => 'medium',
            default => 'low'
        };
    }

    private function getNextPaymentDue(string $loanId): ?string
    {
        // Logique pour récupérer la prochaine échéance
        // TODO: Implémenter avec le service de paiement
        return (new \DateTimeImmutable('+1 month'))->format('d/m/Y');
    }

    private function getRequiredDocuments(string $loanId): array
    {
        // TODO: Récupérer les documents manquants depuis la base
        return [
            'Pièce d\'identité',
            'Justificatif de revenus',
            'Relevé bancaire (3 derniers mois)'
        ];
    }

    private function getFirstPaymentDate(string $loanId): string
    {
        // TODO: Calculer la première échéance
        return (new \DateTimeImmutable('+1 month'))->format('d/m/Y');
    }
}
