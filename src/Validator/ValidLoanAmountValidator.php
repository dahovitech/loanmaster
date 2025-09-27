<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator for ValidLoanAmount constraint
 */
class ValidLoanAmountValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidLoanAmount) {
            throw new UnexpectedTypeException($constraint, ValidLoanAmount::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_numeric($value)) {
            throw new UnexpectedValueException($value, 'numeric');
        }

        $amount = (float) $value;

        // Validate minimum amount
        if ($amount < $constraint->min) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ min }}', $this->formatAmount($constraint->min, $constraint->currency))
                ->setParameter('{{ max }}', $this->formatAmount($constraint->max, $constraint->currency))
                ->addViolation();
            return;
        }

        // Validate maximum amount
        if ($amount > $constraint->max) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ min }}', $this->formatAmount($constraint->min, $constraint->currency))
                ->setParameter('{{ max }}', $this->formatAmount($constraint->max, $constraint->currency))
                ->addViolation();
        }
    }

    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }
}