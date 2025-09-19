<?php

namespace App\GraphQL\Types;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use JsonException;

/**
 * Type scalaire JSON pour GraphQL
 * Support des objets JSON arbitraires
 */
class JSONType extends ScalarType
{
    public string $name = 'JSON';
    public ?string $description = 'JSON custom scalar type for arbitrary JSON data';

    /**
     * Sérialise une valeur JSON pour GraphQL
     */
    public function serialize($value)
    {
        if (is_string($value)) {
            // Vérifie que c'est du JSON valide
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Error('Invalid JSON string: ' . json_last_error_msg());
            }
            return json_decode($value, true);
        }

        if (is_array($value) || is_object($value) || is_null($value) || is_bool($value) || is_numeric($value)) {
            return $value;
        }

        throw new Error('Cannot serialize value to JSON: ' . Utils::printSafe($value));
    }

    /**
     * Parse une valeur depuis GraphQL vers PHP
     */
    public function parseValue($value)
    {
        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Error('Invalid JSON value: ' . $e->getMessage());
            }
        }

        if (is_array($value) || is_object($value) || is_null($value) || is_bool($value) || is_numeric($value)) {
            return $value;
        }

        throw new Error('JSON value must be a valid JSON string or value, got: ' . Utils::printSafe($value));
    }

    /**
     * Parse un literal depuis le AST GraphQL
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (property_exists($valueNode, 'value')) {
            return $this->parseValue($valueNode->value);
        }

        // Pour les objets/arrays complexes dans les queries
        if (property_exists($valueNode, 'fields')) {
            $result = [];
            foreach ($valueNode->fields as $field) {
                $result[$field->name->value] = $this->parseLiteral($field->value, $variables);
            }
            return $result;
        }

        if (property_exists($valueNode, 'values')) {
            $result = [];
            foreach ($valueNode->values as $value) {
                $result[] = $this->parseLiteral($value, $variables);
            }
            return $result;
        }

        throw new Error('Cannot parse JSON literal');
    }
}
