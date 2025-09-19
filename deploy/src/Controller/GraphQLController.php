<?php

namespace App\Controller;

use App\GraphQL\Types\DateTimeType;
use App\GraphQL\Types\UUIDType;
use App\GraphQL\Types\JSONType;
use App\GraphQL\Resolver\LoanResolver;
use App\GraphQL\Resolver\LoanMutationResolver;
use App\GraphQL\Resolver\AuditResolver;
use App\GraphQL\Resolver\LoanSubscriptionResolver;
use App\GraphQL\Middleware\AuthenticationMiddleware;
use App\GraphQL\Middleware\AuditMiddleware;
use App\GraphQL\Middleware\RateLimitMiddleware;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Exception;

/**
 * Contrôleur principal GraphQL
 * Point d'entrée unique pour toutes les opérations GraphQL
 */
#[Route('/graphql', name: 'graphql_')]
class GraphQLController extends AbstractController
{
    private LoanResolver $loanResolver;
    private LoanMutationResolver $loanMutationResolver;
    private AuditResolver $auditResolver;
    private LoanSubscriptionResolver $subscriptionResolver;
    private AuthenticationMiddleware $authMiddleware;
    private AuditMiddleware $auditMiddleware;
    private RateLimitMiddleware $rateLimitMiddleware;

    public function __construct(
        LoanResolver $loanResolver,
        LoanMutationResolver $loanMutationResolver,
        AuditResolver $auditResolver,
        LoanSubscriptionResolver $subscriptionResolver,
        AuthenticationMiddleware $authMiddleware,
        AuditMiddleware $auditMiddleware,
        RateLimitMiddleware $rateLimitMiddleware
    ) {
        $this->loanResolver = $loanResolver;
        $this->loanMutationResolver = $loanMutationResolver;
        $this->auditResolver = $auditResolver;
        $this->subscriptionResolver = $subscriptionResolver;
        $this->authMiddleware = $authMiddleware;
        $this->auditMiddleware = $auditMiddleware;
        $this->rateLimitMiddleware = $rateLimitMiddleware;
    }

    /**
     * Point d'entrée principal GraphQL
     */
    #[Route('', name: 'endpoint', methods: ['POST', 'GET'])]
    public function index(Request $request): JsonResponse
    {
        try {
            // Création du schéma GraphQL
            $schema = $this->createSchema();
            
            // Extraction de la requête GraphQL
            $input = $this->extractGraphQLInput($request);
            
            // Création du contexte
            $context = $this->createContext($request);
            
            // Exécution de la requête GraphQL
            $result = GraphQL::executeQuery(
                $schema,
                $input['query'] ?? null,
                null, // rootValue
                $context,
                $input['variables'] ?? [],
                $input['operationName'] ?? null
            );
            
            // Configuration du debug en mode développement
            $debug = $this->getParameter('kernel.debug') ? 
                DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : 
                DebugFlag::NONE;
            
            $output = $result->toArray($debug);
            
            // Ajout de métadonnées
            $output['extensions'] = [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                'version' => '1.0.0',
                'complexity' => $this->calculateComplexity($input['query'] ?? ''),
                'execution_time' => number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2)
            ];
            
            return new JsonResponse($output, Response::HTTP_OK, [
                'Content-Type' => 'application/json',
                'X-GraphQL-Endpoint' => 'LoanMaster GraphQL API v1.0'
            ]);
            
        } catch (Exception $e) {
            return new JsonResponse([
                'errors' => [
                    FormattedError::createFromException($e)
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Playground GraphQL (mode développement)
     */
    #[Route('/playground', name: 'playground', methods: ['GET'])]
    public function playground(): Response
    {
        if (!$this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException('GraphQL Playground is only available in debug mode');
        }
        
        return $this->render('graphql/playground.html.twig', [
            'endpoint' => $this->generateUrl('graphql_endpoint'),
            'subscriptionEndpoint' => $this->generateUrl('graphql_subscription')
        ]);
    }

    /**
     * Point d'entrée pour les souscriptions WebSocket
     */
    #[Route('/subscription', name: 'subscription', methods: ['GET'])]
    public function subscription(Request $request): Response
    {
        // Implémentation WebSocket pour les souscriptions
        // En production, ceci serait géré par un serveur WebSocket dédié
        
        return new Response('WebSocket endpoint for GraphQL subscriptions', Response::HTTP_OK, [
            'Content-Type' => 'text/plain'
        ]);
    }

    /**
     * Introspection du schéma (mode développement)
     */
    #[Route('/schema', name: 'schema', methods: ['GET'])]
    public function schema(): JsonResponse
    {
        if (!$this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException('Schema introspection is only available in debug mode');
        }
        
        $schema = $this->createSchema();
        $introspectionQuery = \GraphQL\Type\Introspection::getIntrospectionQuery();
        
        $result = GraphQL::executeQuery(
            $schema,
            $introspectionQuery
        );
        
        return new JsonResponse($result->toArray());
    }

    /**
     * Crée le schéma GraphQL
     */
    private function createSchema(): Schema
    {
        // Chargement du schéma depuis le fichier
        $schemaFile = $this->getParameter('kernel.project_dir') . '/graphql/schema.graphql';
        $schemaContent = file_get_contents($schemaFile);
        
        // Construction du schéma
        $schema = BuildSchema::build($schemaContent, function($config, $typeDefinitionNode) {
            // Configuration des types personnalisés
            return $this->configureCustomTypes($config, $typeDefinitionNode);
        });
        
        // Configuration des résolveurs
        $this->configureResolvers($schema);
        
        return $schema;
    }

    /**
     * Configure les types personnalisés
     */
    private function configureCustomTypes($config, $typeDefinitionNode)
    {
        $typeName = $typeDefinitionNode->name->value;
        
        return match ($typeName) {
            'DateTime' => new DateTimeType(),
            'UUID' => new UUIDType(),
            'JSON' => new JSONType(),
            default => null
        };
    }

    /**
     * Configure les résolveurs
     */
    private function configureResolvers(Schema $schema): void
    {
        // Configuration des résolveurs pour les requêtes
        $queryType = $schema->getQueryType();
        if ($queryType) {
            $this->setFieldResolver($queryType, 'loans', [$this->loanResolver, 'loans']);
            $this->setFieldResolver($queryType, 'loan', [$this->loanResolver, 'loan']);
            $this->setFieldResolver($queryType, 'loanStatistics', [$this->loanResolver, 'statistics']);
            $this->setFieldResolver($queryType, 'auditHistory', [$this->auditResolver, 'history']);
            $this->setFieldResolver($queryType, 'eventHistory', [$this->auditResolver, 'eventHistory']);
            $this->setFieldResolver($queryType, 'reconstructState', [$this->auditResolver, 'reconstructState']);
        }
        
        // Configuration des résolveurs pour les mutations
        $mutationType = $schema->getMutationType();
        if ($mutationType) {
            $this->setFieldResolver($mutationType, 'createLoanApplication', [$this->loanMutationResolver, 'createApplication']);
            $this->setFieldResolver($mutationType, 'changeLoanStatus', [$this->loanMutationResolver, 'changeStatus']);
            $this->setFieldResolver($mutationType, 'assessLoanRisk', [$this->loanMutationResolver, 'assessRisk']);
        }
        
        // Configuration des résolveurs pour les souscriptions
        $subscriptionType = $schema->getSubscriptionType();
        if ($subscriptionType) {
            $this->setFieldResolver($subscriptionType, 'loanStatusUpdated', [$this->subscriptionResolver, 'statusUpdated']);
            $this->setFieldResolver($subscriptionType, 'newLoanApplication', [$this->subscriptionResolver, 'newApplication']);
            $this->setFieldResolver($subscriptionType, 'riskAlerts', [$this->subscriptionResolver, 'riskAlerts']);
            $this->setFieldResolver($subscriptionType, 'auditEvents', [$this->subscriptionResolver, 'auditEvents']);
        }
    }

    /**
     * Définit un résolveur pour un champ avec middleware
     */
    private function setFieldResolver($type, string $fieldName, callable $resolver): void
    {
        $field = $type->getField($fieldName);
        
        if ($field) {
            $field->resolveFn = function($root, $args, $context, $info) use ($resolver) {
                // Application des middlewares
                $middlewareChain = $this->createMiddlewareChain($resolver);
                return $middlewareChain($root, $args, $context, $info);
            };
        }
    }

    /**
     * Crée la chaîne de middlewares
     */
    private function createMiddlewareChain(callable $resolver): callable
    {
        $middlewares = [
            $this->rateLimitMiddleware,
            $this->authMiddleware,
            $this->auditMiddleware
        ];
        
        return array_reduce(
            array_reverse($middlewares),
            function($next, $middleware) {
                return function($root, $args, $context, $info) use ($middleware, $next) {
                    return $middleware($next, $root, $args, $context, $info);
                };
            },
            $resolver
        );
    }

    /**
     * Extrait l'input GraphQL de la requête
     */
    private function extractGraphQLInput(Request $request): array
    {
        if ($request->isMethod('GET')) {
            return [
                'query' => $request->query->get('query'),
                'variables' => $request->query->get('variables') ? 
                    json_decode($request->query->get('variables'), true) : [],
                'operationName' => $request->query->get('operationName')
            ];
        }
        
        $contentType = $request->headers->get('Content-Type', '');
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($request->getContent(), true);
            return [
                'query' => $data['query'] ?? null,
                'variables' => $data['variables'] ?? [],
                'operationName' => $data['operationName'] ?? null
            ];
        }
        
        // Fallback pour form data
        return [
            'query' => $request->request->get('query'),
            'variables' => $request->request->get('variables') ? 
                json_decode($request->request->get('variables'), true) : [],
            'operationName' => $request->request->get('operationName')
        ];
    }

    /**
     * Crée le contexte pour GraphQL
     */
    private function createContext(Request $request): array
    {
        $user = null;
        
        // Extraction de l'utilisateur depuis le token JWT ou session
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $user = $this->parseJwtToken($token);
        }
        
        return [
            'user' => $user,
            'request' => [
                'ip' => $request->getClientIp(),
                'userAgent' => $request->headers->get('User-Agent'),
                'referer' => $request->headers->get('Referer'),
                'correlationId' => $request->headers->get('X-Correlation-ID')
            ],
            'symfony' => [
                'request' => $request,
                'container' => $this->container
            ]
        ];
    }

    /**
     * Parse un token JWT (simplifié)
     */
    private function parseJwtToken(string $token): ?array
    {
        // TODO: Implémenter le parsing JWT réel
        // Pour l'instant, retour d'un utilisateur de test
        return [
            'id' => 'user123',
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN']
        ];
    }

    /**
     * Calcule la complexité de la requête (simplifié)
     */
    private function calculateComplexity(string $query): int
    {
        // Calcul basique de complexité
        $complexity = substr_count($query, '{') + substr_count($query, 'query') + substr_count($query, 'mutation');
        return max(1, $complexity);
    }
}
