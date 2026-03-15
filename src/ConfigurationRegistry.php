<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Registry
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use RuntimeException;

    /**
     * A static registry that tracks all named `Configuration` instances for the lifetime of the
     * process. Configurations register themselves on construction (or rename) and are removed when
     * renamed away from their previous name or when `unregister()` is called explicitly.
     *
     * Separating registry ownership from the `Configuration` data store means:
     * - `Configuration` has no knowledge of global state beyond delegating name changes here.
     * - The registry can be queried, extended, or replaced independently of the data store.
     * - Unit tests can reset the registry without needing a `Configuration` instance at all.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationRegistry {
        /**
         * The map of registered named `Configuration` instances, keyed by name.
         * @var array<string, Configuration>
         */
        private static array $entries = [];

        /**
         * Returns a named `Configuration` from the registry, or `null` when not found.
         * When `$name` is `null`, looks up `Configuration::DEFAULT_NAME`.
         * @param string|null $name The name to find; `null` resolves to `Configuration::DEFAULT_NAME`.
         * @return Configuration|null The registered instance, or `null`.
         */
        public static function get (?string $name = null) : ?Configuration {
            return static::$entries[$name ?? Configuration::DEFAULT_NAME] ?? null;
        }

        /**
         * Returns whether a named `Configuration` exists in the registry.
         * When `$name` is `null`, checks `Configuration::DEFAULT_NAME`.
         * @param string|null $name The name to check; `null` resolves to `Configuration::DEFAULT_NAME`.
         * @return bool Whether the configuration is registered.
         */
        public static function exists (?string $name = null) : bool {
            return isset(static::$entries[$name ?? Configuration::DEFAULT_NAME]);
        }

        /**
         * Returns all registered `Configuration` instances, keyed by name.
         * @return array<string, Configuration> The full registry.
         */
        public static function getAll () : array {
            return static::$entries;
        }

        /**
         * Returns the names of all registered `Configuration` instances.
         * @return string[] The registered names.
         */
        public static function getAllNames () : array {
            return array_keys(static::$entries);
        }

        /**
         * Registers a named `Configuration` instance in the global registry.
         * Called internally by `Configuration::__construct()` and `Configuration::setName()`.
         * @param string $name The name to register under.
         * @param Configuration $config The instance to register.
         * @return void
         * @throws RuntimeException If a configuration with the given name is already registered.
         */
        public static function register (string $name, Configuration $config) : void {
            if (isset(static::$entries[$name])) {
                throw new RuntimeException("Configuration with name '$name' already exists.");
            }

            static::$entries[$name] = $config;
        }

        /**
         * Removes all entries from the registry.
         * Intended for use in test teardown only. To roll back registered configurations between
         * requests in long-running runtimes, use `resetAll()` instead.
         * @return void
         */
        public static function reset () : void {
            static::$entries = [];
        }

        /**
         * Rolls every registered `Configuration` back to its last `snapshot()` point without
         * removing entries from the registry.
         *
         * This is the correct hook for long-running runtimes (Octane, Swoole, RoadRunner,
         * ReactPHP) where the same `Configuration` instances are reused across requests.
         * The recommended bootstrap pattern is:
         *
         * ```php
         * // 1. During app boot — load files, then pin the clean state:
         * $config->import('/path/to/config');
         * $config->snapshot();
         *
         * // 2. In your framework's between-request hook:
         * ConfigurationRegistry::resetAll();
         * ```
         *
         * Configurations that have never called `snapshot()` are silently skipped because
         * there is no baseline to roll back to.
         * @return void
         */
        public static function resetAll () : void {
            foreach (static::$entries as $config) {
                $config->reset();
            }
        }

        /**
         * Removes a named `Configuration` from the global registry.
         * Silently does nothing if the name is not registered.
         * Called internally by `Configuration::setName()` when a configuration is renamed or unnamed.
         * @param string $name The name to remove.
         * @return void
         */
        public static function unregister (string $name) : void {
            unset(static::$entries[$name]);
        }
    }
?>