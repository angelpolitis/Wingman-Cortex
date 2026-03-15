<?php
    /*/
     * Project Name:    Wingman — Cortex — Undefined Variable Exception
     * Created by:      Angel Politis
     * Creation Date:   Feb 16 2026
     * Last Modified:   Feb 16 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when a variable is accessed before it is defined.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class UndefinedVariableException extends RuntimeException implements Exception {
        /**
         * Creates a new undefined variable exception.
         * @param string $variable The name of the undefined variable.
         * @param string|null $namespace The namespace of the undefined variable, if any.
         * @param string|null $environment The environment of the undefined variable, if any.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $variable, ?string $namespace = null, ?string $environment = null, ?Throwable $previous = null) {
            $message = "Variable '$variable' is not defined"
                . ($namespace ? " within namespace '$namespace'" : "")
                . ($environment ? " in environment '$environment'" : "") . ".";
            parent::__construct($message, 0, $previous);
        }
    }
?>