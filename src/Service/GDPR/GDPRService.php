<?php

namespace App\Service\GDPR;

use App\Entity\UserConsent;
use App\Repository\UserConsentRepository;
use App\Service\Audit\AuditLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

class GDPRService
{
    // Bases légales RGPD
    public const LEGAL_BASIS_CONSENT = 'consent';
    public const LEGAL_BASIS_CONTRACT = 'contract';
    public const LEGAL_BASIS_LEGAL_OBLIGATION = 'legal_obligation';
    public const LEGAL_BASIS_VITAL_INTERESTS = 'vital_interests';
    public const LEGAL_BASIS_PUBLIC_TASK = 'public_task';
    public const LEGAL_BASIS_LEGITIMATE_INTERESTS = 'legitimate_interests';

    // Durées de conservation par défaut (en jours)
    public const RETENTION_DEFAULT = 2555; // ~7 ans
    public const RETENTION_MARKETING = 1095; // 3 ans
    public const RETENTION_ANALYTICS = 730; // 2 ans
    public const RETENTION_COOKIES = 365; // 1 an

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserConsentRepository $userConsentRepository,
        private AuditLoggerService $auditLogger,
        private RequestStack $requestStack,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
        private array $requiredConsents = []
    ) {}

    /**
     * Vérifie si un utilisateur a donné un consentement valide
     */
    public function hasValidConsent(int $userId, string $consentType): bool
    {
        return $this->userConsentRepository->hasValidConsent($userId, $consentType);
    }

    /**
     * Enregistre un consentement utilisateur
     */
    public function grantConsent(
        int $userId,
        string $consentType,
        string $consentText = null,
        string $version = '1.0',
        int $durationDays = null,
        string $locale = 'fr',
        string $legalBasis = self::LEGAL_BASIS_CONSENT
    ): UserConsent {
        // Vérifier si un consentement existe déjà
        $existingConsent = $this->userConsentRepository->findUserConsent($userId, $consentType);
        
        if ($existingConsent) {
            // Mettre à jour le consentement existant
            $existingConsent->grant(
                $this->getClientIp(),
                $this->getUserAgent()
            );
            $existingConsent->setVersion($version)
                           ->setLocale($locale)
                           ->setLegalBasis($legalBasis);
            
            if ($consentText) {
                $existingConsent->setConsentText($consentText);
            }
            
            if ($durationDays) {
                $expiresAt = new \DateTimeImmutable('+' . $durationDays . ' days');
                $existingConsent->setExpiresAt($expiresAt);
            }

            $consent = $existingConsent;
        } else {
            // Créer un nouveau consentement
            $consent = new UserConsent();
            $consent->setUserId($userId)
                   ->setConsentType($consentType)
                   ->setVersion($version)
                   ->setLocale($locale)
                   ->setLegalBasis($legalBasis);
            
            if ($consentText) {
                $consent->setConsentText($consentText);
            }
            
            if ($durationDays) {
                $expiresAt = new \DateTimeImmutable('+' . $durationDays . ' days');
                $consent->setExpiresAt($expiresAt);
            }

            $consent->grant($this->getClientIp(), $this->getUserAgent());
        }

        // Sauvegarder
        $this->userConsentRepository->save($consent, true);

        // Audit trail
        $this->auditLogger->logGdprEvent(
            AuditLoggerService::ACTION_CONSENT_GRANTED,
            $userId,
            $consentType,
            null,
            "Consentement accordé pour {$consentType}"
        );

        return $consent;
    }

    /**
     * Retire un consentement utilisateur
     */
    public function withdrawConsent(int $userId, string $consentType, string $reason = null): ?UserConsent
    {
        $consent = $this->userConsentRepository->findUserConsent($userId, $consentType);
        
        if (!$consent) {
            return null;
        }

        $consent->withdraw($reason, $this->getClientIp(), $this->getUserAgent());
        $this->entityManager->flush();

        // Audit trail
        $this->auditLogger->logGdprEvent(
            AuditLoggerService::ACTION_CONSENT_WITHDRAWN,
            $userId,
            $consentType,
            ['reason' => $reason],
            "Consentement retiré pour {$consentType}" . ($reason ? " : {$reason}" : "")
        );

        return $consent;
    }

    /**
     * Refuse un consentement utilisateur
     */
    public function denyConsent(int $userId, string $consentType): UserConsent
    {
        // Vérifier si un consentement existe déjà
        $existingConsent = $this->userConsentRepository->findUserConsent($userId, $consentType);
        
        if ($existingConsent) {
            $existingConsent->deny($this->getClientIp(), $this->getUserAgent());
            $consent = $existingConsent;
        } else {
            // Créer un nouveau consentement refusé
            $consent = new UserConsent();
            $consent->setUserId($userId)
                   ->setConsentType($consentType)
                   ->deny($this->getClientIp(), $this->getUserAgent());
        }

        $this->userConsentRepository->save($consent, true);

        return $consent;
    }

    /**
     * Obtient tous les consentements d'un utilisateur
     */
    public function getUserConsents(int $userId): array
    {
        return $this->userConsentRepository->findByUser($userId);
    }

    /**
     * Obtient les consentements valides d'un utilisateur
     */
    public function getValidUserConsents(int $userId): array
    {
        $consents = $this->userConsentRepository->findGrantedConsents($userId);
        return array_filter($consents, fn($consent) => $consent->isValid());
    }

    /**
     * Vérifie la conformité RGPD d'un utilisateur
     */
    public function checkUserCompliance(int $userId): array
    {
        $userConsents = $this->getUserConsents($userId);
        $validConsents = $this->getValidUserConsents($userId);
        
        $consentTypes = array_map(fn($consent) => $consent->getConsentType(), $validConsents);
        $missingConsents = array_diff($this->requiredConsents, $consentTypes);
        
        $expiringConsents = array_filter($validConsents, function($consent) {
            $daysUntilExpiry = $consent->getDaysUntilExpiry();
            return $daysUntilExpiry !== null && $daysUntilExpiry <= 30;
        });

        return [
            'userId' => $userId,
            'totalConsents' => count($userConsents),
            'validConsents' => count($validConsents),
            'missingConsents' => $missingConsents,
            'expiringConsents' => count($expiringConsents),
            'isCompliant' => empty($missingConsents),
            'complianceScore' => $this->calculateComplianceScore($userId),
            'lastConsentUpdate' => $this->getLastConsentUpdateDate($userConsents),
        ];
    }

    /**
     * Export des données personnelles d'un utilisateur (Droit d'accès RGPD)
     */
    public function exportUserData(int $userId, array $entityTypes = []): array
    {
        $exportedData = [];
        
        try {
            // TODO: Implémenter l'export des données selon les types d'entités demandés
            // Ceci devrait être configuré selon le schéma de base de données de l'application
            
            $exportedData['user'] = [
                'userId' => $userId,
                'exportDate' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'entityTypes' => $entityTypes,
            ];

            // Export des consentements
            $consents = $this->getUserConsents($userId);
            $exportedData['consents'] = array_map(fn($consent) => $consent->toArray(), $consents);

            // Audit trail de l'export
            $this->auditLogger->logDataExport($userId, $exportedData);

            return $exportedData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to export user data', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Anonymise les données d'un utilisateur (Droit à l'effacement RGPD)
     */
    public function anonymizeUserData(int $userId): array
    {
        $anonymizedFields = [];
        
        $this->entityManager->beginTransaction();
        
        try {
            // Anonymiser les consentements
            $anonymizedConsents = $this->userConsentRepository->anonymizeUserConsents($userId);
            if ($anonymizedConsents > 0) {
                $anonymizedFields[] = 'user_consents';
            }

            // TODO: Ajouter l'anonymisation d'autres entités selon le schéma de l'application
            
            $this->entityManager->commit();

            // Audit trail
            $this->auditLogger->logDataAnonymization($userId, $anonymizedFields);

            return [
                'userId' => $userId,
                'anonymizedFields' => $anonymizedFields,
                'anonymizedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to anonymize user data', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Traite une demande RGPD
     */
    public function processGdprRequest(int $userId, string $requestType, array $data = []): array
    {
        $this->auditLogger->logGdprEvent(
            AuditLoggerService::ACTION_GDPR_REQUEST,
            $userId,
            null,
            $data,
            "Demande RGPD reçue : {$requestType}"
        );

        return match($requestType) {
            'access' => $this->exportUserData($userId, $data['entityTypes'] ?? []),
            'erasure' => $this->anonymizeUserData($userId),
            'portability' => $this->exportUserData($userId, $data['entityTypes'] ?? []),
            'rectification' => ['message' => 'Veuillez contacter le support pour les rectifications'],
            default => throw new \InvalidArgumentException("Type de demande RGPD non supporté : {$requestType}")
        };
    }

    /**
     * Gestion automatique des consentements expirés
     */
    public function handleExpiredConsents(): array
    {
        $expiredConsents = $this->userConsentRepository->findExpiredConsents();
        $processed = [];

        foreach ($expiredConsents as $consent) {
            $consent->setStatus(UserConsent::STATUS_WITHDRAWN);
            $consent->setWithdrawalReason('Expiration automatique');
            
            $this->auditLogger->logGdprEvent(
                AuditLoggerService::ACTION_CONSENT_WITHDRAWN,
                $consent->getUserId(),
                $consent->getConsentType(),
                null,
                "Consentement expiré automatiquement"
            );
            
            $processed[] = $consent->toArray();
        }

        if (!empty($expiredConsents)) {
            $this->entityManager->flush();
        }

        return $processed;
    }

    /**
     * Notification des consentements qui expirent bientôt
     */
    public function getExpiringSoonConsents(int $days = 30): array
    {
        return $this->userConsentRepository->findExpiringSoon($days);
    }

    /**
     * Statistiques de conformité RGPD
     */
    public function getComplianceStats(): array
    {
        $consentStats = $this->userConsentRepository->getConsentStatsByType();
        $consentRates = $this->userConsentRepository->getConsentRates();
        $withdrawnConsents = $this->userConsentRepository->findWithdrawnConsents(30);
        $expiredConsents = $this->userConsentRepository->findExpiredConsents();

        return [
            'consentStatsByType' => $consentStats,
            'consentRates' => $consentRates,
            'recentWithdrawals' => count($withdrawnConsents),
            'expiredConsents' => count($expiredConsents),
            'totalUsers' => $this->getTotalUsersCount(),
            'compliantUsers' => $this->getCompliantUsersCount(),
            'complianceRate' => $this->calculateOverallComplianceRate(),
        ];
    }

    /**
     * Nettoyage automatique RGPD
     */
    public function cleanup(): array
    {
        $results = [];
        
        // Nettoyer les anciens consentements
        $deletedConsents = $this->userConsentRepository->deleteOldConsents();
        $results['deletedConsents'] = $deletedConsents;

        // Traiter les consentements expirés
        $expiredConsents = $this->handleExpiredConsents();
        $results['expiredConsents'] = count($expiredConsents);

        return $results;
    }

    /**
     * Calcule le score de conformité d'un utilisateur
     */
    private function calculateComplianceScore(int $userId): float
    {
        $userConsents = $this->getUserConsents($userId);
        $validConsents = $this->getValidUserConsents($userId);
        
        if (empty($this->requiredConsents)) {
            return 100.0;
        }

        $validConsentTypes = array_map(fn($consent) => $consent->getConsentType(), $validConsents);
        $metRequirements = array_intersect($this->requiredConsents, $validConsentTypes);
        
        return (count($metRequirements) / count($this->requiredConsents)) * 100;
    }

    /**
     * Obtient la date de dernière mise à jour des consentements
     */
    private function getLastConsentUpdateDate(array $consents): ?\DateTimeImmutable
    {
        if (empty($consents)) {
            return null;
        }

        $dates = array_map(fn($consent) => $consent->getUpdatedAt() ?? $consent->getCreatedAt(), $consents);
        $dates = array_filter($dates);
        
        return empty($dates) ? null : max($dates);
    }

    /**
     * Obtient l'adresse IP du client
     */
    private function getClientIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->getClientIp();
    }

    /**
     * Obtient le User-Agent du client
     */
    private function getUserAgent(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->headers->get('User-Agent');
    }

    /**
     * Compte le nombre total d'utilisateurs (à implémenter selon le schéma)
     */
    private function getTotalUsersCount(): int
    {
        // TODO: Implémenter selon l'entité User de l'application
        return 0;
    }

    /**
     * Compte le nombre d'utilisateurs conformes (à implémenter selon le schéma)
     */
    private function getCompliantUsersCount(): int
    {
        // TODO: Implémenter selon l'entité User de l'application
        return 0;
    }

    /**
     * Calcule le taux de conformité global
     */
    private function calculateOverallComplianceRate(): float
    {
        $totalUsers = $this->getTotalUsersCount();
        if ($totalUsers === 0) {
            return 100.0;
        }

        $compliantUsers = $this->getCompliantUsersCount();
        return ($compliantUsers / $totalUsers) * 100;
    }

    /**
     * Configure les consentements requis
     */
    public function setRequiredConsents(array $requiredConsents): void
    {
        $this->requiredConsents = $requiredConsents;
    }

    /**
     * Obtient les consentements requis
     */
    public function getRequiredConsents(): array
    {
        return $this->requiredConsents;
    }
}
