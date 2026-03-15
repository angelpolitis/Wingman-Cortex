<?php
    /*/
     * Project Name:    Wingman — Cortex — Scope
     * Created by:      Angel Politis
     * Creation Date:   Feb 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use ArrayAccess;
    use ArrayIterator;
    use Countable;
    use IteratorAggregate;
    use Traversable;
    use UnitEnum;
    use Wingman\Cortex\Exceptions\ReadOnlyException;

    /**
     * A class that represents a variable scope during interpolation.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Scope implements IteratorAggregate, Countable, ArrayAccess {
        /**
         * The configuration that owns a scope.
         * @var Configuration
         */
        protected Configuration $owner;

        /**
         * The dot-delimited path representing the current scope (e.g., "database.connections.mysql").
         * @var string
         */
        protected string $path;

        /**
         * The namespace of the variables in this scope, used for resolving variables.
         * @var string
         */
        protected string $namespace;

        /**
         * Constructs a new scope.
         * @param Configuration $owner The configuration instance that owns this scope.
         * @param string $path The dot-delimited path representing the current scope (e.g., "database.connections.mysql").
         * @param string $namespace The namespace of the variables in this scope, used for resolving variables.
         */
        public function __construct (Configuration $owner, string $path, string $namespace) {
            $this->owner = $owner;
            $this->path = $path;
            $this->namespace = $namespace;
        }

        /**
         * Accesses variables in a scope as properties (e.g., $scope->variable).
         * @param string $name The name of the variable to access.
         * @return mixed The value of the variable, or `null` if it doesn't exist.
         */
        public function __get (string $name) : mixed {
            return $this->get($name);
        }

        /**
         * Converts a scope to a string by retrieving the raw value of the current path.
         * If the value is scalar, it is returned as a string; otherwise, an empty string is returned.
         * This allows using `echo $scope` to output the value of the current scope.
         * @return string The string representation of the current scope's value.
         */
        public function __toString () : string {
            $value = $this->owner->getRaw(new Variable($this->path, $this->namespace, $this->owner->getName()));
            return is_scalar($value) ? (string) $value : "";
        }

        /**
         * Resolves a variable key relative to the current scope, taking into account the scope's path and namespace.
         * @param string|UnitEnum|Variable $key The variable key to resolve (e.g., "host" or "database.host").
         * @return Variable A new Variable instance representing the resolved variable key, with the scope's namespace and environment applied.
         */
        protected function getRelativeVariable (string|UnitEnum|Variable $key) : Variable {
            $variable = Registry::normaliseKey($key, $this->owner->getSegmentDelimiter());
            $relativeName = $variable->getName();
            $delimiter = $this->owner->getSegmentDelimiter();

            $fullPath = ($this->path === "") 
                ? $relativeName 
                : $this->path . $delimiter . $relativeName;

            return new Variable($fullPath, $this->namespace, $this->owner->getName());
        }
        
        /**
         * Counts the number of variables in a scope if it's an array, or returns 0 if it's not an array.
         * @return int The number of variables in the scope.
         */
        public function count () : int {
            $data = $this->owner->getRaw(new Variable($this->path, $this->namespace, $this->owner->getName()));
            return is_array($data) ? count($data) : 0;
        }

        /**
         * Gets an iterator for iterating over the variables in the current scope.
         * This allows using `foreach ($scope as $key => $value)` to iterate over the variables in the scope.
         * @return Traversable An iterator that yields key-value pairs of variables in the current scope.
         */
        public function getIterator () : Traversable {
            $data = $this->owner->getRaw(new Variable($this->path, $this->namespace, $this->owner->getName()));

            if (!is_array($data)) return new ArrayIterator([]);

            foreach ($data as $key => $value) {
                yield $key => $this->get($key);
            }
        }

        /**
         * Gets a variable from the scope, resolving it relative to the scope's path and namespace.
         * @param string|UnitEnum|Variable $key The name of the variable to get.
         * @param array $implicitNamespaces Optional additional namespaces to consider for interpolation lookups (e.g., ["global", "env"]).
         * @return mixed The value of the variable, or `null` if it doesn't exist.
         */
        public function get (string|UnitEnum|Variable $key) : mixed {
            return $this->owner->get($this->getRelativeVariable($key));
        }

        /**
         * Gets the namespace of a scope, which is used for resolving variables.
         * @return string The namespace of the scope.
         */
        public function getNamespace () : string {
            return $this->namespace;
        }

        /**
         * Gets a raw value (uninterpolated) relative to the scope.
         * @param string|UnitEnum|Variable $key The name of the variable to get.
         * @return mixed The raw value of the variable, or `null` if it doesn't exist.
         */
        public function getRaw (string|UnitEnum|Variable $key): mixed {
            return $this->owner->getRaw($this->getRelativeVariable($key));
        }

        /**
         * Checks whether a variable exists in the scope.
         * @param string|UnitEnum|Variable $key The name of the variable to check.
         * @return bool Whether the variable exists in the scope.
         */
        public function has (string|UnitEnum|Variable $key) : bool {
            return $this->owner->has($this->getRelativeVariable($key));
        }

        /**
         * Checks whether a variable exists in the scope using array access (e.g., isset($scope['variable'])).
         * @param mixed $offset The name of the variable to check.
         * @return bool Whether the variable exists in the scope.
         */
        public function offsetExists (mixed $offset) : bool {
            return $this->has((string) $offset);
        }

        /**
         * Supports array access to variables in the scope (e.g., $scope['variable']).
         * @param mixed $offset The name of the variable to access.
         * @return mixed The value of the variable, or `null` if it doesn't exist.
         */
        public function offsetGet (mixed $offset) : mixed {
            return $this->get((string) $offset);
        }

        /**
         * Configurations are typically read-only through Scopes.
         * @param mixed $offset The name of the variable to set.
         * @param mixed $value The value to set.
         * @throws ReadOnlyException Always, as scopes are read-only.
         */
        public function offsetSet (mixed $offset, mixed $value) : void {
            throw new ReadOnlyException("Configuration scope");
        }

        /**
         * Unsetting variables in scopes is not allowed.
         * @param mixed $offset The name of the variable to unset.
         * @throws ReadOnlyException Always, as scopes are read-only.
         */
        public function offsetUnset (mixed $offset) : void {
            throw new ReadOnlyException("Configuration scope");
        }
    }
?>