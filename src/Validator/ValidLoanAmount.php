<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint for validating loan amounts
 */
#[\Attribute]
class ValidLoanAmount extends Constraint
{
    public string $message = 'The loan amount must be between {{ min }} and {{ max }}.';
    public float $min = 100;
    public float $max = 1000000;
    public string $currency = 'EUR';

    public function __construct(
        float $min = null,
        float $max = null,
        string $currency = null,
        string $message = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->min = $min ?? $this->min;
        $this->max = $max ?? $this->max;
        $this->currency = $currency ?? $this->currency;
        $this->message = $message ?? $this->message;
    }
}