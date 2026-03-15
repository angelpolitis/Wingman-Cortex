<?php
    /*/
     * Project Name:    Wingman — Cortex — Container Overwrite Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when a `set()` call attempts to replace an existing nested container
     * (an associative-array node in the data tree) with a scalar value without the `$force` flag.
     * Forcing the overwrite is permitted but must be explicit to prevent accidental data loss.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ContainerOverwriteException extends RuntimeException implements Exception {
        /**
         * The key whose container node the write attempted to overwrite.
         * @var string
         */
        protected string $key;

        /**
         * Creates a new container overwrite exception.
         * @param string $key The key path at which the container exists.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $key, ?Throwable $previous = null) {
            parent::__construct(
                "Cannot overwrite the container at '{$key}' with a scalar value without the force flag.",
                0,
                $previous
            );

            $this->key = $key;
        }

        /**
         * Gets the key path at which the container exists.
         * @return string The key.
         */
        public function getKey () : string {
            return $this->key;
        }
    }
?>