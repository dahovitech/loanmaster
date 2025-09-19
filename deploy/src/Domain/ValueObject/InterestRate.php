<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final readonly class InterestRate
{
    private function __construct(private float $annualRate)
    {
        if ($annualRate < 0) {
            throw new InvalidArgumentException('Interest rate cannot be negative');
        }
        if ($annualRate > 0.5) {
            throw new InvalidArgumentException('Interest rate cannot exceed 50%');
        }
    }
    
    public static function fromPercentage(float $percentage): self
    {
        return new self($percentage / 100);
    }
    
    public static function fromDecimal(float $decimal): self
    {
        return new self($decimal);
    }
    
    public function getAnnualRate(): float
    {
        return $this->annualRate;
    }
    
    public function getPercentage(): float
    {
        return $this->annualRate * 100;
    }
    
    public function getMonthlyRate(): float
    {
        return $this->annualRate / 12;
    }
    
    public function equals(self $other): bool
    {
        return abs($this->annualRate - $other->annualRate) < 0.0001;
    }
    
    public function __toString(): string
    {
        return number_format($this->getPercentage(), 2) . '%';
    }
}
