<?php
    /*/
     * Project Name:    Wingman — Cortex — Frozen Configuration Exception
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
     * An exception thrown when a mutation is attempted on a configuration — or a specific namespace
     * within one — that has been frozen via `freeze()` or `freezeNamespace()`.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class FrozenConfigurationException extends RuntimeException implements Exception {
        /**
         * The namespace that was frozen, or `null` when the entire configuration is frozen.
         * @var string|null
         */
        protected ?string $namespace;

        /**
         * The key that the write was attempted on.
         * @var string
         */
        protected string $key;

        /**
         * Creates a new frozen configuration exception.
         * @param string $key The key that the write was attempted on.
         * @param string|null $namespace The frozen namespace, or `null` if the whole configuration is frozen.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $key, ?string $namespace = null, ?Throwable $previous = null) {
            $target = $namespace !== null
                ? "namespace '{$namespace}'"
                : "configuration";

            parent::__construct("Cannot write key '{$key}': the {$target} is frozen.", 0, $previous);

            $this->key = $key;
            $this->namespace = $namespace;
        }

        /**
         * Gets the key that the write was attempted on.
         * @return string The key.
         */
        public function getKey () : string {
            return $this->key;
        }

        /**
         * Gets the frozen namespace, or `null` if the entire configuration was frozen.
         * @return string|null The namespace, or `null`.
         */
        public function getNamespace () : ?string {
            return $this->namespace;
        }
    }
?>