<?php
    /*/
     * Project Name:    Wingman — Cortex — Registry
     * Created by:      Angel Politis
     * Creation Date:   Feb 14 2026
     * Last Modified:   Feb 16 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use BackedEnum;
    use UnitEnum;
    use Wingman\Cortex\Attributes\Configurable;
    use Wingman\Cortex\Exceptions\AccessDeniedException;
    use Wingman\Cortex\Exceptions\ContainerOverwriteException;

    /**
     * Internal key-parsing and key-normalisation utility for the Cortex package.
     *
     * Provides the static `normaliseKeyWithCache()` helper consumed exclusively by
     * `Configuration::normaliseKey()`. The cache is keyed by `$delimiter . "\x00" . $key` to
     * prevent cross-delimiter collisions between configurations that use different segment
     * delimiters. A per-delimiter `QueryParser` pool (`static $parsers`) is maintained so that
     * each delimiter variant is initialised at most once per process.
     *
     * This class also serves as the base for `Bucket`, which extends it to add namespace-scoped
     * storage. Outside that inheritance relationship, **no other code should depend on this class
     * directly**; it is an implementation detail of Cortex and may change without notice.
     *
     * @package Wingman\Cortex
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     * @internal
     */
    class Registry {
        /**
         * Maximum number of entries the key cache may hold. Once the limit is reached, the
         * least-recently-used entry is evicted before storing the new one, bounding memory
         * use in long-lived processes (FPM with high key cardinality, Octane, ReactPHP, etc.).
         * Configurable via `Configuration::hydrate(Registry::class)` with a source declaring
         * `cortex.registry.maxKeyCacheSize`, or programmatically via `setMaxKeyCacheSize()`.
         * @var int
         */
        #[Configurable("cortex.registry.maxKeyCacheSize", "Maximum entries in the normalised-key LRU cache.", "int<min=1>", 1000)]
        private static int $maxKeyCacheSize = 1000;

        /**
         * The cache for normalised keys to avoid redundant parsing.
         * Keys are prefixed with the segment delimiter to prevent cross-delimiter collisions between
         * configurations that use different delimiters.
         * @var array<string, Variable>
         */
        private static array $keyCache = [];

        /**
         * The name of the registry.
         * @var string
         */
        protected string $name;

        /**
         * The data contained in the registry.
         * @var array
         */
        protected array $data = [];

        /**
         * The namespace delimiter used for resolving variables.
         * @var string|null
         */
        protected ?string $namespaceDelimiter = null;

        /**
         * The segment delimiter used for resolving variables.
         * @var string|null
         */
        protected ?string $segmentDelimiter = null;

        /**
         * The prefix to apply to all keys when exporting.
         * @var string
         */
        protected string $prefix = "";

        /**
         * Constructs a new registry with the given name and data.
         * @param string $name The name of the registry.
         * @param array $data The data contained in the registry.
         * @param array{"segmentDelimiter"?: string, "namespaceDelimiter"?: string} $options The options for the registry.
         */
        public function __construct (string $name, array $data = [], array $options = []) {
            $this->name = $name;
            $this->data = $data;
            $this->segmentDelimiter = $options["segmentDelimiter"] ?? QueryParser::DEFAULT_SEGMENT_DELIMITER;
            $this->namespaceDelimiter = $options["namespaceDelimiter"] ?? QueryParser::DEFAULT_NAMESPACE_DELIMITER;
        }

        /**
         * Normalises a key by adding a prefix if necessary and validating the namespace.
         * @param array|string|UnitEnum|Variable $key The key to normalise.
         * @return Variable The normalised key as a variable object.
         * @throws AccessDeniedException If the key belongs to a different registry.
         */
        protected function normalise (array|string|UnitEnum|Variable $key) : Variable {
            $variable = static::normaliseKeyWithCache($key, $this->segmentDelimiter);
            $namespace = $variable->getNamespace();

            if ($namespace !== null && $namespace !== $this->name) {
                throw new AccessDeniedException($namespace, $this->name);
            }
            return $variable->with(namespace: null, environment: null);
        }

        /**
         * Clears the entire static normalised-key LRU cache.
         *
         * Under PHP-FPM this is never necessary because the cache is process-scoped and FPM
         * isolates each request in its own worker. Under long-running runtimes (Octane, Swoole,
         * ReactPHP, RoadRunner) the cache accumulates entries for the lifetime of the worker;
         * the LRU cap (`$maxKeyCacheSize`) prevents unbounded growth, so clearing is only needed
         * when you want to reclaim memory immediately — for example, after a large batch import
         * that temporarily flooded the cache with one-off keys.
         *
         * **Note:** the cache entries are content-addressable (they contain no request-specific
         * state) and are therefore safe to share across requests. Clearing between requests is
         * valid but unnecessary for correctness.
         * @return void
         */
        public static function clearKeyCache () : void {
            self::$keyCache = [];
        }

        /**
         * Exports a registry's data, structured or flattened.
         * @param bool $flat Whether to return a flattened version of the data.
         * @param bool $namespaced Whether to include the registry name as a namespace prefix in the exported keys (only applies if `$flat` is `true`).
         * @return array The exported data.
         */
        public function export (bool $flat = false, bool $namespaced = false) : array {
            if (!$flat) {
                return $this->data;
            }

            if ($namespaced) {
                return static::flatten(
                    $this->data, 
                    true, 
                    $this->prefix !== "" ? $this->prefix . $this->namespaceDelimiter : "", 
                    $this->segmentDelimiter,
                    $this->namespaceDelimiter
                );
            }

            return static::flatten(
                $this->data, 
                true, 
                ($namespaced ? $this->name . $this->namespaceDelimiter : "") . ($this->prefix ?? ""), 
                $this->segmentDelimiter
            );
        }

        /**
         * Flattens a nested array into a dot-notated map.
         * @param iterable $array The array to flatten.
         * @param bool $skipArrays Whether to avoid flattening indexed arrays.
         * @param string $prefix The value to prepend the root keys with.
         * @param string $segmentdelimiter The segment delimiter to use.
         * @param string $namespaceDelimiter The namespace delimiter to use.
         * @return array The flattened array.
         */
        public static function flatten (iterable $array, bool $skipArrays = false, string $prefix = "", string $segmentdelimiter = QueryParser::DEFAULT_SEGMENT_DELIMITER, string $namespaceDelimiter = QueryParser::DEFAULT_NAMESPACE_DELIMITER) : array {
            $result = [];
            $namespace = str_ends_with($prefix, $namespaceDelimiter);
            foreach ($array as $key => $value) {
                $newKey = match (true) {
                    empty($prefix) => $key,
                    $namespace => $prefix . $key,
                    default => $prefix . $segmentdelimiter . $key
                };

                if ($skipArrays && is_array($value) && !empty($value) && array_keys($value) === range(0, count($value) - 1)) {
                    $result[$newKey] = $value;
                }
                elseif (is_array($value)) {
                    $result = array_merge($result, static::flatten($value, $skipArrays, $newKey, $segmentdelimiter, $namespaceDelimiter));
                }
                else {
                    $result[$newKey] = $value;
                }
            }
            return $result;
        }

        /**
         * Retrieves a value from the registry via path parts or a string.
         * @param string|array|UnitEnum|Variable $key The key to retrieve.
         * @return mixed The value at the specified key, or `null` if it doesn't exist.
         */
        public function get (array|string|UnitEnum|Variable $key) : mixed {
            $variable = $this->normalise($key);
            $parts = explode($this->segmentDelimiter, $variable->getName());

            $current = $this->data;
            foreach ($parts as $part) {
                if (!is_array($current) || !isset($current[$part])) {
                    return null;
                }
                $current = $current[$part];
            }

            return $current;
        }

        /**
         * Gets the data contained in a registry.
         * @return array The data contained in the registry.
         */
        public function getData () : array {
            return $this->data;
        }

        /**
         * Gets the maximum number of entries the key cache may hold.
         * @return int The current cap.
         */
        public static function getMaxKeyCacheSize () : int {
            return self::$maxKeyCacheSize;
        }

        /**
         * Gets the name of a registry.
         * @return string The name of the registry.
         */
        public function getName () : string {
            return $this->name;
        }

        /**
         * Gets the prefix of a registry.
         * @return string The prefix of the registry.
         */
        public function getPrefix () : string {
            return $this->prefix;
        }

        /**
         * Checks whether a registry has a value at the specified key path.
         * @param array|string|UnitEnum|Variable $key The key path to check.
         * @return bool Whether the registry has a value at the specified key path.
         */
        public function has (array|string|UnitEnum|Variable $key) : bool {
            $variable = $this->normalise($key);
            $parts = explode($this->segmentDelimiter, $variable->getName());

            $current = $this->data;
            foreach ($parts as $part) {
                if (!is_array($current) || !isset($current[$part])) {
                    return false;
                }
                $current = $current[$part];
            }
            return true;
        }

        /**
         * Merges a nested array directly into a registry's data using the provided merger callable.
         * When no callable is given, `array_replace_recursive` is used (preserving the default behaviour).
         * The merger receives `($existing, $incoming)` and must return the merged array.
         * @param array         $data   The nested array to merge into the registry's data.
         * @param callable|null $merger An optional merge callable. `null` falls back to `array_replace_recursive`.
         * @return static The registry.
         */
        public function merge (array $data, callable|null $merger = null) : static {
            $this->data = $merger !== null ? $merger($this->data, $data) : array_replace_recursive($this->data, $data);
            return $this;
        }

        /**
         * Normalises a key into a variable object, with caching to improve performance on repeated keys.
         * @param string|UnitEnum|Variable $key A key.
         * @param string|null $segmentDelimiter The delimiter to use for path segments.
         * @return Variable The normalised key as a variable object.
         * @throws RuntimeException If the key belongs to a different environment.
         */
        public static function normaliseKeyWithCache (array|string|UnitEnum|Variable $key, ?string $segmentDelimiter = null) : Variable {
            $variable = static::normaliseKey($key, $segmentDelimiter);
            $newKey = (string) $variable;
            $delimiter = $segmentDelimiter ?? QueryParser::DEFAULT_SEGMENT_DELIMITER;
            $cacheKey = $delimiter . "\x00" . $newKey;

            if (isset(self::$keyCache[$cacheKey])) {
                $cached = self::$keyCache[$cacheKey];
                unset(self::$keyCache[$cacheKey]);
                self::$keyCache[$cacheKey] = $cached;
                return $cached;
            }

            static $parsers = [];
            if (!isset($parsers[$delimiter])) {
                $parsers[$delimiter] = new QueryParser(segmentDelimiter: $delimiter);
            }

            $newVariable = $parsers[$delimiter]->resolve($newKey)[0];

            if (count(self::$keyCache) >= self::$maxKeyCacheSize) {
                unset(self::$keyCache[array_key_first(self::$keyCache)]);
            }

            $parsedNs = $newVariable->getNamespace()    !== QueryParser::DEFAULT_NAMESPACE_NAME    ? $newVariable->getNamespace()    : null;
            $parsedEnv = $newVariable->getEnvironment() !== QueryParser::DEFAULT_ENVIRONMENT_NAME ? $newVariable->getEnvironment() : null;

            return self::$keyCache[$cacheKey] = $newVariable->with(
                namespace:   $variable->getNamespace()    ?? $parsedNs,
                environment: $variable->getEnvironment()  ?? $parsedEnv,
            );
        }

        /**
         * Normalises a key into a variable object.
         * @param string|UnitEnum|Variable $key A key.
         * @param string|null $segmentDelimiter The delimiter to use for path segments.
         * @return Variable The normalised key as a variable object.
         * @throws RuntimeException If the key belongs to a different environment.
         */
        public static function normaliseKey (array|string|UnitEnum|Variable $key, ?string $segmentDelimiter = null) : Variable {
            if ($key instanceof Variable) {
                return $key;
            }

            $segmentDelimiter ??= QueryParser::DEFAULT_SEGMENT_DELIMITER;

            if (is_array($key)) {
                return new Variable(implode($segmentDelimiter, $key));
            }

            return match (true) {
                $key instanceof BackedEnum => new Variable($key->value),
                $key instanceof UnitEnum => new Variable($key->name),
                default => new Variable($key)
            };
        }

        /**
         * Sets a value into a registry at the specified key path.
         * @param array|string|UnitEnum|Variable $key The key path to set the value at.
         * @param mixed $value The value to set at the specified key path.
         * @param bool $force Whether to force the value to be set even if it would overwrite an existing container.
         * @return static The registry.
         */
        public function set (array|string|UnitEnum|Variable $key, mixed $value, bool $force = false) : static {
            $variable = $this->normalise($key);
            $parts = explode($this->segmentDelimiter, $variable->getName());

            $current = &$this->data;

            foreach (array_slice($parts, 0, -1) as $part) {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }

            $last = end($parts);

            if (!$force && isset($current[$last]) && is_array($current[$last])) {
                throw new ContainerOverwriteException($variable->getName());
            }

            if ($force && isset($current[$last]) && is_array($current[$last]) && is_array($value)) {
                $current[$last] = array_replace_recursive($current[$last], $value);
            }
            else $current[$last] = $value;
            
            return $this;
        }

        /**
         * Sets the maximum number of entries the key cache may hold.
         * Entries already in the cache are not evicted immediately; the new cap takes effect on
         * the next cache miss. Accepts any positive integer; values below 1 are ignored.
         * @param int $size The new cap. Must be greater than 0.
         * @return void
         */
        public static function setMaxKeyCacheSize (int $size) : void {
            if ($size < 1) return;
            self::$maxKeyCacheSize = $size;
        }

        /**
         * Sets the prefix for keys in a registry.
         * @param string $prefix The prefix to set.
         * @return static The registry.
         */
        public function setPrefix (string $prefix) : static {
            $this->prefix = $prefix;
            return $this;
        }

        /**
         * Removes a path from a registry's data.
         * @param array|string|UnitEnum|Variable $key The key path to remove.
         * @return static The registry.
         */
        public function remove (array|string|UnitEnum|Variable $key) : static {
            $variable = $this->normalise($key);
            $parts = explode($this->segmentDelimiter, $variable->getName());

            $current = &$this->data;
            $count = count($parts);

            for ($i = 0; $i < $count - 1; $i++) {
                $part = $parts[$i];
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    return $this;
                }
                $current = &$current[$part];
            }

            $last = end($parts);
            unset($current[$last]);

            return $this;
        }

        /**
         * Unflattens a dot-notated map into a nested array.
         * @param iterable $data The flattened data.
         * @param string $prefix The prefix to remove from keys.
         * @param bool $strict If true, prefix removal only happens if ALL keys start with the prefix.
         * @param string $delimiter The segment delimiter to use.
         * @return array The unflattened array.
         */
        public static function unflatten (iterable $data, string $prefix = "", bool $strict = false, string $delimiter = QueryParser::DEFAULT_SEGMENT_DELIMITER) : array {
            $result = [];
            $removePrefix = !empty($prefix);

            if ($removePrefix && $strict) {
                foreach ($data as $key => $value) {
                    if (strpos($key, $prefix . $delimiter) !== 0) {
                        $removePrefix = false;
                        break;
                    }
                }
            }

            foreach ($data as $key => $value) {
                if ($removePrefix && strpos($key, $prefix . $delimiter) === 0) {
                    $key = substr($key, strlen($prefix) + strlen($delimiter));
                }

                $keys = explode($delimiter, $key);
                $current = &$result;

                foreach ($keys as $nestedKey) {
                    if (!isset($current[$nestedKey]) || !is_array($current[$nestedKey])) {
                        $current[$nestedKey] = [];
                    }
                    $current = &$current[$nestedKey];
                }

                $current = $value;
            }

            return $result;
        }
    }
?>