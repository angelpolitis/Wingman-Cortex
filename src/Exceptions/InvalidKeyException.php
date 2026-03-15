<?php
    /*/
     * Project Name:    Wingman — Cortex — Invalid Key Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Exceptions namespace.
    namespace Wingman\Cortex\Exceptions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Throwable;
    use Wingman\Cortex\Interfaces\Exception;

    /**
     * An exception thrown when a key or variable expression string fails to parse because it does
     * not conform to the expected syntax (e.g., empty name segment, missing required components,
     * or illegal characters in the expression).
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidKeyException extends InvalidArgumentException implements Exception {
        /**
         * The raw expression string that failed to parse.
         * @var string
         */
        protected string $expression;

        /**
         * A short description of why the expression is invalid.
         * @var string
         */
        protected string $reason;

        /**
         * Creates a new invalid key exception.
         * @param string $expression The raw expression that caused the failure.
         * @param string $reason A short human-readable explanation of the parse failure.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $expression, string $reason, ?Throwable $previous = null) {
            parent::__construct(
                "Invalid key expression '{$expression}': {$reason}.",
                0,
                $previous
            );

            $this->expression = $expression;
            $this->reason     = $reason;
        }

        /**
         * Gets the raw expression string that failed to parse.
         * @return string The invalid expression.
         */
        public function getExpression () : string {
            return $this->expression;
        }

        /**
         * Gets the human-readable reason why the expression is invalid.
         * @return string The reason.
         */
        public function getReason () : string {
            return $this->reason;
        }
    }
?>