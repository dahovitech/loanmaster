<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Query\Loan\GetLoanByIdQuery;
use App\Application\Service\LoanCalculatorService;
use App\Domain\ValueObject\Amount;
use App\Domain\ValueObject\Duration;
use App\Domain\ValueObject\InterestRate;
use App\Domain\ValueObject\LoanType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/loans', name: 'api_loans_')]
final class LoanApiController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private LoanCalculatorService $calculator,
        private ValidatorInterface $validator
    ) {}
    
    #[Route('/calculate', name: 'calculate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function calculateLoan(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $loanType = LoanType::from($data['loanType'] ?? '');
            $amount = Amount::fromFloat($data['amount'] ?? 0);
            $duration = Duration::fromMonths($data['durationMonths'] ?? 0);
            $userIncome = (float) ($data['userIncome'] ?? 0);
            
            $interestRate = InterestRate::fromDecimal($loanType->getBaseInterestRate());
            
            $calculations = [
                'monthlyPayment' => $this->calculator->calculateMonthlyPayment($amount, $interestRate, $duration)->getValue(),
                'totalAmount' => $this->calculator->calculateTotalAmount($amount, $interestRate, $duration)->getValue(),
                'totalInterest' => $this->calculator->calculateTotalInterest($amount, $interestRate, $duration)->getValue(),
                'interestRate' => $interestRate->getPercentage(),
                'eligibilityScore' => $this->calculator->getEligibilityScore($loanType, $amount, $duration, $userIncome)
            ];
            
            return $this->json([
                'success' => true,
                'data' => $calculations
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/{id}/details', name: 'details', methods: ['GET'])]
    #[IsGranted('loan.view', subject: 'id')]
    public function getLoanDetails(string $id): JsonResponse
    {
        try {
            $query = new GetLoanByIdQuery($id);
            $loan = $this->queryBus->dispatch($query);
            
            if (!$loan) {
                return $this->json(['error' => 'Loan not found'], 404);
            }
            
            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $loan->getId()->toString(),
                    'number' => $loan->getNumber(),
                    'type' => $loan->getType()->value,
                    'typeLabel' => $loan->getType()->getLabel(),
                    'amount' => $loan->getAmount()->getValue(),
                    'duration' => $loan->getDuration()->getMonths(),
                    'status' => $loan->getStatus()->value,
                    'statusLabel' => $loan->getStatus()->getLabel(),
                    'monthlyPayment' => $loan->calculateMonthlyPayment()->getValue(),
                    'totalAmount' => $loan->calculateTotalAmount()->getValue(),
                    'totalInterest' => $loan->calculateTotalInterest()->getValue(),
                    'createdAt' => $loan->getCreatedAt()->format('c'),
                    'approvedAt' => $loan->getApprovedAt()?->format('c'),
                    'fundedAt' => $loan->getFundedAt()?->format('c')
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/types', name: 'types', methods: ['GET'])]
    public function getLoanTypes(): JsonResponse
    {
        $types = [];
        
        foreach (LoanType::cases() as $type) {
            $types[] = [
                'value' => $type->value,
                'label' => $type->getLabel(),
                'maxAmount' => $type->getMaxAmount(),
                'maxDurationMonths' => $type->getMaxDurationMonths(),
                'baseInterestRate' => $type->getBaseInterestRate() * 100 // En pourcentage
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => $types
        ]);
    }
}
