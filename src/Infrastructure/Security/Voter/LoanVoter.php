<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\Entity\Loan;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class LoanVoter extends Voter
{
    public const VIEW = 'loan.view';
    public const EDIT = 'loan.edit';
    public const APPROVE = 'loan.approve';
    public const FUND = 'loan.fund';
    public const ADMIN_ACCESS = 'loan.admin';
    
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::APPROVE, self::FUND, self::ADMIN_ACCESS])
            && ($subject instanceof Loan || $subject === null);
    }
    
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof UserInterface) {
            return false;
        }
        
        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::APPROVE => $this->canApprove($subject, $user),
            self::FUND => $this->canFund($subject, $user),
            self::ADMIN_ACCESS => $this->hasAdminAccess($user),
            default => false,
        };
    }
    
    private function canView(?Loan $loan, UserInterface $user): bool
    {
        // Admin peut voir tous les prêts
        if ($this->hasRole($user, 'ROLE_ADMIN')) {
            return true;
        }
        
        // Loan officer peut voir les prêts en cours d'examen
        if ($this->hasRole($user, 'ROLE_LOAN_OFFICER') && $loan) {
            return in_array($loan->getStatus(), [
                \App\Domain\ValueObject\LoanStatus::UNDER_REVIEW,
                \App\Domain\ValueObject\LoanStatus::APPROVED,
                \App\Domain\ValueObject\LoanStatus::FUNDED
            ]);
        }
        
        // Utilisateur peut voir ses propres prêts
        if ($loan && $this->hasRole($user, 'ROLE_USER')) {
            return $loan->getUserId()->toString() === $user->getId();
        }
        
        return false;
    }
    
    private function canEdit(?Loan $loan, UserInterface $user): bool
    {
        if (!$loan) {
            return false;
        }
        
        // Admin peut toujours éditer
        if ($this->hasRole($user, 'ROLE_ADMIN')) {
            return true;
        }
        
        // Loan officer peut éditer les prêts en cours d'examen
        if ($this->hasRole($user, 'ROLE_LOAN_OFFICER')) {
            return $loan->getStatus() === \App\Domain\ValueObject\LoanStatus::UNDER_REVIEW;
        }
        
        // Utilisateur peut éditer ses prêts en attente
        if ($this->hasRole($user, 'ROLE_USER')) {
            return $loan->getUserId()->toString() === $user->getId() 
                && $loan->getStatus() === \App\Domain\ValueObject\LoanStatus::PENDING;
        }
        
        return false;
    }
    
    private function canApprove(?Loan $loan, UserInterface $user): bool
    {
        if (!$loan) {
            return false;
        }
        
        // Seuls les loan officers et admins peuvent approuver
        return ($this->hasRole($user, 'ROLE_LOAN_OFFICER') || $this->hasRole($user, 'ROLE_ADMIN'))
            && $loan->getStatus() === \App\Domain\ValueObject\LoanStatus::UNDER_REVIEW;
    }
    
    private function canFund(?Loan $loan, UserInterface $user): bool
    {
        if (!$loan) {
            return false;
        }
        
        // Seuls les admins peuvent financer
        return $this->hasRole($user, 'ROLE_ADMIN')
            && $loan->getStatus() === \App\Domain\ValueObject\LoanStatus::APPROVED;
    }
    
    private function hasAdminAccess(UserInterface $user): bool
    {
        return $this->hasRole($user, 'ROLE_ADMIN');
    }
    
    private function hasRole(UserInterface $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }
}
