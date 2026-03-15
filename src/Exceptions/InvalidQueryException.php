<?php
    /*/
     * Project Name:    Wingman — Cortex — Invalid Query Exception
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
     * An exception thrown when a caller passes a multi-value or wildcard query pattern to `get()`
     * or `getRaw()`, which only support single-key resolution. Multi-value queries must be routed
     * through `search()` instead.
     * @package Wingman\Cortex\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidQueryException extends InvalidArgumentException implements Exception {
        /**
         * The query string that triggered the exception.
         * @var string
         */
        protected string $query;

        /**
         * Creates a new invalid query exception.
         * @param string $query The query string that was rejected.
         * @param Throwable|null $previous The previous throwable, if any.
         */
        public function __construct (string $query, ?Throwable $previous = null) {
            parent::__construct(
                "Multi-value queries must be performed via search(); got '{$query}'.",
                0,
                $previous
            );

            $this->query = $query;
        }

        /**
         * Gets the query string that was rejected.
         * @return string The invalid query.
         */
        public function getQuery () : string {
            return $this->query;
        }
    }
?>