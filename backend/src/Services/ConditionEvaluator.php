<?php

namespace App\Services;

/**
 * Evaluates a set of dynamic conditions (e.g. "amount > 10000",
 * "department == Finance") against a request's data payload.
 *
 * Conditions are stored as JSON: an array of
 *   { "field": "amount", "operator": ">", "value": 10000 }
 * All conditions in a step are combined with AND semantics, which covers
 * the challenge's examples directly. An empty condition list always
 * evaluates to true (the step always applies).
 *
 * This is the piece that lets the engine stay fully data-driven: no
 * workflow-specific logic is ever hardcoded in PHP.
 */
class ConditionEvaluator
{
    public const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'contains', 'in'];

    public static function evaluate(array $conditions, array $data): bool
    {
        foreach ($conditions as $condition) {
            if (!self::evaluateSingle($condition, $data)) {
                return false;
            }
        }
        return true;
    }

    private static function evaluateSingle(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $expected = $condition['value'] ?? null;

        if ($field === null || !array_key_exists($field, $data)) {
            // A condition referencing a field the request doesn't supply
            // cannot be satisfied; fail closed rather than silently pass.
            return false;
        }

        $actual = $data[$field];

        // Attempt numeric comparison when both sides look numeric.
        $bothNumeric = is_numeric($actual) && is_numeric($expected);

        switch ($operator) {
            case '=':
            case '==':
                return $bothNumeric
                    ? (float) $actual === (float) $expected
                    : self::normalize($actual) === self::normalize($expected);
            case '!=':
                return $bothNumeric
                    ? (float) $actual !== (float) $expected
                    : self::normalize($actual) !== self::normalize($expected);
            case '>':
                return $bothNumeric && (float) $actual > (float) $expected;
            case '>=':
                return $bothNumeric && (float) $actual >= (float) $expected;
            case '<':
                return $bothNumeric && (float) $actual < (float) $expected;
            case '<=':
                return $bothNumeric && (float) $actual <= (float) $expected;
            case 'contains':
                return is_string($actual) && str_contains(
                    strtolower($actual),
                    strtolower((string) $expected)
                );
            case 'in':
                $options = is_array($expected) ? $expected : [$expected];
                return in_array(self::normalize($actual), array_map([self::class, 'normalize'], $options), true);
            default:
                return false;
        }
    }

    private static function normalize($value): string
    {
        return strtolower(trim((string) $value));
    }
}
