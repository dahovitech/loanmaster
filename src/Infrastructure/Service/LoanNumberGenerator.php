<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Service\LoanNumberGeneratorInterface;

final class LoanNumberGenerator implements LoanNumberGeneratorInterface
{
    private const PREFIX = 'DOC';
    
    public function generate(): string
    {
        $timestamp = time();
        $random = random_int(1000, 9999);
        
        return sprintf('%s%010d%04d', self::PREFIX, $timestamp, $random);
    }
}
