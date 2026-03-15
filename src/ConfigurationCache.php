<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Cache
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use RuntimeException;

    /**
     * Handles the serialisation of a `Configuration` instance to a compiled PHP file and the
     * subsequent restoration of state from that file, fully decoupling persistence from the
     * configuration store itself.
     *
     * The compiled file is plain PHP that returns an array, which means PHP's opcode cache can
     * store it. Subsequent loads pay only the cost of reading a cached opcode — no parsing of
     * YAML, JSON, or INI files, no directory recursion, and no merge overhead.
     *
     * Typical bootstrap usage:
     * ```php
     * $cache = new ConfigurationCache('/path/to/bootstrap/cache/config.php');
     *
     * if ($cache->isStale()) {
     *     $config->importLayered('/path/to/config', 'production', ["snapshot" => true]);
     *     $cache->write($config);
     * } else {
     *     $cache->load($config);
     * }
     * ```
     *
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationCache {
        /**
         * The absolute path to the compiled cache file.
         * @var string
         */
        protected string $path;

        /**
         * Creates a new cache for the given file path.
         * @param string $path Absolute path to the compiled cache file.
         */
        public function __construct (string $path) {
            $this->path = $path;
        }

        /**
         * One-shot factory that wires together the cache and configuration in a single call.
         *
         * If the cache is fresh, the compiled state is loaded directly — no source files are
         * parsed, no merges are performed. If the cache is stale or absent, `$populate` is invoked
         * with the empty `Configuration`, which is responsible for loading all sources. The result
         * is then serialised to disk before being returned.
         *
         * Typical usage (true one-liner at the call site):
         * ```php
         * $config = ConfigurationCache::boot(
         *     __DIR__ . "/storage/config.cache.php",
         *     fn ($c) => $c->importLayered(__DIR__ . "/config", $_ENV["APP_ENV"] ?? null, ["snapshot" => true]),
         *     "app"
         * );
         * ```
         *
         * @param string      $path     Absolute path to the compiled cache file.
         * @param callable    $populate A callable that receives the empty `Configuration` and loads
         *                              all source data into it. Only called on a cache miss.
         * @param string|null $name     Optional name to register the `Configuration` under in the
         *                              global `ConfigurationRegistry`. Pass `null` for an anonymous
         *                              instance.
         * @return Configuration The fully-loaded configuration, regardless of which path was taken.
         */
        public static function boot (string $path, callable $populate, ?string $name = null) : Configuration {
            $cache = new static($path);
            $config = new Configuration($name);

            if ($cache->isStale()) {
                $populate($config);
                $cache->write($config);
            }
            else $cache->load($config);

            return $config;
        }

        /**
         * Checks whether a readable cache file exists at the configured path.
         * Unlike testing `file_exists()` directly, this also verifies that the file is readable
         * so that a follow-up call to `load()` will not fail with a permissions error.
         * @return bool `true` if the file exists and is readable.
         */
        public function exists () : bool {
            return is_file($this->path) && is_readable($this->path);
        }

        /**
         * Gets the absolute path to the cache file.
         * @return string The path.
         */
        public function getPath () : string {
            return $this->path;
        }

        /**
         * Determines whether the cache is stale by comparing the source-file modification times
         * stored in the cache payload against the current file-system state.
         *
         * Returns `true` (stale) when any of the following hold:
         * - The cache file does not exist or is not readable.
         * - The cache payload has no `"sources"` entry (written before 7.8 was implemented).
         * - Any recorded source path no longer exists on disk.
         * - Any recorded source path has a different `filemtime()` than when the cache was written.
         *
         * Returns `false` (fresh) only when the cache file is present and every source path exists
         * with an unchanged modification time.
         *
         * @return bool `true` if the cache should be regenerated, `false` if it can be used as-is.
         */
        public function isStale () : bool {
            if (!$this->exists()) {
                return true;
            }

            $data = @include $this->path;

            if (!is_array($data) || !isset($data["sources"])) {
                return true;
            }

            foreach ($data["sources"] as $path => $cachedMtime) {
                if (!file_exists($path) || filemtime($path) !== $cachedMtime) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Loads the compiled cache file and restores the full configuration state from it,
         * replacing whatever the given `Configuration` instance currently holds. All buckets,
         * constants, and delimiter settings are restored; `importedOperations` is cleared because
         * the cached state is already the final resolved form.
         * @param Configuration $config The configuration instance to restore into.
         * @return static The cache, for chaining.
         * @throws RuntimeException If the file does not exist or has an invalid format.
         */
        public function load (Configuration $config) : static {
            if (!is_file($this->path)) {
                throw new RuntimeException("Configuration cache file not found: {$this->path}.");
            }

            $data = require $this->path;

            if (!is_array($data) || !isset($data["buckets"])) {
                throw new RuntimeException("Invalid configuration cache format in: {$this->path}.");
            }

            $config->restoreCache($data);
            return $this;
        }

        /**
         * Serialises the fully-resolved state of the given `Configuration` instance to the
         * configured path. The directory is created automatically if it does not exist.
         *
         * The write is performed atomically: the content is first written to a temporary file in the
         * same directory, then renamed over the target path. On POSIX systems `rename()` is atomic,
         * which means any concurrent `include $path` in another worker either sees the complete old
         * file or the complete new file — never a partial write.
         * @param Configuration $config The configuration instance to serialise.
         * @return static The cache, for chaining.
         * @throws RuntimeException If the temporary file cannot be created or the rename fails.
         */
        public function write (Configuration $config) : static {
            $payload = $config->dumpCache();
            $dir = dirname($this->path);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = "<?php\n\n    # Generated by Wingman Cortex \u{2014} do not edit manually.\n"
                . "    # Generated: " . date("Y-m-d H:i:s") . "\n\n    return "
                . var_export($payload, true) . ";\n";

            $tmp = tempnam($dir, ".cortex_cache_");

            if ($tmp === false) {
                throw new RuntimeException("Failed to create a temporary cache file in: {$dir}.");
            }

            try {
                if (file_put_contents($tmp, $content, LOCK_EX) === false) {
                    throw new RuntimeException("Failed to write temporary cache file: {$tmp}.");
                }

                if (!rename($tmp, $this->path)) {
                    throw new RuntimeException("Failed to atomically replace cache file: {$this->path}.");
                }
            } catch (RuntimeException $e) {
                @unlink($tmp);
                throw $e;
            }

            return $this;
        }
    }
?>