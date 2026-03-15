<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Schema Exception
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
     * Thrown by `ConfigurationSchema::assert()` when one or more keys fail their declared schema.
     * Wraps the full set of per-key `SchemaViolationException` instances so that callers can inspect
     * every failure in a single catch block rather than dealing with interruptions mid-validation.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationSchemaException extends RuntimeException implements Exception {
        /**
         * The per-key violations, indexed by configuration key.
         * @var array<string, SchemaViolationException>
         */
        protected array $violations;

        /**
         * Creates a new configuration schema exception.
         * @param array<string, SchemaViolationException> $violations A map of configuration key to its violation.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (array $violations, ?Throwable $previous = null) {
            $this->violations = $violations;

            $count = count($violations);
            $keys  = implode("', '", array_keys($violations));
            parent::__construct(
                "Configuration schema validation failed for {$count} key(s): '{$keys}'.",
                0,
                $previous
            );
        }

        /**
         * Gets the per-key violations as a map of configuration key to its `SchemaViolationException`.
         * @return array<string, SchemaViolationException> The violations.
         */
        public function getViolations () : array {
            return $this->violations;
        }
    }
?>