<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Http\Api\Resource\LoanResource;
use App\Infrastructure\Http\Api\Transformer\LoanResourceTransformer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class LoanCollectionProvider implements ProviderInterface
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private LoanResourceTransformer $transformer,
        private Security $security,
        private RequestStack $requestStack,
        private Pagination $pagination
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }
        
        $request = $this->requestStack->getCurrentRequest();
        $userId = null;
        
        // Si l'utilisateur est admin, il peut filtrer par userId
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $userIdParam = $request?->query->get('userId');
            if ($userIdParam) {
                $userId = UserId::fromString($userIdParam);
            }
        } else {
            // Sinon, on ne montre que ses propres prêts
            $userId = UserId::fromString($user->getId());
        }
        
        $loans = $userId 
            ? $this->loanRepository->findByUserId($userId)
            : $this->loanRepository->findPendingLoans(); // Admin voit tous les prêts en attente
        
        // Transformation en ressources API
        $resources = [];
        foreach ($loans as $loan) {
            $resources[] = $this->transformer->transformToResource($loan);
        }
        
        return $resources;
    }
}
