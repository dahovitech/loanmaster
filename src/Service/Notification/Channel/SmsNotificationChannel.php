<?php

namespace App\Service\Notification\Channel;

use App\Service\Notification\NotificationResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Canal de notification SMS
 */
class SmsNotificationChannel implements ChannelInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private array $configuration;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        array $configuration = []
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->configuration = array_merge([
            'provider' => 'twilio',
            'api_key' => $_ENV['SMS_API_KEY'] ?? '',
            'api_secret' => $_ENV['SMS_API_SECRET'] ?? '',
            'from' => $_ENV['SMS_FROM_NUMBER'] ?? '+33123456789',
            'max_length' => 160,
            'enabled' => $_ENV['SMS_ENABLED'] ?? false
        ], $configuration);
    }

    public function send(string $type, array $recipients, array $data, array $options = []): NotificationResult
    {
        $startTime = microtime(true);
        $deliveredCount = 0;
        $failedCount = 0;
        $errors = [];

        if (!$this->isAvailable()) {
            return new NotificationResult(
                uniqid('sms_', true),
                false,
                0,
                count($recipients),
                ['channel' => 'sms', 'error' => 'SMS service not available'],
                microtime(true) - $startTime,
                ['SMS service not configured or disabled']
            );
        }

        try {
            // Formatage du message
            $message = $this->formatMessage($type, $data, $options);
            
            foreach ($recipients as $recipient) {
                $phoneNumber = $this->extractPhoneNumber($recipient);
                
                if (!$phoneNumber) {
                    $failedCount++;
                    $errors[] = "No phone number for recipient: " . json_encode($recipient);
                    continue;
                }
                
                try {
                    $success = $this->sendSms($phoneNumber, $message, $options);
                    
                    if ($success) {
                        $deliveredCount++;
                        $this->logger->info('SMS sent successfully', [
                            'phone' => $this->maskPhoneNumber($phoneNumber),
                            'type' => $type
                        ]);
                    } else {
                        $failedCount++;
                        $errors[] = "Failed to send SMS to {$this->maskPhoneNumber($phoneNumber)}";
                    }
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "SMS error for {$this->maskPhoneNumber($phoneNumber)}: {$e->getMessage()}";
                    
                    $this->logger->error('SMS sending failed', [
                        'phone' => $this->maskPhoneNumber($phoneNumber),
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            $failedCount = count($recipients);
            $errors[] = $e->getMessage();
            
            $this->logger->error('SMS channel general error', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $executionTime = microtime(true) - $startTime;

        return new NotificationResult(
            uniqid('sms_', true),
            $deliveredCount > 0,
            $deliveredCount,
            $failedCount,
            [
                'channel' => 'sms',
                'message_length' => strlen($message ?? ''),
                'execution_time' => $executionTime
            ],
            $executionTime,
            $errors
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->configuration['enabled']) && 
               !empty($this->configuration['api_key']) && 
               !empty($this->configuration['api_secret']);
    }

    public function getName(): string
    {
        return 'sms';
    }

    public function getConfiguration(): array
    {
        // Masquer les clés sensibles
        $config = $this->configuration;
        if (isset($config['api_key'])) {
            $config['api_key'] = '***masked***';
        }
        if (isset($config['api_secret'])) {
            $config['api_secret'] = '***masked***';
        }
        return $config;
    }

    public function validateData(array $data): bool
    {
        // Vérifier qu'on peut créer un message
        try {
            $message = $this->formatMessage('test', $data, []);
            return strlen($message) <= $this->configuration['max_length'];
        } catch (\Exception $e) {
            return false;
        }
    }

    public function formatData(array $data, array $options = []): array
    {
        return $data; // SMS utilise formatMessage au lieu de formatData
    }

    /**
     * Formate le message SMS selon le type de notification
     */
    private function formatMessage(string $type, array $data, array $options): string
    {
        $message = match ($type) {
            'loan_status_update' => $this->formatLoanStatusMessage($data),
            'risk_alert' => $this->formatRiskAlertMessage($data),
            'payment_reminder' => $this->formatPaymentReminderMessage($data),
            'audit_alert' => $this->formatAuditAlertMessage($data),
            default => $this->formatGenericMessage($data)
        };

        // Truncate si nécessaire
        if (strlen($message) > $this->configuration['max_length']) {
            $message = substr($message, 0, $this->configuration['max_length'] - 3) . '...';
        }

        return $message;
    }

    private function formatLoanStatusMessage(array $data): string
    {
        $status = $data['status_display'] ?? $data['new_status'] ?? 'mis à jour';
        return "LoanMaster: Votre demande de prêt #{$data['loan_id']} est maintenant '{$status}'. Consultez votre espace client pour plus d'infos.";
    }

    private function formatRiskAlertMessage(array $data): string
    {
        return "ALERTE RISQUE: Prêt #{$data['loan_id']} - Niveau {$data['risk_level']} (Score: {$data['risk_score']}). Action requise.";
    }

    private function formatPaymentReminderMessage(array $data): string
    {
        $amount = number_format($data['amount'], 2, ',', ' ');
        $dueDate = $data['due_date'] ?? 'bientôt';
        
        if ($data['is_overdue'] ?? false) {
            return "URGENT: Paiement en retard de {$amount}€ pour le prêt #{$data['loan_id']}. Contactez-nous rapidement.";
        }
        
        return "Rappel: Paiement de {$amount}€ dû le {$dueDate} pour votre prêt #{$data['loan_id']}.";
    }

    private function formatAuditAlertMessage(array $data): string
    {
        return "AUDIT: {$data['severity']} - {$data['event_type']} détecté sur {$data['entity_type']} #{$data['entity_id']}.";
    }

    private function formatGenericMessage(array $data): string
    {
        return $data['message'] ?? 'Notification LoanMaster - Consultez votre espace client.';
    }

    /**
     * Extrait le numéro de téléphone du recipient
     */
    private function extractPhoneNumber($recipient): ?string
    {
        if (is_string($recipient)) {
            // Format simple: numéro de téléphone direct
            return $this->validatePhoneNumber($recipient) ? $recipient : null;
        }
        
        if (is_array($recipient)) {
            $phone = $recipient['phone'] ?? $recipient['mobile'] ?? $recipient['telephone'] ?? null;
            return $phone && $this->validatePhoneNumber($phone) ? $phone : null;
        }
        
        return null;
    }

    /**
     * Valide le format du numéro de téléphone
     */
    private function validatePhoneNumber(string $phone): bool
    {
        // Format international simple
        return preg_match('/^\+?[1-9]\d{7,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone));
    }

    /**
     * Masque le numéro de téléphone pour les logs
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) < 4) {
            return '***';
        }
        
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }

    /**
     * Envoie le SMS via l'API
     */
    private function sendSms(string $phoneNumber, string $message, array $options): bool
    {
        if ($this->configuration['provider'] === 'twilio') {
            return $this->sendViaTwilio($phoneNumber, $message, $options);
        }
        
        // Simulation pour développement
        if ($_ENV['APP_ENV'] === 'dev') {
            $this->logger->info('SMS simulation (dev mode)', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'message' => $message
            ]);
            return true;
        }
        
        throw new \Exception("SMS provider '{$this->configuration['provider']}' not implemented");
    }

    /**
     * Envoie SMS via Twilio
     */
    private function sendViaTwilio(string $phoneNumber, string $message, array $options): bool
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . $this->configuration['api_key'] . '/Messages.json', [
                'auth_basic' => [$this->configuration['api_key'], $this->configuration['api_secret']],
                'body' => [
                    'From' => $this->configuration['from'],
                    'To' => $phoneNumber,
                    'Body' => $message
                ]
            ]);
            
            return $response->getStatusCode() === 201;
            
        } catch (\Exception $e) {
            $this->logger->error('Twilio SMS API error', [
                'error' => $e->getMessage(),
                'phone' => $this->maskPhoneNumber($phoneNumber)
            ]);
            
            return false;
        }
    }
}
