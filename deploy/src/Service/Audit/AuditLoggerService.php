<?php

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class AuditLoggerService
{
    // Niveaux de sévérité
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_ERROR = 'error';

    // Actions d'audit courantes
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_EXPORT = 'export';
    public const ACTION_IMPORT = 'import';
    public const ACTION_CONSENT_GRANTED = 'consent_granted';
    public const ACTION_CONSENT_WITHDRAWN = 'consent_withdrawn';
    public const ACTION_DATA_ANONYMIZED = 'data_anonymized';
    public const ACTION_DATA_EXPORTED = 'data_exported';
    public const ACTION_GDPR_REQUEST = 'gdpr_request';
    public const ACTION_LOAN_APPLIED = 'loan_applied';
    public const ACTION_LOAN_APPROVED = 'loan_approved';
    public const ACTION_LOAN_REJECTED = 'loan_rejected';
    public const ACTION_PAYMENT_PROCESSED = 'payment_processed';
    public const ACTION_SCORE_CALCULATED = 'score_calculated';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private RequestStack $requestStack,
        private Security $security,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private bool $auditEnabled = true
    ) {}

    /**
     * Log une action d'audit
     */
    public function log(
        string $action,
        string $entityType = null,
        string $entityId = null,
        array $oldData = null,
        array $newData = null,
        string $description = null,
        string $severity = self::SEVERITY_INFO,
        array $metadata = null
    ): ?AuditLog {
        if (!$this->auditEnabled) {
            return null;
        }

        try {
            $auditLog = new AuditLog();
            
            // Données de base
            $auditLog->setAction($action)
                    ->setSeverity($severity)
                    ->setDescription($description);

            // Données d'entité
            if ($entityType) {
                $auditLog->setEntityType($entityType);
            }
            if ($entityId) {
                $auditLog->setEntityId($entityId);
            }

            // Données de changement
            if ($oldData) {
                $auditLog->setOldData($this->sanitizeData($oldData));
            }
            if ($newData) {
                $auditLog->setNewData($this->sanitizeData($newData));
            }

            // Calcul des champs modifiés
            if ($oldData && $newData) {
                $changedFields = $this->getChangedFields($oldData, $newData);
                $auditLog->setChangedFields($changedFields);
            }

            // Informations utilisateur
            $this->setUserInfo($auditLog);

            // Informations de requête
            $this->setRequestInfo($auditLog);

            // Métadonnées
            if ($metadata) {
                $auditLog->setMetadata($metadata);
            }

            // Sauvegarde
            $this->auditLogRepository->save($auditLog, true);

            return $auditLog;

        } catch (\Exception $e) {
            // En cas d'erreur, on log dans les logs système mais on ne fait pas échouer l'opération
            $this->logger->error('Failed to create audit log', [
                'action' => $action,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Log un événement de connexion
     */
    public function logLogin(int $userId, string $userName, bool $success = true, string $reason = null): ?AuditLog
    {
        $action = $success ? self::ACTION_LOGIN : self::ACTION_LOGIN_FAILED;
        $severity = $success ? self::SEVERITY_INFO : self::SEVERITY_HIGH;
        
        $description = $success 
            ? "Connexion réussie pour l'utilisateur {$userName}" 
            : "Échec de connexion pour l'utilisateur {$userName}" . ($reason ? " : {$reason}" : "");

        $metadata = [
            'userId' => $userId,
            'userName' => $userName,
            'success' => $success
        ];

        if ($reason) {
            $metadata['reason'] = $reason;
        }

        return $this->log($action, 'User', (string)$userId, null, null, $description, $severity, $metadata);
    }

    /**
     * Log un événement de déconnexion
     */
    public function logLogout(int $userId, string $userName): ?AuditLog
    {
        return $this->log(
            self::ACTION_LOGOUT,
            'User',
            (string)$userId,
            null,
            null,
            "Déconnexion de l'utilisateur {$userName}",
            self::SEVERITY_INFO,
            ['userId' => $userId, 'userName' => $userName]
        );
    }

    /**
     * Log une modification d'entité
     */
    public function logEntityChange(
        string $action,
        object $entity,
        array $oldData = null,
        array $newData = null,
        string $description = null
    ): ?AuditLog {
        $entityType = $this->getEntityType($entity);
        $entityId = $this->getEntityId($entity);
        
        if (!$description) {
            $description = $this->generateChangeDescription($action, $entityType, $entityId);
        }

        $severity = match($action) {
            self::ACTION_DELETE => self::SEVERITY_HIGH,
            self::ACTION_CREATE, self::ACTION_UPDATE => self::SEVERITY_MEDIUM,
            default => self::SEVERITY_INFO
        };

        return $this->log($action, $entityType, $entityId, $oldData, $newData, $description, $severity);
    }

    /**
     * Log un événement RGPD
     */
    public function logGdprEvent(
        string $action,
        int $userId,
        string $consentType = null,
        array $data = null,
        string $description = null
    ): ?AuditLog {
        if (!$description) {
            $description = $this->generateGdprDescription($action, $consentType);
        }

        $metadata = [
            'gdpr' => true,
            'consentType' => $consentType,
            'dataSubjectId' => $userId
        ];

        $gdprData = [
            'action' => $action,
            'dataSubjectId' => $userId,
            'consentType' => $consentType,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ];

        $auditLog = $this->log(
            $action,
            'GDPR',
            (string)$userId,
            null,
            $data,
            $description,
            self::SEVERITY_HIGH,
            $metadata
        );

        if ($auditLog) {
            $auditLog->setGdprData($gdprData);
            $this->entityManager->flush();
        }

        return $auditLog;
    }

    /**
     * Log une demande d'export de données (RGPD)
     */
    public function logDataExport(int $userId, array $exportedData): ?AuditLog
    {
        return $this->logGdprEvent(
            self::ACTION_DATA_EXPORTED,
            $userId,
            null,
            ['exportedFields' => array_keys($exportedData)],
            "Export des données personnelles pour l'utilisateur ID {$userId}"
        );
    }

    /**
     * Log une anonymisation de données (RGPD)
     */
    public function logDataAnonymization(int $userId, array $anonymizedFields): ?AuditLog
    {
        return $this->logGdprEvent(
            self::ACTION_DATA_ANONYMIZED,
            $userId,
            null,
            ['anonymizedFields' => $anonymizedFields],
            "Anonymisation des données pour l'utilisateur ID {$userId}"
        );
    }

    /**
     * Log un calcul de score
     */
    public function logScoreCalculation(int $loanApplicationId, float $score, string $model, array $features = null): ?AuditLog
    {
        $metadata = [
            'score' => $score,
            'model' => $model,
            'loanApplicationId' => $loanApplicationId
        ];

        if ($features) {
            $metadata['features'] = $features;
        }

        return $this->log(
            self::ACTION_SCORE_CALCULATED,
            'LoanApplication',
            (string)$loanApplicationId,
            null,
            ['score' => $score, 'model' => $model],
            "Calcul de score automatique : {$score} (modèle: {$model})",
            self::SEVERITY_MEDIUM,
            $metadata
        );
    }

    /**
     * Définit les informations utilisateur
     */
    private function setUserInfo(AuditLog $auditLog): void
    {
        $user = $this->security->getUser();
        if ($user) {
            $auditLog->setUserId($user->getId() ?? null)
                    ->setUserName($user->getUserIdentifier() ?? null);
        }
    }

    /**
     * Définit les informations de requête
     */
    private function setRequestInfo(AuditLog $auditLog): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp())
                    ->setUserAgent($request->headers->get('User-Agent'))
                    ->setRoute($request->attributes->get('_route'))
                    ->setHttpMethod($request->getMethod())
                    ->setSessionId($request->getSession()?->getId());
        }
    }

    /**
     * Obtient le type d'entité
     */
    private function getEntityType(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    /**
     * Obtient l'ID d'entité
     */
    private function getEntityId(object $entity): ?string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            return $id !== null ? (string)$id : null;
        }
        return null;
    }

    /**
     * Calcule les champs modifiés entre deux jeux de données
     */
    private function getChangedFields(array $oldData, array $newData): array
    {
        $changedFields = [];
        
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changedFields[] = $key;
            }
        }

        // Vérifier les champs supprimés
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changedFields[] = $key;
            }
        }

        return array_unique($changedFields);
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
            'bankAccount'
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
     * Génère une description pour un changement d'entité
     */
    private function generateChangeDescription(string $action, string $entityType, ?string $entityId): string
    {
        $actionLabels = [
            self::ACTION_CREATE => 'Création',
            self::ACTION_UPDATE => 'Modification',
            self::ACTION_DELETE => 'Suppression',
            self::ACTION_VIEW => 'Consultation'
        ];

        $actionLabel = $actionLabels[$action] ?? $action;
        $entityIdPart = $entityId ? " (ID: {$entityId})" : "";

        return "{$actionLabel} de l'entité {$entityType}{$entityIdPart}";
    }

    /**
     * Génère une description pour un événement RGPD
     */
    private function generateGdprDescription(string $action, ?string $consentType): string
    {
        $descriptions = [
            self::ACTION_CONSENT_GRANTED => 'Consentement accordé',
            self::ACTION_CONSENT_WITHDRAWN => 'Consentement retiré',
            self::ACTION_DATA_ANONYMIZED => 'Données anonymisées',
            self::ACTION_DATA_EXPORTED => 'Export de données personnelles',
            self::ACTION_GDPR_REQUEST => 'Demande RGPD'
        ];

        $description = $descriptions[$action] ?? $action;
        
        if ($consentType) {
            $description .= " pour le type : {$consentType}";
        }

        return $description;
    }

    /**
     * Active ou désactive l'audit
     */
    public function setAuditEnabled(bool $enabled): void
    {
        $this->auditEnabled = $enabled;
    }

    /**
     * Vérifie si l'audit est activé
     */
    public function isAuditEnabled(): bool
    {
        return $this->auditEnabled;
    }

    /**
     * Log multiple avec transaction
     */
    public function logBatch(array $logData): array
    {
        $auditLogs = [];
        
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($logData as $data) {
                $auditLog = $this->log(
                    $data['action'],
                    $data['entityType'] ?? null,
                    $data['entityId'] ?? null,
                    $data['oldData'] ?? null,
                    $data['newData'] ?? null,
                    $data['description'] ?? null,
                    $data['severity'] ?? self::SEVERITY_INFO,
                    $data['metadata'] ?? null
                );
                
                if ($auditLog) {
                    $auditLogs[] = $auditLog;
                }
            }
            
            $this->entityManager->commit();
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to create batch audit logs', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $auditLogs;
    }

    /**
     * Nettoie les anciens logs d'audit
     */
    public function cleanup(int $retentionDays = 365): int
    {
        return $this->auditLogRepository->deleteOldLogs($retentionDays);
    }
}
