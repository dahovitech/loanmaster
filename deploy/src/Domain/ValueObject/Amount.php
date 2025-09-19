<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Amount
{
    private function __construct(private float $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }
        if ($value > 1000000) {
            throw new InvalidArgumentException('Amount exceeds maximum limit of 1,000,000');
        }
    }
    
    public static function fromFloat(float $value): self
    {
        return new self($value);
    }
    
    public function getValue(): float
    {
        return $this->value;
    }
    
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }
    
    public function subtract(self $other): self
    {
        return new self($this->value - $other->value);
    }
    
    public function multiply(float $factor): self
    {
        return new self($this->value * $factor);
    }
    
    public function calculateInterest(InterestRate $rate, Duration $duration): self
    {
        $monthlyRate = $rate->getAnnualRate() / 12;
        $months = $duration->getMonths();
        
        if ($monthlyRate === 0.0) {
            return new self($this->value / $months);
        }
        
        $monthlyPayment = $this->value * $monthlyRate / (1 - pow(1 + $monthlyRate, -$months));
        return new self($monthlyPayment);
    }
    
    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.01;
    }
    
    public function __toString(): string
    {
        return number_format($this->value, 2) . ' €';
    }
}
