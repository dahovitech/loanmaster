<?php

namespace App\Controller\Api;

use App\Entity\UserConsent;
use App\Service\GDPR\GDPRService;
use App\Service\Audit\AuditLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[Route('/api/gdpr', name: 'api_gdpr_')]
#[OA\Tag(name: 'GDPR')]
class GDPRController extends AbstractController
{
    public function __construct(
        private GDPRService $gdprService,
        private AuditLoggerService $auditLogger,
        private ValidatorInterface $validator
    ) {}

    #[Route('/consents', name: 'consents', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/gdpr/consents',
        summary: 'Récupérer les consentements de l\'utilisateur connecté',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des consentements',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'consentType', type: 'string'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'isValid', type: 'boolean'),
                        ]
                    )
                )
            )
        ]
    )]
    public function getUserConsents(): JsonResponse
    {
        $user = $this->getUser();
        $consents = $this->gdprService->getUserConsents($user->getId());
        
        $data = array_map(fn($consent) => $consent->toArray(), $consents);
        
        return $this->json($data);
    }

    #[Route('/consents/grant', name: 'grant_consent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/consents/grant',
        summary: 'Accorder un consentement',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'consentType', type: 'string', example: 'marketing'),
                    new OA\Property(property: 'consentText', type: 'string'),
                    new OA\Property(property: 'version', type: 'string', example: '1.0'),
                    new OA\Property(property: 'durationDays', type: 'integer', example: 365),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Consentement accordé'),
            new OA\Response(response: 400, description: 'Données invalides')
        ]
    )]
    public function grantConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['consentType'])) {
            return $this->json(['error' => 'consentType requis'], 400);
        }

        $user = $this->getUser();
        $consentType = $data['consentType'];

        // Valider le type de consentement
        if (!in_array($consentType, UserConsent::getAvailableTypes())) {
            return $this->json(['error' => 'Type de consentement invalide'], 400);
        }

        try {
            $consent = $this->gdprService->grantConsent(
                $user->getId(),
                $consentType,
                $data['consentText'] ?? null,
                $data['version'] ?? '1.0',
                $data['durationDays'] ?? null,
                $data['locale'] ?? 'fr'
            );

            return $this->json([
                'success' => true,
                'consent' => $consent->toArray(),
                'message' => 'Consentement accordé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/consents/withdraw', name: 'withdraw_consent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/consents/withdraw',
        summary: 'Retirer un consentement',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'consentType', type: 'string', example: 'marketing'),
                    new OA\Property(property: 'reason', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Consentement retiré'),
            new OA\Response(response: 404, description: 'Consentement non trouvé')
        ]
    )]
    public function withdrawConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['consentType'])) {
            return $this->json(['error' => 'consentType requis'], 400);
        }

        $user = $this->getUser();
        $consentType = $data['consentType'];
        $reason = $data['reason'] ?? null;

        try {
            $consent = $this->gdprService->withdrawConsent($user->getId(), $consentType, $reason);
            
            if (!$consent) {
                return $this->json(['error' => 'Consentement non trouvé'], 404);
            }

            return $this->json([
                'success' => true,
                'consent' => $consent->toArray(),
                'message' => 'Consentement retiré avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/consents/deny', name: 'deny_consent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/consents/deny',
        summary: 'Refuser un consentement',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'consentType', type: 'string', example: 'marketing'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Consentement refusé')
        ]
    )]
    public function denyConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['consentType'])) {
            return $this->json(['error' => 'consentType requis'], 400);
        }

        $user = $this->getUser();
        $consentType = $data['consentType'];

        // Valider le type de consentement
        if (!in_array($consentType, UserConsent::getAvailableTypes())) {
            return $this->json(['error' => 'Type de consentement invalide'], 400);
        }

        try {
            $consent = $this->gdprService->denyConsent($user->getId(), $consentType);

            return $this->json([
                'success' => true,
                'consent' => $consent->toArray(),
                'message' => 'Consentement refusé'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/compliance', name: 'compliance_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/gdpr/compliance',
        summary: 'Vérifier le statut de conformité RGPD de l\'utilisateur',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statut de conformité',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'userId', type: 'integer'),
                        new OA\Property(property: 'isCompliant', type: 'boolean'),
                        new OA\Property(property: 'complianceScore', type: 'number'),
                        new OA\Property(property: 'missingConsents', type: 'array'),
                        new OA\Property(property: 'expiringConsents', type: 'integer'),
                    ]
                )
            )
        ]
    )]
    public function getComplianceStatus(): JsonResponse
    {
        $user = $this->getUser();
        $compliance = $this->gdprService->checkUserCompliance($user->getId());
        
        return $this->json($compliance);
    }

    #[Route('/data-export', name: 'data_export', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/data-export',
        summary: 'Demander l\'export des données personnelles (Droit d\'accès RGPD)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'entityTypes',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['User', 'LoanApplication']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données exportées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'exportDate', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function exportUserData(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $entityTypes = $data['entityTypes'] ?? [];

        $user = $this->getUser();

        try {
            $exportedData = $this->gdprService->exportUserData($user->getId(), $entityTypes);

            return $this->json([
                'success' => true,
                'data' => $exportedData,
                'exportDate' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'message' => 'Données exportées avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/data-deletion', name: 'data_deletion', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/data-deletion',
        summary: 'Demander l\'anonymisation/suppression des données (Droit à l\'effacement RGPD)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Demande de suppression enregistrée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'requestId', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function requestDataDeletion(): JsonResponse
    {
        $user = $this->getUser();

        try {
            // Pour une vraie suppression, ceci devrait créer une demande qui sera traitée par un admin
            // Pour l'instant, on enregistre seulement la demande dans l'audit log
            $this->auditLogger->logGdprEvent(
                AuditLoggerService::ACTION_GDPR_REQUEST,
                $user->getId(),
                null,
                ['requestType' => 'data_deletion'],
                'Demande de suppression de données RGPD'
            );

            $requestId = 'DEL_' . $user->getId() . '_' . time();

            return $this->json([
                'success' => true,
                'requestId' => $requestId,
                'message' => 'Votre demande de suppression a été enregistrée. Elle sera traitée dans les plus brefs délais.',
                'processingTime' => '30 jours maximum selon RGPD'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/data-portability', name: 'data_portability', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/gdpr/data-portability',
        summary: 'Demander la portabilité des données (Droit à la portabilité RGPD)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'format', type: 'string', example: 'json'),
                    new OA\Property(
                        property: 'entityTypes',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Données exportées pour portabilité'
            )
        ]
    )]
    public function requestDataPortability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $format = $data['format'] ?? 'json';
        $entityTypes = $data['entityTypes'] ?? [];

        $user = $this->getUser();

        try {
            $exportedData = $this->gdprService->processGdprRequest(
                $user->getId(),
                'portability',
                ['entityTypes' => $entityTypes]
            );

            return $this->json([
                'success' => true,
                'data' => $exportedData,
                'format' => $format,
                'exportDate' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'message' => 'Données exportées pour portabilité'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/consent-types', name: 'consent_types', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gdpr/consent-types',
        summary: 'Obtenir la liste des types de consentement disponibles',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Types de consentement',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'required', type: 'boolean'),
                        ]
                    )
                )
            )
        ]
    )]
    public function getConsentTypes(Request $request): JsonResponse
    {
        $locale = $request->getLocale() ?? 'fr';
        $types = UserConsent::getAvailableTypes();
        
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'type' => $type,
                'description' => UserConsent::getTypeDescription($type, $locale),
                'required' => in_array($type, $this->gdprService->getRequiredConsents()),
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/audit-trail', name: 'audit_trail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/gdpr/audit-trail',
        summary: 'Obtenir l\'historique d\'audit pour l\'utilisateur connecté',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique d\'audit',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'action', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string'),
                        ]
                    )
                )
            )
        ]
    )]
    public function getUserAuditTrail(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = min($request->query->getInt('limit', 50), 100);
        
        // Récupérer les logs d'audit pour cet utilisateur (actions GDPR uniquement)
        $auditLogs = $this->auditLogger->auditLogRepository->createQueryBuilder('a')
            ->andWhere('a.userId = :userId')
            ->andWhere('a.gdprData IS NOT NULL OR a.action LIKE :gdprAction')
            ->setParameter('userId', $user->getId())
            ->setParameter('gdprAction', '%consent%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function($log) {
            return [
                'action' => $log->getAction(),
                'description' => $log->getDescription(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'metadata' => $log->getGdprData(),
            ];
        }, $auditLogs);
        
        return $this->json($data);
    }
}
