<?php

namespace App\GraphQL\Types;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Exception;

/**
 * Type scalaire UUID pour GraphQL
 * Support des UUID v4 avec validation
 */
class UUIDType extends ScalarType
{
    public string $name = 'UUID';
    public ?string $description = 'UUID custom scalar type (RFC 4122 format)';

    /**
     * Sérialise une valeur UUID pour GraphQL
     */
    public function serialize($value): string
    {
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        if (is_string($value)) {
            if (!Uuid::isValid($value)) {
                throw new Error('Invalid UUID format: ' . $value);
            }
            return $value;
        }

        throw new Error('Cannot serialize non-UUID value: ' . Utils::printSafe($value));
    }

    /**
     * Parse une valeur depuis GraphQL vers PHP
     */
    public function parseValue($value): UuidInterface
    {
        if (!is_string($value)) {
            throw new Error('UUID value must be a string, got: ' . Utils::printSafe($value));
        }

        try {
            return Uuid::fromString($value);
        } catch (Exception $e) {
            throw new Error('Invalid UUID format: ' . $value . '. Expected RFC 4122 format.');
        }
    }

    /**
     * Parse un literal depuis le AST GraphQL
     */
    public function parseLiteral($valueNode, ?array $variables = null): UuidInterface
    {
        if (!property_exists($valueNode, 'value') || !is_string($valueNode->value)) {
            throw new Error('UUID literal must be a string');
        }

        return $this->parseValue($valueNode->value);
    }
}
