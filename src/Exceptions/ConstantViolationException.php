<?php
    /*/
     * Project Name:    Wingman — Cortex — Constant Violation Exception
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
     * An exception thrown when a write operation targets a key that has been locked as a constant
     * via `setConst()`, or when `setConst()` itself is called on a key that is already a constant.
     *
     * The `$redefine` flag distinguishes the two scenarios:
     * - `false` (default) — an ordinary `set()` or `mergeFlat()` attempted to overwrite a constant.
     * - `true`            — `setConst()` tried to redefine an already-registered constant.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConstantViolationException extends RuntimeException implements Exception {
        /**
         * The fully-qualified key (namespace + delimiter + name) of the constant.
         * @var string
         */
        protected string $key;

        /**
         * Whether this violation is a redefinition attempt (true) or an overwrite attempt (false).
         * @var bool
         */
        protected bool $redefine;

        /**
         * Creates a new constant violation exception.
         * @param string $key The fully-qualified constant key that was violated.
         * @param bool $redefine `true` if `setConst()` tried to redefine an existing constant;
         *                       `false` if an ordinary write tried to overwrite one.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $key, bool $redefine = false, ?Throwable $previous = null) {
            $message = $redefine
                ? "Constant '{$key}' is already defined and cannot be redefined."
                : "Cannot override constant '{$key}'.";

            parent::__construct($message, 0, $previous);

            $this->key      = $key;
            $this->redefine = $redefine;
        }

        /**
         * Gets the fully-qualified constant key that was violated.
         * @return string The constant key.
         */
        public function getKey () : string {
            return $this->key;
        }

        /**
         * Returns `true` if this exception represents a redefinition attempt via `setConst()`,
         * or `false` if it represents an overwrite attempt via `set()` or `mergeFlat()`.
         * @return bool Whether this is a redefinition violation.
         */
        public function isRedefine () : bool {
            return $this->redefine;
        }
    }
?>