<?php
    /*/
	 * Project Name:    Wingman — Cortex — Configuration
	 * Created by:      Angel Politis
	 * Creation Date:   Jan 27 2026
	 * Last Modified:   Mar 17 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use ArrayAccess;
    use RuntimeException;
    use UnitEnum;
    use Wingman\Cortex\Bridge\Corvus\Emitter as CorvusEmitter;
    use Wingman\Cortex\Bridge\Verix\Validator;
    use Wingman\Cortex\Enums\MergeStrategy;
    use Wingman\Cortex\Enums\Signal;
    use Wingman\Cortex\Exceptions\AccessDeniedException;
    use Wingman\Cortex\Exceptions\ConstantViolationException;
    use Wingman\Cortex\Exceptions\ContainerOverwriteException;
    use Wingman\Cortex\Exceptions\FrozenConfigurationException;
    use Wingman\Cortex\Exceptions\InvalidQueryException;
    use Wingman\Cortex\Exceptions\SchemaViolationException;
    use Wingman\Cortex\Interpolator;
    use Wingman\Cortex\Traits\CanExport;
    use Wingman\Cortex\Traits\CanImport;
    use Wingman\Cortex\Traits\HasTypedAccessors;

    /**
     * A class that provides configuration capabilities.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Configuration implements ArrayAccess {
        use CanExport;
        use CanImport;
        use HasTypedAccessors;

        /**
         * The name of the default configuration in the static registry.
         * @var string
         */
        public const string DEFAULT_NAME = "main";

        /**
         * The default namespace for variables.
         * @var string
         */
        public const string DEFAULT_NAMESPACE = '/';

        /**
         * The buckets of a configuration.
         * @var array<string, Bucket>
         */
        protected array $buckets = [];

        /**
         * The set of locked constant variable keys; once a key is registered here via setConst(),
         * any subsequent attempt to overwrite it via set() or mergeFlat() will throw.
         * Keys are stored as "{namespace}{namespaceDelimiter}{name}" strings.
         * @var array<string, true>
         */
        protected array $constants = [];

        /**
         * Whether the entire configuration is frozen against any further mutations.
         * When `true`, every write attempt — including set(), merge(), mergeFlat(), and setConst() — throws
         * a `FrozenConfigurationException` for all namespaces.
         * @var bool
         */
        protected bool $frozen = false;

        /**
         * The set of individually frozen namespaces. Keys are namespace names; values are always `true`.
         * A write to any key within a frozen namespace throws a `FrozenConfigurationException`, even if
         * the whole configuration is not frozen via `$frozen`.
         * @var array<string, true>
         */
        protected array $frozenNamespaces = [];

        /**
         * The registry of lazily-loaded namespaces. Entries are added via `registerNamespace()` and
         * consumed (loaded then removed) on the first `getBucket()` call for that namespace.
         * Each entry has the shape `{source: string|array, options: array}`.
         * @var array<string, array{source: string|array, options: array}>
         */
        protected array $lazyNamespaces = [];

        /**
         * A map of every source path loaded via `import()` to its `filemtime()` value at load
         * time. Included in the `dumpCache()` payload so that `ConfigurationCache::isStale()`
         * can detect whether any source file has changed since the cache was written — without
         * requiring the caller to remember which files were originally imported.
         * @var array<string, int>
         */
        protected array $loadedSources = [];

        /**
         * The implicit namespace to search within when resolving variables without an explicit namespace.
         * @var string
         */
        protected string $implicitNamespace = self::DEFAULT_NAMESPACE;

        /**
         * A serialised point-in-time clone of `$buckets`, `$constants`, `$frozenNamespaces`, and
         * `$frozen`, captured by `snapshot()`. Used by `reset()` to restore the post-import state
         * without replaying individual operations.
         * @var string|null
         */
        protected ?string $resetSnapshot = null;

        /**
         * The name of a configuration.
         * @var string|null
         */
        protected ?string $name;

        /**
         * The namespace delimiter used for variable references.
         * @var string
         */
        protected string $namespaceDelimiter = QueryParser::DEFAULT_NAMESPACE_DELIMITER;

        /**
         * The change dispatcher that owns the observer registry and Corvus emitter for this
         * configuration. Handles pattern-matched local callbacks and `Signal::CHANGED` bus
         * emissions on every successful write. Not serialised — observer graphs and bus
         * registrations are ephemeral runtime state.
         * @var ChangeDispatcher
         */
        private ChangeDispatcher $dispatcher;

        /**
         * The common prefix of configurations.
         * @var string
         */
        protected string $prefix = "";

        /**
         * The segment delimiter used for keys in the configuration.
         * @var string
         */
        protected string $segmentDelimiter = QueryParser::DEFAULT_SEGMENT_DELIMITER;

        /**
         * Whether the configuration operates in strict mode. When `true`, `merge()` throws a
         * `ConstantViolationException` on the first incoming key that would overwrite a constant.
         * When `false` (default), such keys are silently dropped and a
         * `Signal::CONSTANT_MERGE_SKIPPED` signal is emitted instead.
         * @var bool
         */
        protected bool $strict = false;

        /**
         * The loader used for importing configurations.
         * @var Loader
         */
        protected Loader $loader;

        /**
         * The active merge strategy used by `merge()` when incorporating incoming nested arrays.
         * Defaults to `MergeStrategy::REPLACE`, which preserves the historical `array_replace_recursive`
         * behaviour. Override via `setMergeStrategy()` to change how array branches are combined.
         * @var MergeStrategy
         */
        protected MergeStrategy $mergeStrategy = MergeStrategy::REPLACE;

        /**
         * The interpolator used for resolving variable references within values.
         * Reused across all get() calls to avoid per-read object instantiation.
         * @var Interpolator
         */
        protected Interpolator $interpolator;

        /**
         * The query parser used for matching configuration queries.
         * @var QueryParser
         */
        protected QueryParser $parser;

        /**
         * Creates a new configuration, optionally registering it in the static registry.
         * @param string|null $name The name to register under. `null` creates an anonymous configuration.
         * @param CorvusEmitter|null $emitter An optional pre-configured Corvus emitter. When `null`, a
         *                                    new emitter is created automatically. Injection is useful
         *                                    for sharing a single emitter across multiple configurations
         *                                    or for scoping emissions to a particular bus.
         * @throws RuntimeException If a configuration with the same name already exists in the registry.
         */
        public function __construct (?string $name = null, ?CorvusEmitter $emitter = null) {
            $this->name = $name;
            $this->dispatcher = new ChangeDispatcher($emitter ?? CorvusEmitter::create());
            $this->loader = new Loader(true, $this->segmentDelimiter);
            $this->parser = new QueryParser(
                defaultEnvironment: $name ?? QueryParser::DEFAULT_ENVIRONMENT_NAME,
                defaultNamespace: $this->implicitNamespace,
                namespaceDelimiter: $this->namespaceDelimiter,
                segmentDelimiter: $this->segmentDelimiter
            );
            $this->interpolator = new Interpolator();

            if ($name !== null) ConfigurationRegistry::register($name, $this);
        }

        /**
         * Gets the debug information for a configuration.
         * @return array The debug information.
         */
        public function __debugInfo () {
            return [
                "name" => $this->name,
                "buckets" => $this->buckets,
                "prefix" => $this->prefix
            ];
        }

        /**
         * Supports `$config->key` or `$config->{"key"}` access to configuration values.
         * @param string $name The name of the key to access.
         * @return mixed The value associated with the key, or a scope if the value is an array or `null`.
         */
        public function __get (string $name) : mixed {
            $value = $this->get($name);
            
            if ($value instanceof Scope) {
                return new Scope($this, $name, $value->getNamespace() ?? static::DEFAULT_NAMESPACE);
            }
            return $value;
        }

        /**
         * Supports invoking a configuration to get a scope for a specific namespace (e.g., $config("namespace")).
         * @param string $namespace The namespace to get a scope for or `null` to get a scope for the implicit namespace.
         * @return Scope A scope for the specified namespace.
         */
        public function __invoke (?string $namespace = null) : Scope {
            return new Scope($this, "", $namespace ?? $this->implicitNamespace);
        }

        /**
         * Serialises a configuration.
         * @return array The data to serialise.
         */
        public function __serialize () : array {
            return [
                "name" => $this->name,
                "buckets" => $this->buckets,
                "prefix" => $this->prefix,
                "implicitNamespace" => $this->implicitNamespace,
                "namespaceDelimiter" => $this->namespaceDelimiter,
                "segmentDelimiter" => $this->segmentDelimiter,
                "constants" => $this->constants,
                "frozen" => $this->frozen,
                "frozenNamespaces" => $this->frozenNamespaces,
                "lazyNamespaces" => $this->lazyNamespaces,
                "resetSnapshot" => $this->resetSnapshot
            ];            
        }

        /**
         * Unserialises a configuration.
         * @param array $data The data to unserialise.
         */
        public function __unserialize (array $data) : void {
            $this->name = $data["name"];
            $this->buckets = $data["buckets"];
            $this->prefix = $data["prefix"];
            $this->implicitNamespace = $data["implicitNamespace"] ?? self::DEFAULT_NAMESPACE;
            $this->namespaceDelimiter = $data["namespaceDelimiter"] ?? QueryParser::DEFAULT_NAMESPACE_DELIMITER;
            $this->segmentDelimiter = $data["segmentDelimiter"] ?? QueryParser::DEFAULT_SEGMENT_DELIMITER;
            $this->constants = $data["constants"] ?? [];
            $this->frozen = $data["frozen"] ?? false;
            $this->frozenNamespaces = $data["frozenNamespaces"] ?? [];
            $this->lazyNamespaces = $data["lazyNamespaces"] ?? [];
            $this->resetSnapshot = $data["resetSnapshot"] ?? null;
            $this->loader = new Loader(true, $this->segmentDelimiter);
            $this->parser = new QueryParser(
                defaultEnvironment: $this->name ?? QueryParser::DEFAULT_ENVIRONMENT_NAME,
                defaultNamespace: $this->implicitNamespace,
                namespaceDelimiter: $this->namespaceDelimiter,
                segmentDelimiter: $this->segmentDelimiter
            );
            $this->interpolator = new Interpolator();
            $this->dispatcher = new ChangeDispatcher(CorvusEmitter::create());
        }

        /**
         * Creates a deep copy of the configuration with independently-owned buckets and a
         * fresh `ChangeDispatcher`. No observers from the original carry over to the clone.
         * The `$resetSnapshot` is intentionally cleared so the clone starts with no automatic
         * rollback point — its baseline is the data state at branch time.
         */
        public function __clone () : void {
            $this->dispatcher = new ChangeDispatcher(CorvusEmitter::create());
            $this->resetSnapshot = null;

            $cloned = [];

            foreach ($this->buckets as $bucketName => $bucket) {
                $cloned[$bucketName] = new Bucket($bucketName, $this, $bucket->getData());
            }

            $this->buckets = $cloned;
        }

        /**
         * Computes the fully-qualified key and forwards all arguments.
         * @param string $key The base key name (without namespace prefix).
         * @param string $namespace The namespace the key belongs to.
         * @param mixed $old The previous value, or `null` if the key did not exist.
         * @param mixed $new The incoming value.
         */
        private function fireChange (string $key, string $namespace, mixed $old, mixed $new) : void {
            $fullyQualifiedKey = $namespace . $this->namespaceDelimiter . $key;
            $this->dispatcher->fire($fullyQualifiedKey, $key, $namespace, $old, $new, $this);
        }

        /**
         * Normalises a key by adding a prefix if necessary.
         * @param string|UnitEnum|Variable $key A key.
         * @return Variable The normalised key as a variable object.
         * @throws AccessDeniedException If the key belongs to a different environment.
         */
        protected function normaliseKey (string|UnitEnum|Variable $key) : Variable {
            $variable = Registry::normaliseKeyWithCache($key, $this->segmentDelimiter);
            $environment = $variable->getEnvironment();

            if ($environment !== null && ($this->name == null || $environment !== $this->name)) {
                throw new AccessDeniedException($environment, $this->name);
            }

            return $variable;
        }

        /**
         * Captures the current values of all `#[Configurable]`-annotated properties of `$object`
         * into a named slot on this configuration instance, keyed internally as
         * `"{spl_object_id}:{name}"`. Calling this again with the same `$name` overwrites the
         * previous capture. Properties that are not yet initialised are silently skipped.
         * @param object $object The object whose `#[Configurable]` properties should be captured.
         * @param string $name An arbitrary label for the capture (e.g. `"defaults"`, `"test"`).
         * @return static The configuration.
         */
        public function captureObject (object $object, string $name) : static {
            ObjectHydrator::capture($object, $name, $this);
            return $this;
        }

        /**
         * Creates a new configuration, optionally registering it in the static registry.
         * @param string|null $name The name to register under. `null` creates an anonymous configuration.
         * @param CorvusEmitter|null $emitter An optional pre-configured Corvus emitter. When `null`, a
         *                                    new emitter is created automatically. Injection is useful
         *                                    for sharing a single emitter across multiple configurations
         *                                    or for scoping emissions to a particular bus.
         */
        public static function create (?string $name = null, ?CorvusEmitter $emitter = null) {
            return new static($name, $emitter);
        }

        /**
         * Creates a snapshot of a configuration, copying its settings but not its data.
         * @return static A new configuration instance with the same settings as the current one.
         */
        public function createSnapshot () : static {
            $snapshot = new static($this->name);
            $snapshot->namespaceDelimiter = $this->namespaceDelimiter;
            $snapshot->segmentDelimiter = $this->segmentDelimiter;
            $snapshot->prefix = $this->prefix;
            return $snapshot;
        }

        /**
         * Builds the serialisable payload array used by `ConfigurationCache::write()` to persist
         * the fully-resolved configuration state. The format is self-describing so that
         * `restoreCache()` can rebuild the instance faithfully without re-parsing any source files.
         * @return array The payload.
         */
        public function dumpCache () : array {
            return [
                "generatedAt" => time(),
                "name" => $this->name,
                "namespaceDelimiter" => $this->namespaceDelimiter,
                "segmentDelimiter" => $this->segmentDelimiter,
                "prefix" => $this->prefix,
                "constants" => $this->constants,
                "sources" => $this->loadedSources,
                "buckets" => array_map(fn (Bucket $bucket) => $bucket->getData(), $this->buckets),
            ];
        }

        /**
         * Gets a bucket by name. If the namespace has a registered lazy-load source and has not yet
         * been loaded, the source is imported first and the registration entry is consumed. A new empty
         * bucket is created when no lazy registration exists and the bucket is not already present.
         * @param string $name The name of the bucket to retrieve.
         * @return Bucket The bucket associated with the given name.
         */
        public function getBucket (string $name) : Bucket {
            if (!isset($this->buckets[$name]) && isset($this->lazyNamespaces[$name])) {
                $savedNamespace = $this->implicitNamespace;
                $registration = $this->lazyNamespaces[$name];

                unset($this->lazyNamespaces[$name]);

                $this->setImplicitNamespace($name);
                $this->import($registration["source"], $registration["options"]);
                $this->setImplicitNamespace($savedNamespace);
            }

            if (!isset($this->buckets[$name])) {
                $this->buckets[$name] = new Bucket($name, $this);
            }

            return $this->buckets[$name];
        }

        /**
         * Returns the full configuration export with all keys matched by the given queries removed.
         * Useful for safe serialisation and logging where sensitive keys must be omitted.
         * Each query uses the standard Cortex DSL (the same syntax accepted by `search()`),
         * e.g. `["[security]", "db[password]"]` to strip an entire namespace and a single key.
         * @param array<string> $queries One or more Cortex DSL queries whose matched keys are excluded.
         * @return array<string, mixed> Nested array matching `toArray()` output, minus excluded keys.
         */
        public function except (array $queries) : array {
            return QueryEngine::except($this, $queries);
        }

        /**
         * Returns whether a named configuration exists in the registry.
         * Delegates to `ConfigurationRegistry::exists()`.
         * @param string|null $name The name to look up; `null` resolves to `DEFAULT_NAME`.
         * @return bool Whether the configuration is registered.
         */
        public static function exists (?string $name = null) : bool {
            return ConfigurationRegistry::exists($name);
        }

        /**
         * Returns a named configuration from the registry. When `$name` is `null` and no default
         * configuration has been registered yet, one is created and registered automatically so
         * that callers can start reading and writing without an explicit `create()` call.
         * Named lookups still return `null` when the requested name is not registered.
         * @param string|null $name The name to look up; `null` resolves to `DEFAULT_NAME`.
         * @return static|null The configuration, or `null` if not registered.
         */
        public static function find (?string $name = null) : ?static {
            if ($name === null && !ConfigurationRegistry::exists()) {
                new static(static::DEFAULT_NAME);
            }
            return ConfigurationRegistry::get($name);
        }

        /**
         * Freezes the entire configuration, preventing all further mutations regardless of namespace.
         * Calling `set()`, `merge()`, `mergeFlat()`, or `setConst()` on a frozen configuration will
         * throw a `FrozenConfigurationException`. Freeze is permanent within the lifetime of the
         * instance; there is no thaw operation by design.
         * @return static The configuration.
         */
        public function freeze () : static {
            $this->frozen = true;
            return $this;
        }

        /**
         * Freezes a single namespace, preventing all further mutations to keys within it while
         * leaving other namespaces writable. If the whole configuration is already frozen via
         * `freeze()`, this has no additional effect but is non-destructive.
         * @param string $namespace The namespace to freeze.
         * @return static The configuration.
         */
        public function freezeNamespace (string $namespace) : static {
            $this->frozenNamespaces[$namespace] = true;
            return $this;
        }

        /**
         * Returns a deep-cloned copy of this configuration that can be mutated independently.
         * The clone gets its own `ChangeDispatcher` (no observers inherited), fresh-owned
         * `Bucket` instances, and a cleared `$resetSnapshot`. The active `$mergeStrategy` and
         * all current data, constants, and frozen state are preserved.
         *
         * Typical use-case: share a fully-loaded configuration with a consumer that needs to
         * customise it without affecting the originator.
         *
         * @param string|null $name Optional name for the branch. `null` keeps the original name.
         * @return static A deep-cloned, independently-mutable copy.
         */
        public function branch (?string $name = null) : static {
            $instance = clone $this;

            if ($name !== null) {
                $instance->name = $name;
            }

            return $instance;
        }

        /**
         * Returns an immutable (frozen) deep-cloned copy of this configuration. Delegates to
         * `branch()` then calls `freeze()` on the result. The returned instance is permanently
         * read-only — all write operations will throw `FrozenConfigurationException`.
         *
         * Use this to safely distribute a read-only view of configuration data to untrusted or
         * non-owning components without the risk of unintentional mutation.
         *
         * @param string|null $name Optional name for the immutable copy. `null` keeps the original.
         * @return static A frozen, independently-owned copy.
         */
        public function immutable (?string $name = null) : static {
            return $this->branch($name)->freeze();
        }

        /**
         * Creates a new configuration instance from an iterable of key-value pairs.
         * @param iterable $data The data to populate the configuration with.
         * @param string|null $name An optional name to register the configuration under. `null` creates an anonymous configuration.
         * @return static The populated configuration instance.
         */
        public static function fromIterable (iterable $data, ?string $name = null) : static {
            $config = new static($name);
            foreach ($data as $key => $value) {
                $config->set($key, $value);
            }
            return $config;
        }

        /**
         * Gets a value from a configuration.
         * @param string|UnitEnum|Variable $key The key to retrieve a value from.
         * @param mixed $default The default value to return if the key is not defined.
         * @return mixed The value saved in association with the key.
         */
        public function get (string|UnitEnum|Variable $key, mixed $default = null) : mixed {
            $interpolator = $this->interpolator;
            $resolver = function (Variable $variable, array $namespaces) use ($default) {
                if ($variable->getEnvironment() !== null && ($this->name == null || $variable->getEnvironment() !== $this->name)) {
                    throw new AccessDeniedException((string) $variable->getEnvironment(), $this->name);
                }

                if ($variable->getNamespace() === null) {
                    foreach ($namespaces as $ns) {
                        $var = ($ns === $this->implicitNamespace) ? $variable : $variable->withNamespace($ns);
                        $value = $this->getRaw($var, $default);
                        if ($value !== null) return $value;
                    }
                }
                else {
                    $value = $this->getRaw($variable, $default);
                    if ($value !== null) return $value;
                }

                return $default;
            };
            $raw = $this->getRaw($key, $default);
            return $interpolator->interpolate($raw, $resolver, [$this->implicitNamespace]);
        }

        /**
         * Gets the implicit namespace of a configuration, which is used for resolving variables without an explicit namespace.
         * @return string The implicit namespace of the configuration.
         */
        public function getImplicitNamespace () : string {
            return $this->implicitNamespace;
        }

        /**
         * Gets a raw value from a configuration.
         * @param string|UnitEnum|Variable $key The key to retrieve a value from.
         * @param mixed $default The default value to return if the key is not defined.
         * @return mixed The value saved in association with the key.
         * @throws InvalidQueryException If a multi-value query is attempted.
         */
        public function getRaw (string|UnitEnum|Variable $key, mixed $default = null) : mixed {
            $variable = $this->normaliseKey($key);
            $rawKey = $variable->getName();

            if (strpbrk($rawKey, '*,;')) {
                throw new InvalidQueryException($rawKey);
            }

            if (str_contains($rawKey, '[')) {
                $patterns = $this->parser->compile($variable);
                $results = QueryEngine::extractQuery($this, $patterns);
                return QueryEngine::unwrapSelection($this, $results);
            }

            $namespace = $variable->getNamespace();

            if ($namespace !== null && !isset($this->buckets[$namespace])) {
                return $default;
            }

            $bucket = $this->getBucket($namespace ?? $this->implicitNamespace);

            if (!$bucket->has($variable)) {
                return $default;
            }

            $value = $bucket->get($variable);

            if (is_array($value)) {
                return new Scope($this, $rawKey, $namespace ?? $this->implicitNamespace);
            }

            return $value;
        }

        /**
         * Gets the name of a configuration.
         * @return string|null The name of the configuration, or `null` if it doesn't have one.
         */
        public function getName () : ?string {
            return $this->name;
        }

        /**
         * Gets the namespace delimiter for keys in a configuration.
         * @return string The namespace delimiter for keys in the configuration.
         */
        public function getNamespaceDelimiter () : string {
            return $this->namespaceDelimiter;
        }

        /**
         * Gets the query parser owned by this configuration. Exposed for use by `QueryEngine`
         * and other same-package collaborators that need to compile queries or inspect defaults.
         * @return QueryParser The query parser.
         */
        public function getParser () : QueryParser {
            return $this->parser;
        }

        /**
         * Gets the segment delimiter for keys in a configuration.
         * @return string The segment delimiter for keys in the configuration.
         */
        public function getSegmentDelimiter () : string {
            return $this->segmentDelimiter;
        }

        /**
         * Gets the prefix for keys in a configuration.
         * @return string The prefix for keys in the configuration.
         */
        public function getPrefix () : string {
            return $this->prefix;
        }

        /**
         * Gets the locked constants map as a plain associative array of `"namespace/key" => true` entries.
         * Intended for use by `ConfigurationCache::write()` so the cache can snapshot the constants
         * set without exposing a mutation vector.
         * @return array<string, true> The constants map.
         */
        public function getConstants () : array {
            return $this->constants;
        }

        /**
         * Returns all registered configurations.
         * Delegates to `ConfigurationRegistry::getAll()`.
         * @return static[] All configurations, keyed by name.
         */
        public static function getAll () : array {
            return ConfigurationRegistry::getAll();
        }

        /**
         * Returns the names of all registered configurations.
         * Delegates to `ConfigurationRegistry::getAllNames()`.
         * @return string[] The names.
         */
        public static function getAllNames () : array {
            return ConfigurationRegistry::getAllNames();
        }

        /**
         * Gets whether a key has been defined in a configuration.
         * @param string|UnitEnum|Variable $key A key.
         * @return bool Whether the key exists.
         */
        public function has (string|UnitEnum|Variable $key) : bool {
            $variable = $this->normaliseKey($key);
            $namespace = $variable->getNamespace() ?? $this->implicitNamespace;

            if (!isset($this->buckets[$namespace]) && isset($this->lazyNamespaces[$namespace])) {
                $this->getBucket($namespace);
            }

            if (!isset($this->buckets[$namespace])) {
                return false;
            }

            return $this->buckets[$namespace]->has(explode($this->segmentDelimiter, $variable->getName()));
        }

        /**
         * Returns whether the given string is the name of a currently loaded namespace (bucket).
         * Used by `QueryEngine` to distinguish namespace keys from path segments when unwrapping
         * a single-entry result tree.
         * @param  string $name The namespace name to test.
         * @return bool         `true` if a bucket with that name exists.
         */
        public function hasNamespace (string $name) : bool {
            return isset($this->buckets[$name]);
        }

        /**
         * Hydrates an object's `#[Configurable]` properties from a configuration source.
         *
         * The `$source` parameter accepts either an existing `Configuration` instance or a flat
         * associative array of dot-notation key-value pairs, which is wrapped in a new anonymous
         * `Configuration` via `mergeFlat()` before hydration proceeds.
         *
         * Two hydration modes are available, selected by whether `$map` is supplied:
         * - Mode A — Explicit map: `$map` is an associative `propertyName => configKey` array.
         *   Integer-keyed entries imply `propertyName === configKey`.
         * - Mode B — Attribute mode: every property decorated with `#[Configurable]` is resolved
         *   automatically using the key declared in the attribute. Values are coerced to the
         *   property's declared primitive type (`bool`, `int`, `float`, `string`) before assignment.
         *   If the key is absent and the attribute declares a `$default`, that default is used;
         *   otherwise the property is left untouched (or `null` is assigned when the type allows it).
         *
         * In strict mode, a missing or null-valued config key with no attribute default throws an
         * `UndefinedVariableException`. In non-strict mode, such keys are silently skipped.
         *
         * @param string|object $target The object whose properties should be hydrated, or a fully-qualified
         *                               class-string to hydrate only its `static` properties without an instance.
         * @param array|static $source A flat key-value array or an existing `Configuration` instance.
         * @param array $map An optional explicit `propertyName => configKey` override map.
         * @param bool $strict When `true`, throws for missing or null-valued keys that carry no attribute default.
         * @return static The `Configuration` instance that was used for hydration.
         * @throws UndefinedVariableException If strict mode is enabled and a required key is absent or null.
         */
        public static function hydrate (string|object $target, array|self $source = [], array $map = [], bool $strict = false) : static {
            return ObjectHydrator::hydrate($target, $source, $map, $strict);
        }

        /**
         * Gets whether a variable in a configuration is defined as a constant.
         * @param string|UnitEnum|Variable $key The key to check.
         * @return bool Whether the key is a constant.
         */
        public function isConst (string|UnitEnum|Variable $key) : bool {
            $variable = $this->normaliseKey($key);
            $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
            return isset($this->constants[$namespace . $this->namespaceDelimiter . $variable->getName()]);
        }

        /**
         * Gets whether the entire configuration is frozen against mutations.
         * @return bool `true` if the configuration was frozen via `freeze()`.
         */
        public function isFrozen () : bool {
            return $this->frozen;
        }

        /**
         * Gets whether a specific namespace is frozen against mutations.
         * Returns `true` if the namespace was individually frozen via `freezeNamespace()`, or if
         * the entire configuration is frozen via `freeze()`.
         * @param string $namespace The namespace to check.
         * @return bool `true` if writes to the namespace are currently blocked.
         */
        public function isNamespaceFrozen (string $namespace) : bool {
            return $this->frozen || isset($this->frozenNamespaces[$namespace]);
        }

        /**
         * Gets whether a namespace has already been loaded (i.e., its bucket exists in memory).
         * A namespace is considered loaded as soon as any write or triggered lazy load created its
         * bucket, regardless of whether it contained data.
         * @param string $namespace The namespace to check.
         * @return bool `true` if the namespace bucket exists in memory.
         */
        public function isNamespaceLoaded (string $namespace) : bool {
            return isset($this->buckets[$namespace]);
        }

        /**
         * Gets whether a namespace has a pending lazy-load registration that has not yet been
         * triggered. Once the namespace is accessed for the first time, the registration is consumed
         * and this method returns `false` while `isNamespaceLoaded()` returns `true`.
         * @param string $namespace The namespace to check.
         * @return bool `true` if the namespace registration is still pending.
         */
        public function isNamespaceRegistered (string $namespace) : bool {
            return isset($this->lazyNamespaces[$namespace]);
        }

        /**
         * Gets whether a variable is set in a configuration.
         * @param string|UnitEnum|Variable $key The key to check for existence.
         * @return bool Whether the variable is set.
         */
        public function isSet (string|UnitEnum|Variable $key) : bool {
            return $this->getRaw($key) !== null;
        }

        /**
         * Gets whether the configuration is operating in strict mode.
         * In strict mode, `merge()` throws a `ConstantViolationException` on constant key conflicts
         * instead of silently skipping them.
         * @return bool `true` if strict mode is enabled.
         */
        public function isStrict () : bool {
            return $this->strict;
        }

        /**
         * Gets the active merge strategy used by `merge()` on this configuration instance.
         * @return MergeStrategy The current merge strategy.
         */
        public function getMergeStrategy () : MergeStrategy {
            return $this->mergeStrategy;
        }

        /**
         * Merges one or more arrays into a configuration using the active `$mergeStrategy`.
         * The strategy can be changed instance-wide via `setMergeStrategy()`; the default strategy
         * is `MergeStrategy::REPLACE`, which preserves backwards-compatible `array_replace_recursive`
         * behaviour.
         *
         * When constants are registered, incoming keys that would overwrite a constant are handled
         * according to the strict mode set by `setStrict()`:
         * - **Strict** (`true`): throws `ConstantViolationException` on the first constant conflict,
         *   preventing any write from the offending map.
         * - **Permissive** (`false`, default): constant keys are silently dropped and a
         *   `Signal::CONSTANT_MERGE_SKIPPED` Corvus signal is emitted for each one, carrying
         *   `["key" => $constKey, "value" => $skippedValue]` as payload.
         *
         * @param array ...$maps One or more associative maps to merge into the implicit namespace.
         * @return static The configuration.
         * @throws FrozenConfigurationException If the configuration or its implicit namespace is frozen.
         * @throws ConstantViolationException   If strict mode is on and an incoming key targets a constant.
         */
        public function merge (array ...$maps) : static {
            $namespace = $this->implicitNamespace;

            if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                throw new FrozenConfigurationException("*", $this->frozen ? null : $namespace);
            }

            $merger = $this->mergeStrategy !== MergeStrategy::REPLACE
                ? fn (array $e, array $i) => $this->mergeStrategy->apply($e, $i)
                : null;

            foreach ($maps as $data) {
                if (!empty($this->constants)) {
                    $data = $this->filterMergeConstants($data, $namespace, "");
                }

                $this->getBucket($namespace)->merge($data, $merger);
            }

            return $this;
        }

        /**
         * Recursively filters constant keys out of a nested array before it is passed to `merge()`.
         * Each scalar leaf is matched against `$this->constants` using its fully-qualified key form.
         * In strict mode the first match throws; in permissive mode the key is dropped and a
         * `Signal::CONSTANT_MERGE_SKIPPED` Corvus signal is emitted carrying the key and value.
         * Array nodes are recurse into so that deeply nested constants are handled correctly.
         * @param array  $data      The nested array slice being filtered.
         * @param string $namespace The implicit namespace the merge targets.
         * @param string $prefix    The dot-notated path prefix accumulated so far (empty at root level).
         * @return array The filtered array slice, safe to pass to the bucket merger.
         * @throws ConstantViolationException If strict mode is on and a constant key is encountered.
         */
        private function filterMergeConstants (array $data, string $namespace, string $prefix) : array {
            $filtered = [];

            foreach ($data as $key => $value) {
                $fullKey = $prefix === "" ? (string) $key : $prefix . $this->segmentDelimiter . $key;
                $constKey = $namespace . $this->namespaceDelimiter . $fullKey;

                if (is_array($value)) {
                    $filtered[$key] = $this->filterMergeConstants($value, $namespace, $fullKey);
                    continue;
                }

                if (isset($this->constants[$constKey])) {
                    if ($this->strict) {
                        throw new ConstantViolationException($constKey);
                    }

                    $this->dispatcher->getEmitter()
                        ->withOnly(["key" => $constKey, "value" => $value])
                        ->emit(Signal::CONSTANT_MERGE_SKIPPED);

                    continue;
                }

                $filtered[$key] = $value;
            }

            return $filtered;
        }

        /**
         * Merges one or more flat maps into a configuration, treating the keys as variable references.
         * @param array ...$maps A single flat map or a list of flat maps.
         * @return static The configuration.
         * @throws RuntimeException If a map from a different environment is being merged or a constant key is targeted.
         */
        public function mergeFlat (array ...$maps) : static {
            foreach ($maps as $map) {
                foreach ($map as $key => $value) {
                    $variable = Variable::from($key);
                    $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
                    $environment = $variable->getEnvironment();
                    $constKey = $namespace . $this->namespaceDelimiter . $variable->getName();

                    if ($environment !== null && ($this->name == null || $environment !== $this->name)) {
                        throw new AccessDeniedException($environment, $this->name);
                    }

                    if (isset($this->constants[$constKey])) {
                        throw new ConstantViolationException($constKey);
                    }

                    if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                        throw new FrozenConfigurationException($constKey, $this->frozen ? null : $namespace);
                    }

                    $old = $this->getRaw($variable, null);
                    $this->getBucket($namespace)->set($variable, $value, true);
                    $this->fireChange($variable->getName(), $namespace, $old, $value);
                }
            }

            return $this;
        }

        /**
         * Merges one or more nested arrays into the store, using the given `$strategy` for this call
         * only, without altering the globally active `$mergeStrategy`. The previous strategy is
         * restored — even if `merge()` throws — so the call is always side-effect-free with respect
         * to the instance's strategy state.
         *
         * @param MergeStrategy $strategy The strategy to apply for this merge only.
         * @param array         ...$maps  One or more associative maps to merge into the implicit namespace.
         * @return static The configuration.
         * @throws FrozenConfigurationException If the configuration or its implicit namespace is frozen.
         * @throws ConstantViolationException   If strict mode is on and an incoming key targets a constant.
         */
        public function mergeWithStrategy (MergeStrategy $strategy, array ...$maps) : static {
            $previous = $this->mergeStrategy;
            $this->mergeStrategy = $strategy;

            try {
                $this->merge(...$maps);
            } finally {
                $this->mergeStrategy = $previous;
            }

            return $this;
        }

        /**
         * Merges one or more flat maps into the store, applying `$strategy` when both the existing
         * and incoming value at a given key are arrays. For scalar-to-scalar or absent-key writes
         * the strategy has no effect and the incoming value is set directly, preserving the same
         * per-key freeze and constant guards as `mergeFlat()`.
         *
         * @param MergeStrategy $strategy The strategy to apply when merging array-to-array conflicts.
         * @param array         ...$maps  One or more flat dot-notation key-value maps.
         * @return static The configuration.
         * @throws AccessDeniedException        If a key belongs to a different environment.
         * @throws ConstantViolationException   If a key is locked as a constant.
         * @throws FrozenConfigurationException If the configuration or the target namespace is frozen.
         */
        public function mergeFlatWithStrategy (MergeStrategy $strategy, array ...$maps) : static {
            foreach ($maps as $map) {
                foreach ($map as $key => $value) {
                    $variable = Variable::from($key);
                    $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
                    $environment = $variable->getEnvironment();
                    $constKey = $namespace . $this->namespaceDelimiter . $variable->getName();

                    if ($environment !== null && ($this->name == null || $environment !== $this->name)) {
                        throw new AccessDeniedException($environment, $this->name);
                    }

                    if (isset($this->constants[$constKey])) {
                        throw new ConstantViolationException($constKey);
                    }

                    if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                        throw new FrozenConfigurationException($constKey, $this->frozen ? null : $namespace);
                    }

                    $old = $this->getRaw($variable, null);

                    if (is_array($value) && is_array($old)) {
                        $value = $strategy->apply($old, $value);
                    }

                    $this->getBucket($namespace)->set($variable, $value, true);
                    $this->fireChange($variable->getName(), $namespace, $old, $value);
                }
            }

            return $this;
        }

        /**
         * Checks whether a key exists in a configuration.
         * @param mixed $key The key to check for existence.
         * @return bool Whether the key exists.
         */
        public function offsetExists (mixed $key) : bool {
            return $this->has($key);
        }

        /**
         * Gets a value from a configuration using array access.
         * @param mixed $key The key to retrieve a value from.
         * @return mixed The value saved in association with the key.
         */
        public function offsetGet (mixed $key) : mixed {
            $variable = $this->normaliseKey($key);
            $value = $this->get($variable);

            if (is_array($value) || $value === null) {
                return new Scope($this, $variable->getName(), $variable->getNamespace() ?? $this->implicitNamespace);
            }

            return $value;
        }

        /**
         * Sets a value to a configuration using array access.
         * @param mixed $key The key to save a value at.
         * @param mixed $value The value to store.
         */
        public function offsetSet (mixed $key, mixed $value) : void {
            $this->set($key, $value);
        }

        /**
         * Unsets a key from a configuration using array access.
         * @param mixed $key The key to unset.
         */
        public function offsetUnset (mixed $key) : void {
            $this->unset($key);
        }

        /**
         * Suspends per-key change notifications for the duration of `$fn` and emits a single
         * `"cortex.batchChanged"` Corvus signal when it returns, carrying the full list of
         * queued changes as its payload. Local `onChange()` observers are still invoked once per
         * changed key after `$fn` completes, but only one signal crosses the Corvus bus.
         *
         * This is the correct way to perform bulk writes (large imports, feature-flag toggles,
         * etc.) without flooding any subscriber that listens to `Signal::CHANGED` with hundreds
         * of individual events. Batch calls nest safely — the inner call's changes are accumulated
         * into the outer queue and the bus signal fires only when the outermost batch ends.
         *
         * @param callable $fn A callable that receives this configuration as its first argument
         *                     and performs any number of writes.
         * @return static The configuration.
         */
        public function batch (callable $fn) : static {
            $this->dispatcher->beginBatch();

            try {
                $fn($this);
            } finally {
                $this->dispatcher->endBatch($this);
            }

            return $this;
        }

        /**
         * Registers a callback to be invoked whenever a key matching `$pattern` is written via
         * `set()` or `mergeFlat()`. The pattern is matched against the fully-qualified key
         * `"{namespace}{namespaceDelimiter}{name}"` using `fnmatch()`, so standard glob wildcards
         * (`*`, `?`, `[seq]`) are supported.
         *
         * Returns an opaque integer handle. Pass it to `offChange()` to unregister the observer
         * when it is no longer needed (e.g. on service shutdown).
         *
         * When Corvus is installed, cross-package subscribers should prefer attaching a Corvus
         * `Listener` to the `Signal::CHANGED` signal directly; `onChange()` is intended for
         * same-package or lightweight use.
         *
         * @param string   $pattern  A glob-style pattern to match against fully-qualified keys.
         * @param callable $listener Callback with signature
         *        `fn(string $key, string $namespace, mixed $old, mixed $new, static $config): void`.
         * @return int An opaque handle for use with `offChange()`.
         */
        public function onChange (string $pattern, callable $listener) : int {
            return $this->dispatcher->register($pattern, $listener);
        }

        /**
         * Removes the observer identified by `$handle` from the change-notification pipeline.
         * If the handle does not correspond to a live observer (e.g. it was already removed),
         * the call is silently ignored.
         * @param int $handle The handle returned by `onChange()`.
         * @return static The configuration.
         */
        public function offChange (int $handle) : static {
            $this->dispatcher->unregister($handle);
            return $this;
        }

        /**
         * Restores the configuration to the state captured by the last call to `snapshot()`.
         * If no snapshot has been taken, all buckets and constants are cleared entirely.
         * @return static The configuration.
         */
        public function reset () : static {
            if ($this->resetSnapshot !== null) {
                [$this->buckets, $this->constants, $this->frozenNamespaces, $this->frozen]
 = unserialize($this->resetSnapshot);
            }
            else {
                $this->buckets = [];
                $this->constants = [];
                $this->frozen = false;
                $this->frozenNamespaces = [];
            }

            return $this;
        }

        /**
         * Rolls every registered `Configuration` back to its last `snapshot()` point.
         *
         * Delegates to `ConfigurationRegistry::resetAll()`. This is the recommended
         * between-request hook for long-running runtimes (Octane, Swoole, RoadRunner,
         * ReactPHP). Pair it with a `$config->snapshot()` call at the end of your boot
         * sequence to pin the clean post-import state once, then replay it on every request.
         *
         * Configurations that have never called `snapshot()` are silently skipped.
         * @return void
         */
        public static function resetAll () : void {
            ConfigurationRegistry::resetAll();
        }

        /**
         * Restores the full configuration state from an array previously produced by `dumpCache()`.
         * All buckets, constants, delimiters, and the name are replaced. The reset snapshot is
         * cleared because the cache represents the final resolved state — there is nothing to roll back to.
         * This method is intended exclusively for use by `ConfigurationCache::load()`.
         * @param array $data The payload produced by `dumpCache()`.
         * @return static The configuration.
         */
        public function restoreCache (array $data) : static {
            $this->name = $data["name"] ?? $this->name;
            $this->namespaceDelimiter = $data["namespaceDelimiter"] ?? $this->namespaceDelimiter;
            $this->segmentDelimiter = $data["segmentDelimiter"] ?? $this->segmentDelimiter;
            $this->prefix = $data["prefix"] ?? "";
            $this->constants = $data["constants"] ?? [];
            $this->resetSnapshot = null;
            $this->buckets = [];

            $saved = $this->implicitNamespace;

            foreach ($data["buckets"] as $namespace => $bucketData) {
                $this->setImplicitNamespace($namespace)->merge($bucketData);
            }

            $this->setImplicitNamespace($saved);
            return $this;
        }

        /**
         * Restores the values of all `#[Configurable]`-annotated properties of `$object` from a
         * slot previously written by `captureObject()`. Writes are performed directly via reflection
         * regardless of property visibility.
         * @param object $object The object whose `#[Configurable]` properties should be restored.
         * @param string $name The label of the capture to restore.
         * @return static The configuration.
         * @throws RuntimeException If no capture with the given name exists for the object.
         */
        public function restoreObject (object $object, string $name) : static {
            ObjectHydrator::restore($object, $name, $this);
            return $this;
        }

        /**
         * Searches the configuration using a query pattern.
         * @param string $query The query pattern (e.g., "app[debug, version]").
         * @return static A new configuration instance containing the results.
         */
        public function search (string $query) : static {
            return QueryEngine::search($this, $query);
        }

        /**
         * Sets a value to a configuration.
         * @param string|UnitEnum|Variable $key The key to save a value at.
         * @param mixed $value The value to store.
         * @param bool $force Whether to force the value to be set even if it means overwriting a container (default: false).
         * @return static The configuration.
         * @throws ContainerOverwriteException If a container is being redefined without the force flag.
         * @throws ConstantViolationException If the key is registered as a constant.
         */
        public function set (string|UnitEnum|Variable $key, mixed $value, bool $force = false) : static {
            $variable = $this->normaliseKey($key);
            $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
            $constKey = $namespace . $this->namespaceDelimiter . $variable->getName();

            if (isset($this->constants[$constKey])) {
                throw new ConstantViolationException($constKey);
            }

            if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                throw new FrozenConfigurationException($constKey, $this->frozen ? null : $namespace);
            }

            $old = $this->getRaw($variable, null);
            $this->getBucket($namespace)->set($variable, $value, $force);
            $this->fireChange($variable->getName(), $namespace, $old, $value);
            return $this;
        }

        /**
         * Sets a constant variable in a configuration.
         * Once set, the value cannot be overridden via `set()` or `mergeFlat()`; any attempt
         * to do so will throw a `ConstantViolationException`. When an `$expression` is given
         * the value is validated against the Verix DSL before it is stored, so a wrong-type
         * value is rejected at the call site rather than at validation time.
         * @param string|UnitEnum|Variable $key        The key to define as a constant.
         * @param mixed                    $value      The value to assign.
         * @param string|null              $expression Optional Verix DSL expression the value must satisfy.
         * @return static The configuration.
         * @throws SchemaViolationException     If `$expression` is given and the value does not satisfy it.
         * @throws ConstantViolationException   If the key is already registered as a constant.
         * @throws FrozenConfigurationException If the configuration or namespace is frozen.
         */
        public function setConst (string|UnitEnum|Variable $key, mixed $value, ?string $expression = null) : static {
            $variable = $this->normaliseKey($key);
            $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
            $constKey = $namespace . $this->namespaceDelimiter . $variable->getName();

            if ($expression !== null) {
                $errors = (new Validator())->check($expression, $value);

                if (!empty($errors)) {
                    throw new SchemaViolationException($constKey, $expression, $errors);
                }
            }

            if (isset($this->constants[$constKey])) {
                throw new ConstantViolationException($constKey, true);
            }

            if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                throw new FrozenConfigurationException($constKey, $this->frozen ? null : $namespace);
            }

            $this->getBucket($namespace)->set($variable, $value);
            $this->constants[$constKey] = true;
            return $this;
        }

        /**
         * Replaces the owned Corvus emitter with the provided instance. Useful when the caller
         * needs to scope emissions to a specific bus or share one emitter across configurations.
         * Throws when the `Wingman\Corvus` package is not installed, because injecting a stub
         * in that context would silently swallow signal expectations.
         * @param CorvusEmitter $emitter The emitter to use.
         * @return static The configuration.
         * @throws MissingDependencyException If the `Wingman\Corvus` package is not installed.
         */
        public function setEmitter (CorvusEmitter $emitter) : static {
            $this->dispatcher->setEmitter($emitter);
            return $this;
        }

        /**
         * Sets the implicit namespace for resolving variables without an explicit namespace.
         * @param string $namespace The implicit namespace to set.
         * @return static The configuration.
         */
        public function setImplicitNamespace (string $namespace) : static {
            $this->implicitNamespace = $namespace;
            $this->parser->setDefaultNamespace($namespace);
            return $this;
        }

        /**
         * Sets the merge strategy to use for all subsequent `merge()` calls on this instance.
         * Use `MergeStrategy::REPLACE` (default) for backwards-compatible `array_replace_recursive`
         * behaviour, `MergeStrategy::APPEND` for `array_merge_recursive` semantics, or
         * `MergeStrategy::DEEP` for a deep merge that recursively combines associative arrays while
         * replacing indexed arrays entirely.
         * @param MergeStrategy $strategy The strategy to apply.
         * @return static The configuration.
         */
        public function setMergeStrategy (MergeStrategy $strategy) : static {
            $this->mergeStrategy = $strategy;
            return $this;
        }

        /**
         * Sets the name of a configuration, updating the static registry accordingly.
         * If the configuration previously had a name, it is removed from the registry.
         * Passing `null` or an empty string removes the configuration from the registry.
         * @param string|null $name The name to set for the configuration.
         * @return static The configuration.
         */
        public function setName (?string $name) : static {
            $oldName = $this->name;
            if ($oldName !== null) ConfigurationRegistry::unregister($oldName);

            $this->name = $name;

            if ($name !== null) {
                ConfigurationRegistry::register($name, $this);
                $this->parser->setDefaultEnvironment($name);
            }

            return $this;
        }

        /**
         * Sets the namespace delimiter for keys in a configuration.
         * @param string $delimiter The namespace delimiter to set.
         * @return static The configuration.
         */
        public function setNamespaceDelimiter (string $delimiter) : static {
            $this->namespaceDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the path delimiter for keys in a configuration.
         * @param string $delimiter The path delimiter to set.
         * @return static The configuration.
         */
        public function setPathDelimiter (string $delimiter) : static {
            $this->segmentDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the prefix for keys in a configuration.
         * @param string|null $prefix The prefix to set.
         * @return static The configuration.
         */
        public function setPrefix (?string $prefix) : static {
            $this->prefix = $prefix ?? "";
            return $this;
        }

        /**
         * Enables or disables strict mode for this configuration instance.
         * When strict mode is `true`, `merge()` throws a `ConstantViolationException` the moment
         * it encounters an incoming key that would overwrite a registered constant, aborting the
         * entire map without writing anything. When `false` (default), such keys are silently
         * skipped and a `Signal::CONSTANT_MERGE_SKIPPED` Corvus signal is emitted for each one.
         * @param bool $strict Whether to enable strict mode.
         * @return static The configuration.
         */
        public function setStrict (bool $strict) : static {
            $this->strict = $strict;
            return $this;
        }

        /**
         * Captures a point-in-time clone of the current bucket data, constants, frozen state, and
         * frozen namespaces for use by `reset()`. Call this once after all file imports are complete
         * so that any subsequent programmatic mutations can be rolled back cleanly.
         * Calling `snapshot()` again replaces the previous checkpoint.
         * @return static The configuration.
         */
        public function snapshot () : static {
            $this->resetSnapshot = serialize([
                $this->buckets,
                $this->constants,
                $this->frozenNamespaces,
                $this->frozen,
            ]);
            return $this;
        }

        /**
         * Unsets a key from a configuration.
         * Respects the same constant and freeze guards as `set()` — attempting to unset a
         * constant key throws a `ConstantViolationException`, and attempting to unset any
         * key in a frozen configuration or namespace throws a `FrozenConfigurationException`.
         * @param string|UnitEnum|Variable $key The key to unset.
         * @return static The configuration.
         * @throws ConstantViolationException   If the key is registered as a constant.
         * @throws FrozenConfigurationException If the configuration or namespace is frozen.
         */
        public function unset (string|UnitEnum|Variable $key) : static {
            $variable = $this->normaliseKey($key);
            $namespace = $variable->getNamespace() ?? $this->implicitNamespace;
            $constKey = $namespace . $this->namespaceDelimiter . $variable->getName();

            if (isset($this->constants[$constKey])) {
                throw new ConstantViolationException($constKey);
            }

            if ($this->frozen || isset($this->frozenNamespaces[$namespace])) {
                throw new FrozenConfigurationException($constKey, $this->frozen ? null : $namespace);
            }

            if (isset($this->buckets[$namespace])) {
                $this->buckets[$namespace]->remove(explode($this->segmentDelimiter, $variable->getName()));
            }

            return $this;
        }
    }
?>