<?php
    /*/
     * Project Name:    Wingman — Cortex — Native Validator
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Validation namespace.
    namespace Wingman\Cortex\Validation;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Interfaces\ValidatorInterface;

    /**
     * A zero-dependency validator that covers the subset of Verix DSL expressions expressible through
     * PHP's own type system. Use this validator when Verix is not available or when only basic type
     * checks are needed.
     *
     * Supported expressions:
     * - Scalars:    `string`, `int` / `integer`, `float` / `double` / `number`, `bool` / `boolean`
     * - Structured: `array`, `object`
     * - Special:    `null`, `mixed` / `any`
     * - Nullable:   `?string` — shorthand for `string|null`
     * - Union:      `string|null`, `int|float`, etc.
     * - Class/interface: any fully-qualified or bare class name — resolved via `instanceof`
     *
     * Anything more advanced (range constraints, struct shapes, regex formats, etc.) requires the
     * Verix bridge. Expressions that cannot be interpreted natively are returned as an error suggesting
     * the Verix bridge be installed.
     *
     * @package Wingman\Cortex\Validation
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class NativeValidator implements ValidatorInterface {
        /**
         * Checks whether a single primitive or class name expression is satisfied by `$value`.
         * @param string $token A single, trimmed, non-union expression.
         * @param mixed $value The value to test.
         * @return string[] An array of error messages; empty if the value is valid.
         */
        private function checkToken (string $token, mixed $value) : array {
            $lower = strtolower($token);

            if (in_array($lower, ["mixed", "any"], true)) {
                return [];
            }

            $passed = match ($lower) {
                "string"           => is_string($value),
                "int", "integer"   => is_int($value),
                "float", "double",
                "number"           => is_float($value) || is_int($value),
                "bool", "boolean"  => is_bool($value),
                "array"            => is_array($value),
                "object"           => is_object($value),
                "null"             => is_null($value),
                default            => null
            };

            if ($passed !== null) {
                return $passed ? [] : ["Expected {$token}, got " . get_debug_type($value) . "."];
            }

            if (class_exists($token) || interface_exists($token)) {
                return ($value instanceof $token)
                    ? []
                    : ["Expected instance of {$token}, got " . get_debug_type($value) . "."];
            }

            return ["Expression '{$token}' requires the Verix bridge for full validation."];
        }

        /**
         * Validates a value against a type expression and returns all constraint violations.
         * Handles nullable shorthand (`?type`) and union types (`A|B|C`) before delegating individual
         * tokens to `checkToken()`.
         * @param string $expression The Verix DSL expression.
         * @param mixed $value The value to validate.
         * @return string[] An array of error messages; empty if the value is valid.
         */
        public function check (string $expression, mixed $value) : array {
            $expression = trim($expression);

            if (str_starts_with($expression, '?')) {
                $expression = substr($expression, 1) . '|null';
            }

            $members = array_map('trim', explode('|', $expression));

            if (count($members) > 1) {
                foreach ($members as $member) {
                    if (empty($this->check($member, $value))) {
                        return [];
                    }
                }
                return ["Expected " . implode('|', $members) . ", got " . get_debug_type($value) . "."];
            }

            return $this->checkToken($expression, $value);
        }
    }
?>