<?php
    /*/
     * Project Name:    Wingman — Cortex — Type Mismatch Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when a configuration value does not match the expected type demanded by a
     * typed accessor such as `getString()`, `getInt()`, `getFloat()`, `getBool()`, or `getArray()`.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TypeMismatchException extends RuntimeException implements Exception {
        /**
         * The actual type of the value that was found.
         * @var string
         */
        protected string $actual;

        /**
         * The expected type of the value.
         * @var string
         */
        protected string $expected;

        /**
         * The configuration key whose value had the wrong type.
         * @var string
         */
        protected string $key;

        /**
         * Creates a new type mismatch exception.
         * @param string $key The configuration key whose value did not match.
         * @param string $expected The expected type name (e.g., "string", "int").
         * @param string $actual The actual PHP type of the value (e.g., "bool", "array").
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $key, string $expected, string $actual, ?Throwable $previous = null) {
            parent::__construct(
                "Configuration key '{$key}' expected {$expected}, got {$actual}.",
                0,
                $previous
            );
            $this->actual = $actual;
            $this->expected = $expected;
            $this->key = $key;
        }

        /**
         * Gets the actual PHP type of the value that was found.
         * @return string The actual type.
         */
        public function getActual () : string {
            return $this->actual;
        }

        /**
         * Gets the expected type name.
         * @return string The expected type.
         */
        public function getExpected () : string {
            return $this->expected;
        }

        /**
         * Gets the configuration key that triggered the exception.
         * @return string The key.
         */
        public function getKey () : string {
            return $this->key;
        }
    }
?>