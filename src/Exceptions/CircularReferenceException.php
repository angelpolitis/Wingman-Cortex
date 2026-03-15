<?php
    /*/
     * Project Name:    Wingman — Cortex — Circular Reference Exception
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
     * An exception thrown when the interpolator detects a circular variable reference — that is,
     * a chain of `${key}` references that eventually points back to itself, making resolution
     * impossible without infinite recursion.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CircularReferenceException extends RuntimeException implements Exception {
        /**
         * The ordered list of variable references that formed the cycle.
         * @var string[]
         */
        protected array $chain;

        /**
         * Creates a new circular reference exception.
         * @param string[] $chain The ordered sequence of variable names that formed the cycle.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (array $chain, ?Throwable $previous = null) {
            parent::__construct(
                "Circular reference detected: " . implode(" → ", $chain) . ".",
                0,
                $previous
            );

            $this->chain = $chain;
        }

        /**
         * Gets the ordered sequence of variable references that formed the circular chain.
         * @return string[] The reference chain.
         */
        public function getChain () : array {
            return $this->chain;
        }
    }
?>