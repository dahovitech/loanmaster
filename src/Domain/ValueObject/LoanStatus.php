<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum LoanStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case FUNDED = 'funded';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';
    case CANCELLED = 'cancelled';
    
    public function canTransitionTo(self $newStatus): bool
    {
        return match ([$this, $newStatus]) {
            [self::PENDING, self::UNDER_REVIEW] => true,
            [self::UNDER_REVIEW, self::APPROVED] => true,
            [self::UNDER_REVIEW, self::REJECTED] => true,
            [self::APPROVED, self::FUNDED] => true,
            [self::FUNDED, self::ACTIVE] => true,
            [self::ACTIVE, self::COMPLETED] => true,
            [self::ACTIVE, self::DEFAULTED] => true,
            [self::PENDING, self::CANCELLED] => true,
            [self::UNDER_REVIEW, self::CANCELLED] => true,
            default => false,
        };
    }
    
    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::UNDER_REVIEW, self::APPROVED, self::FUNDED, self::ACTIVE]);
    }
    
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED, self::DEFAULTED, self::CANCELLED]);
    }
    
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::UNDER_REVIEW => 'En cours d\'examen',
            self::APPROVED => 'Approuvé',
            self::REJECTED => 'Rejeté',
            self::FUNDED => 'Financé',
            self::ACTIVE => 'Actif',
            self::COMPLETED => 'Terminé',
            self::DEFAULTED => 'En défaut',
            self::CANCELLED => 'Annulé',
        };
    }
}
