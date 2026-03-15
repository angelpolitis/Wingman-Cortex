<?php
    /*/
     * Project Name:    Wingman — Cortex — Typed Accessors Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex traits namespace.
    namespace Wingman\Cortex\Traits;

    # Import the following classes to the current scope.
    use UnitEnum;
    use Wingman\Cortex\Exceptions\TypeMismatchException;
    use Wingman\Cortex\Exceptions\UndefinedVariableException;
    use Wingman\Cortex\Variable;

    /**
     * Provides the type-asserting getter surface for `Configuration`.
     *
     * Encapsulates the private `getTyped()` helper and all public typed read methods
     * (`getArray`, `getBool`, `getFloat`, `getInt`, `getString`) as well as the
     * multi-key projections `getMany()` and `only()`.
     *
     * Every method delegates the actual key resolution to `get()` / `getRaw()` on the
     * owning `Configuration`; this trait owns only the type-validation layer.
     *
     * @package Wingman\Cortex\Traits
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    trait HasTypedAccessors {
        /**
         * Retrieves and validates a typed value from the configuration, handling the absent-key and
         * type-mismatch cases in a single place so that every public typed accessor stays lean.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param string                   $type    One of: `"string"`, `"int"`, `"float"`, `"bool"`, `"array"`.
         * @param mixed                    $default The caller-supplied default, or `null` if none was provided.
         * @return mixed The validated value, coerced to float when `$type === "float"` and the stored value is an int.
         * @throws UndefinedVariableException If the key is absent and no default was given.
         * @throws TypeMismatchException      If the value exists but does not match `$type`.
         */
        private function getTyped (string|UnitEnum|Variable $key, string $type, mixed $default) : mixed {
            $value = $this->get($key);
            $label = $key instanceof Variable ? $key->getName() : (string) $key;

            if ($value === null) {
                if ($default !== null) {
                    return $default;
                }

                throw new UndefinedVariableException($label, null, $this->name);
            }

            $valid = match ($type) {
                "string" => is_string($value),
                "int" => is_int($value),
                "float" => is_float($value) || is_int($value),
                "bool" => is_bool($value),
                "array" => is_array($value)
            };

            if (!$valid) {
                throw new TypeMismatchException($label, $type, get_debug_type($value));
            }

            return $type === "float" ? (float) $value : $value;
        }

        /**
         * Gets a value from the configuration and asserts it is an array.
         * If the key is absent and `$default` is provided, the default is returned. If no default is
         * given, an `UndefinedVariableException` is thrown. If the value is present but not an array,
         * a `TypeMismatchException` is thrown.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param array|null               $default An optional fallback returned when the key is absent.
         * @return array The array value stored at `$key`.
         * @throws TypeMismatchException      If the value is present but not an array.
         * @throws UndefinedVariableException If the key is absent and no default was supplied.
         */
        public function getArray (string|UnitEnum|Variable $key, ?array $default = null) : array {
            return $this->getTyped($key, "array", $default);
        }

        /**
         * Gets a value from the configuration and asserts it is a boolean.
         * If the key is absent and `$default` is provided, the default is returned. If no default is
         * given, an `UndefinedVariableException` is thrown. If the value is present but not a boolean,
         * a `TypeMismatchException` is thrown.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param bool|null                $default An optional fallback returned when the key is absent.
         * @return bool The boolean value stored at `$key`.
         * @throws TypeMismatchException      If the value is present but not a boolean.
         * @throws UndefinedVariableException If the key is absent and no default was supplied.
         */
        public function getBool (string|UnitEnum|Variable $key, ?bool $default = null) : bool {
            return $this->getTyped($key, "bool", $default);
        }

        /**
         * Gets a value from the configuration and asserts it is a float (or a promotable int).
         * If the key is absent and `$default` is provided, the default is returned. If no default is
         * given, an `UndefinedVariableException` is thrown. Integer values are silently promoted to
         * float since PHP treats them as numerically equivalent; all other non-float types throw a
         * `TypeMismatchException`.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param float|null               $default An optional fallback returned when the key is absent.
         * @return float The float value stored at `$key`.
         * @throws TypeMismatchException      If the value is present but cannot be treated as a float.
         * @throws UndefinedVariableException If the key is absent and no default was supplied.
         */
        public function getFloat (string|UnitEnum|Variable $key, ?float $default = null) : float {
            return $this->getTyped($key, "float", $default);
        }

        /**
         * Gets a value from the configuration and asserts it is an integer.
         * If the key is absent and `$default` is provided, the default is returned. If no default is
         * given, an `UndefinedVariableException` is thrown. Float values are NOT silently truncated;
         * only a strictly-typed PHP int passes. If the value is present but not an int, a
         * `TypeMismatchException` is thrown.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param int|null                 $default An optional fallback returned when the key is absent.
         * @return int The integer value stored at `$key`.
         * @throws TypeMismatchException      If the value is present but not an integer.
         * @throws UndefinedVariableException If the key is absent and no default was supplied.
         */
        public function getInt (string|UnitEnum|Variable $key, ?int $default = null) : int {
            return $this->getTyped($key, "int", $default);
        }

        /**
         * Retrieves multiple keys in a single call, returning a keyed array.
         * Each entry in `$keys` is passed to `get()` independently; the input key string is
         * preserved as-is in the output array. Pass `$default` to use the same default for every
         * absent key, or omit it to receive `null` for missing keys.
         * @param array<string|UnitEnum|Variable> $keys    The keys to retrieve.
         * @param mixed                           $default The default value for any absent key (default: `null`).
         * @return array<string, mixed> A map of `$key => resolved value`.
         */
        public function getMany (array $keys, mixed $default = null) : array {
            $results = [];

            foreach ($keys as $key) {
                $label = $key instanceof Variable ? (string) $key : (string) $this->normaliseKey($key);
                $results[$label] = $this->get($key, $default);
            }

            return $results;
        }

        /**
         * Gets a value from the configuration and asserts it is a string.
         * If the key is absent and `$default` is provided, the default is returned. If no default is
         * given, an `UndefinedVariableException` is thrown. If the value is present but not a string,
         * a `TypeMismatchException` is thrown.
         * @param string|UnitEnum|Variable $key     The key to retrieve.
         * @param string|null              $default An optional fallback returned when the key is absent.
         * @return string The string value stored at `$key`.
         * @throws TypeMismatchException      If the value is present but not a string.
         * @throws UndefinedVariableException If the key is absent and no default was supplied.
         */
        public function getString (string|UnitEnum|Variable $key, ?string $default = null) : string {
            return $this->getTyped($key, 'string', $default);
        }

        /**
         * Returns a flat keyed projection of the specified configuration keys.
         * Unlike `getMany()`, the output keys are normalised to the bare segment path (environment
         * and namespace prefixes are stripped), making the result suitable for passing directly
         * as named arguments or constructor options to third-party code.
         * @param array<string|UnitEnum|Variable> $keys    The keys to project.
         * @param mixed                           $default The default value for any absent key (default: `null`).
         * @return array<string, mixed> A map of `bare_segment_path => resolved value`.
         */
        public function only (array $keys, mixed $default = null) : array {
            $results = [];

            foreach ($keys as $key) {
                $variable = $this->normaliseKey($key);
                $results[$variable->getName()] = $this->getRaw($variable, $default);
            }

            return $results;
        }
    }
?>