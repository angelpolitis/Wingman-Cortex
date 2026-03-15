<?php
    /*/
     * Project Name:    Wingman — Cortex — Invalid Source Exception
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
     * An exception thrown by the `Loader` when the provided source is not a valid file path,
     * directory path, or supported input type. This typically indicates a misconfigured
     * import call or an incorrect path passed at boot time.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidSourceException extends InvalidArgumentException implements Exception {
        /**
         * The source value that was rejected by the loader.
         * @var mixed
         */
        protected mixed $source;

        /**
         * Creates a new invalid source exception.
         * @param mixed $source The value that was rejected as a configuration source.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (mixed $source, ?Throwable $previous = null) {
            $label = is_string($source) ? "'{$source}'" : gettype($source);

            parent::__construct(
                "Invalid configuration source: {$label} is not a readable file or directory.",
                0,
                $previous
            );

            $this->source = $source;
        }

        /**
         * Gets the source value that was rejected by the loader.
         * @return mixed The invalid source.
         */
        public function getSource () : mixed {
            return $this->source;
        }
    }
?>