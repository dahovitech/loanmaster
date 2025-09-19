<?php

namespace App\GraphQL\Types;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use DateTimeImmutable;
use DateTime;
use DateTimeInterface;
use Exception;

/**
 * Type scalaire DateTime pour GraphQL
 * Support des formats ISO 8601 et timestamps
 */
class DateTimeType extends ScalarType
{
    public string $name = 'DateTime';
    public ?string $description = 'DateTime custom scalar type (ISO 8601 format)';

    /**
     * Sérialise une valeur DateTime pour GraphQL
     */
    public function serialize($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTime::ATOM);
        }

        if (is_string($value)) {
            try {
                $date = new DateTimeImmutable($value);
                return $date->format(DateTime::ATOM);
            } catch (Exception $e) {
                throw new Error('Invalid datetime string: ' . $value);
            }
        }

        if (is_int($value)) {
            return (new DateTimeImmutable('@' . $value))->format(DateTime::ATOM);
        }

        throw new Error('Cannot serialize non-DateTime value: ' . Utils::printSafe($value));
    }

    /**
     * Parse une valeur depuis GraphQL vers PHP
     */
    public function parseValue($value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new Error('DateTime value must be a string, got: ' . Utils::printSafe($value));
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw new Error('Invalid datetime format: ' . $value . '. Expected ISO 8601 format.');
        }
    }

    /**
     * Parse un literal depuis le AST GraphQL
     */
    public function parseLiteral($valueNode, ?array $variables = null): DateTimeImmutable
    {
        if (!property_exists($valueNode, 'value') || !is_string($valueNode->value)) {
            throw new Error('DateTime literal must be a string');
        }

        return $this->parseValue($valueNode->value);
    }
}
