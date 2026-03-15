<?php
    /*/
     * Project Name:    Wingman — Cortex — Importable Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex traits namespace.
    namespace Wingman\Cortex\Traits;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Registry;

    /**
     * Provides the file-import and namespace-registration surface for `Configuration`.
     *
     * Encapsulates the three methods that bring external data into the store:
     * - `import()` — loads one or more source files and merges them.
     * - `importLayered()` — loads a base source then auto-discovers and applies an
     *   environment-specific override (e.g. `database.production.php`).
     * - `registerNamespace()` — registers a lazy-load source deferred until first access.
     *
     * No configuration data is read here; all methods are write-path operations.
     *
     * @package Wingman\Cortex\Traits
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    trait CanImport {
        /**
         * Imports one or more maps into a configuration.
         *
         * Supported `$options` keys:
         * - `"parent"` *(string|null)* — parent path passed to the loader.
         * - `"mapDirectoryStructure"` *(bool|null)* — override the loader's directory-mapping default.
         * - `"parserOptions"` *(array)* — parser-specific options forwarded to the file parser.
         * - `"flat"` *(bool)* — when `true`, the loaded data is merged via `mergeFlat()` instead of
         *   the nested `merge()`.
         * - `"const"` *(bool)* — when `true`, every scalar leaf key in the loaded data is
         *   registered as a constant after merging, giving the keys the immutability semantics of
         *   real environment variables. Keys that are already constants are silently skipped.
         *   Note: `const` only protects against `set()` and `mergeFlat()` writes; a subsequent
         *   `merge()` call on the same namespace bypasses the constant check by design.
         * - `"snapshot"` *(bool)* — when `true`, `snapshot()` is called after all sources have been
         *   loaded, creating a named restore-point so that any subsequent programmatic mutations can
         *   be cleanly rolled back via `reset()`.
         *
         * @param string|array $sources A path or an array of paths.
         * @param array        $options Loader options as described above.
         * @return static The configuration.
         */
        public function import (string|array $sources, array $options = []) : static {
            $sources = (array) $sources;
            $parent = $options["parent"] ?? null;
            $mapStructure = $options["mapDirectoryStructure"] ?? null;
            $parserOptions = $options["parserOptions"] ?? [];
            $flat = $options["flat"] ?? false;
            $const = $options["const"] ?? false;
            $autoSnapshot = $options["snapshot"] ?? false;

            foreach ($sources as $source) {
                $data = $this->loader->load($source, $parserOptions, $parent, $mapStructure);
                $realSource = realpath($source) ?: $source;
                $this->loadedSources[$realSource] = is_file($realSource) || is_dir($realSource)
                    ? ((int) filemtime($realSource))
                    : 0;

                if ($flat) {
                    $this->mergeFlat($data);
                } else {
                    $this->merge($data);
                }

                if ($const) {
                    foreach (Registry::flatten($data, true, "", $this->segmentDelimiter) as $key => $value) {
                        if (!$this->isConst($key)) {
                            $this->setConst($key, $value);
                        }
                    }
                }
            }

            if ($autoSnapshot) $this->snapshot();

            return $this;
        }

        /**
         * Loads one or more configuration sources and, if `$env` is set, immediately deep-merges the
         * corresponding environment-specific override on top:
         *
         * - **File source** — after loading `database.php`, also loads `database.production.php` from
         *   the same directory if it exists.
         * - **Directory source** — after loading the base directory, also loads the `{env}/`
         *   sub-directory if it exists (each file maps to the same key as its base-dir counterpart).
         *
         * If `$env` is `null` only the base source(s) are loaded; no override search is performed.
         * Callers are responsible for determining the active environment and passing it in explicitly,
         * for example: `$config->importLayered($sources, $_ENV['MY_ENV'] ?? null)`.
         *
         * In addition to the options forwarded to `import()`, `$options` accepts:
         * - `"snapshot"` *(bool)* — when `true`, `snapshot()` is called after all sources (base and
         *   environment overrides) have been loaded. This creates a named restore-point so that any
         *   subsequent programmatic mutations can be rolled back cleanly via `reset()`, without
         *   replaying the full import pipeline. Equivalent to calling `importLayered(...)` followed
         *   immediately by `snapshot()`, but expressed in one call. Only the final snapshot is kept;
         *   repeated `importLayered()` calls with `"snapshot"` each replace the previous checkpoint.
         *
         * @param string|array $sources A single path or a list of paths (files or directories).
         * @param string|null  $env     The environment name, or `null` to skip override loading.
         * @param array        $options Loader options forwarded to `import()`, plus `"snapshot"` as above.
         * @return static The configuration.
         */
        public function importLayered (string|array $sources, ?string $env = null, array $options = []) : static {
            $autoSnapshot = $options["snapshot"] ?? false;
            $importOptions = array_diff_key($options, ["snapshot" => true]);

            foreach ((array) $sources as $source) {
                $this->import($source, $importOptions);

                if ($env === null || !is_string($source)) {
                    continue;
                }

                if (is_file($source)) {
                    $info = pathinfo($source);
                    $suffix = isset($info["extension"]) ? '.' . $info["extension"] : '';
                    $override = $info["dirname"] . '/' . $info["filename"] . '.' . $env . $suffix;

                    if (file_exists($override)) {
                        $this->import($override, $importOptions);
                    }
                } elseif (is_dir($source)) {
                    $envDir = rtrim($source, '/') . '/' . $env;

                    if (is_dir($envDir)) {
                        $this->import($envDir, $importOptions);
                    }
                }
            }

            if ($autoSnapshot) {
                $this->snapshot();
            }

            return $this;
        }

        /**
         * Reads all environment variables (from `$_ENV` and `getenv()`) whose names start with
         * `$prefix`, strips the prefix, converts the remainder to dot-notation, and merges the
         * resulting map into the configuration via `mergeFlat()`.
         *
         * Conversion rules:
         * - The `$prefix` (if non-empty) is removed from the key name before conversion.
         * - Each `$separator` character in the stripped name is replaced with `.`.
         * - The entire key is lower-cased so that `APP_DB_HOST` → `app.db.host`.
         *
         * Example:
         * ```php
         * // $_ENV = ['APP_DB_HOST' => 'localhost', 'APP_DB_PORT' => '5432', 'PATH' => '...']
         * $config->mapEnvKeys('APP_');
         * // Merges: ['db.host' => 'localhost', 'db.port' => '5432']
         * ```
         *
         * When `$prefix` is an empty string no filtering is applied and every environment variable
         * is mapped. This is useful for generating a complete dot-notation mirror of `$_ENV`.
         *
         * @param string $prefix    Only map variables whose names start with this string.
         *                          Case-sensitive. Pass `""` to map all variables.
         * @param string $separator The character in the environment variable name that maps to a `.`
         *                          in the dot-notation key. Defaults to `"_"`.
         * @return static The configuration.
         */
        public function mapEnvKeys (string $prefix = "", string $separator = "_") : static {
            $envAll = is_array($raw = getenv()) ? $raw : [];
            $env = array_merge($envAll, $_ENV);
            $prefixLen = strlen($prefix);
            $mapped = [];

            foreach ($env as $key => $value) {
                if ($prefix !== "" && !str_starts_with((string) $key, $prefix)) {
                    continue;
                }

                $stripped = $prefix !== "" ? substr((string) $key, $prefixLen) : (string) $key;
                $dotKey = strtolower(str_replace($separator, ".", $stripped));
                $mapped[$dotKey] = $value;
            }

            return $this->mergeFlat($mapped);
        }

        /**
         * Registers a deferred load source for `$namespace`. The source is not imported immediately;
         * instead it is recorded and consumed on the first access that triggers a bucket lookup for
         * the namespace. Does nothing if the namespace bucket has already been created.
         * @param string       $namespace The namespace to register the lazy source for.
         * @param string|array $source    The file path or array of paths to load when triggered.
         * @param array        $options   Loader options forwarded to `import()` when the load fires.
         * @return static The configuration.
         */
        public function registerNamespace (string $namespace, string|array $source, array $options = []) : static {
            if (isset($this->buckets[$namespace])) {
                return $this;
            }

            $this->lazyNamespaces[$namespace] = [
                "source" => $source,
                "options" => $options
            ];

            return $this;
        }
    }
?>