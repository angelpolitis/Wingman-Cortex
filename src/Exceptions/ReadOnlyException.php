<?php
    /*/
     * Project Name:    Wingman — Cortex — Read-Only Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use LogicException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when a write operation is attempted on a read-only structure.
     * The primary use case is `Scope`, whose `ArrayAccess` set and unset implementations
     * are intentionally unsupported since scopes are live views into a `Configuration`, not
     * independent stores.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ReadOnlyException extends LogicException implements Exception {
        /**
         * Creates a new read-only exception.
         * @param string $context A short description of the read-only structure (e.g., "Configuration scope").
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $context, ?Throwable $previous = null) {
            parent::__construct("$context is read-only.", 0, $previous);
        }
    }
?>