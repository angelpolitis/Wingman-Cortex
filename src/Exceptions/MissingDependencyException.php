<?php
    /*/
     * Project Name:    Wingman — Cortex — Missing Dependency Exception
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
     * An exception thrown when an operation requires an optional Wingman package that is not
     * currently installed. The most common case is calling `setEmitter()` when the
     * `Wingman/Corvus` package is absent.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class MissingDependencyException extends RuntimeException implements Exception {
        /**
         * The Composer package name of the missing dependency.
         * @var string
         */
        protected string $package;

        /**
         * Creates a new missing dependency exception.
         * @param string $package The Composer package name that is required but not installed
         *                        (e.g., `"Wingman/Corvus"`).
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $package, ?Throwable $previous = null) {
            parent::__construct(
                "Required package '{$package}' is not installed.",
                0,
                $previous
            );

            $this->package = $package;
        }

        /**
         * Gets the Composer package name of the missing dependency.
         * @return string The package name.
         */
        public function getPackage () : string {
            return $this->package;
        }
    }
?>