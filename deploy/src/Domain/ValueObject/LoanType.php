<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum LoanType: string
{
    case PERSONAL = 'personal';
    case AUTO = 'auto';
    case MORTGAGE = 'mortgage';
    case BUSINESS = 'business';
    case STUDENT = 'student';
    case RENOVATION = 'renovation';
    
    public function getLabel(): string
    {
        return match ($this) {
            self::PERSONAL => 'Prêt personnel',
            self::AUTO => 'Crédit auto',
            self::MORTGAGE => 'Crédit immobilier',
            self::BUSINESS => 'Crédit professionnel',
            self::STUDENT => 'Prêt étudiant',
            self::RENOVATION => 'Crédit travaux',
        };
    }
    
    public function getMaxAmount(): float
    {
        return match ($this) {
            self::PERSONAL => 75000.0,
            self::AUTO => 80000.0,
            self::MORTGAGE => 1000000.0,
            self::BUSINESS => 500000.0,
            self::STUDENT => 50000.0,
            self::RENOVATION => 100000.0,
        };
    }
    
    public function getMaxDurationMonths(): int
    {
        return match ($this) {
            self::PERSONAL => 96,  // 8 ans
            self::AUTO => 84,      // 7 ans
            self::MORTGAGE => 360, // 30 ans
            self::BUSINESS => 120, // 10 ans
            self::STUDENT => 120,  // 10 ans
            self::RENOVATION => 144, // 12 ans
        };
    }
    
    public function getBaseInterestRate(): float
    {
        return match ($this) {
            self::PERSONAL => 0.035,   // 3.5%
            self::AUTO => 0.025,       // 2.5%
            self::MORTGAGE => 0.015,   // 1.5%
            self::BUSINESS => 0.04,    // 4%
            self::STUDENT => 0.01,     // 1%
            self::RENOVATION => 0.03,  // 3%
        };
    }
}
