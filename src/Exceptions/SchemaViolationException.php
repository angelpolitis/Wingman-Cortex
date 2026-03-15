<?php
    /*/
     * Project Name:    Wingman — Cortex — Schema Violation Exception
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
     * Thrown when a single configuration key fails validation against its declared schema expression.
     * This exception is collected per key by `ConfigurationSchema::validate()` and is re-thrown wrapped
     * inside a `ConfigurationSchemaException` by `ConfigurationSchema::assert()`.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SchemaViolationException extends RuntimeException implements Exception {
        /**
         * The array of human-readable error messages describing each individual validation failure.
         * @var string[]
         */
        protected array $errors;

        /**
         * The Verix DSL expression against which the value was validated.
         * @var string
         */
        protected string $expression;

        /**
         * The configuration key whose value failed validation.
         * @var string
         */
        protected string $key;

        /**
         * Creates a new schema violation exception.
         * @param string $key The configuration key that failed validation.
         * @param string $expression The Verix DSL expression the value was validated against.
         * @param string[] $errors Zero or more human-readable error messages describing each failure.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $key, string $expression, array $errors = [], ?Throwable $previous = null) {
            $this->key = $key;
            $this->expression = $expression;
            $this->errors = $errors;

            $suffix = !empty($errors) ? ": " . implode("; ", $errors) : ".";
            parent::__construct(
                "Configuration key '{$key}' failed validation against schema '{$expression}'{$suffix}",
                0,
                $previous
            );
        }

        /**
         * Gets the human-readable error messages describing the individual validation failures.
         * @return string[] The error messages.
         */
        public function getErrors () : array {
            return $this->errors;
        }

        /**
         * Gets the Verix DSL expression against which the value was validated.
         * @return string The expression.
         */
        public function getExpression () : string {
            return $this->expression;
        }

        /**
         * Gets the configuration key that failed validation.
         * @return string The key.
         */
        public function getKey () : string {
            return $this->key;
        }
    }
?>