<?php
    /*/
     * Project Name:    Wingman — Cortex — Access Denied Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when an operation attempts to read from or write to a configuration,
     * registry, or namespace that it does not belong to — for example, resolving a variable that
     * carries an environment name different to the current configuration, or merging data from
     * a foreign environment into an unrelated configuration instance.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class AccessDeniedException extends RuntimeException implements Exception {
        /**
         * The name of the current configuration or registry that rejected the access attempt.
         * @var string|null
         */
        protected ?string $current;

        /**
         * The environment, namespace, or context that was requested but is not permitted.
         * @var string
         */
        protected string $requested;

        /**
         * Creates a new access denied exception.
         * @param string $requested The requested environment, namespace, or context that was denied.
         * @param string|null $current The name of the current configuration or registry, if known.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $requested, ?string $current = null, ?Throwable $previous = null) {
            $message = $current !== null
                ? "Access denied: cannot access '{$requested}' from configuration '{$current}'."
                : "Access denied: cannot access '{$requested}' from the current configuration.";

            parent::__construct($message, 0, $previous);

            $this->current   = $current;
            $this->requested = $requested;
        }

        /**
         * Gets the name of the current configuration or registry that rejected the access.
         * @return string|null The current name, or `null` if not known.
         */
        public function getCurrent () : ?string {
            return $this->current;
        }

        /**
         * Gets the environment, namespace, or context that was requested but denied.
         * @return string The requested context.
         */
        public function getRequested () : string {
            return $this->requested;
        }
    }
?>