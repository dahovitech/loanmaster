<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Duration
{
    private function __construct(private int $months)
    {
        if ($months < 6) {
            throw new InvalidArgumentException('Duration must be at least 6 months');
        }
        if ($months > 360) {
            throw new InvalidArgumentException('Duration cannot exceed 360 months (30 years)');
        }
    }
    
    public static function fromMonths(int $months): self
    {
        return new self($months);
    }
    
    public static function fromYears(int $years): self
    {
        return new self($years * 12);
    }
    
    public function getMonths(): int
    {
        return $this->months;
    }
    
    public function getYears(): float
    {
        return $this->months / 12;
    }
    
    public function equals(self $other): bool
    {
        return $this->months === $other->months;
    }
    
    public function __toString(): string
    {
        if ($this->months % 12 === 0) {
            $years = $this->months / 12;
            return "{$years} an" . ($years > 1 ? 's' : '');
        }
        
        return "{$this->months} mois";
    }
}
