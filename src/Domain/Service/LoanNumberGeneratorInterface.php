<?php

declare(strict_types=1);

namespace App\Domain\Service;

interface LoanNumberGeneratorInterface
{
    public function generate(): string;
}
