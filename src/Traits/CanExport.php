<?php
    /*/
     * Project Name:    Wingman — Cortex — Exportable Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex traits namespace.
    namespace Wingman\Cortex\Traits;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Bucket;
    use Wingman\Cortex\ObjectHydrator;
    use Wingman\Cortex\Variable;

    /**
     * Provides the full export and serialisation surface for `Configuration`.
     *
     * Encapsulates every method that reads configuration state and converts it into
     * a portable representation:
     * - `export()` / `exportFlat()` — nested and flat array projections.
     * - `exportTo()` — atomic write to JSON or PHP file on disk.
     * - `toArray()` / `toJson()` — canonical read-only representations.
     *
     * All methods are pure readers; no mutation of `$this` state occurs here.
     *
     * @package Wingman\Cortex\Traits
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    trait CanExport {
        /**
         * Returns the configuration data as a nested array, optionally wrapped in the prefix path
         * and/or environment name.
         *
         * When `$withEnvironment` is `true` and the instance has a name, the result is wrapped in
         * an additional `[name => ...]` layer — the same structure produced by `importLayered()` when
         * environment-specific overrides are layered on top of a base file.
         *
         * Note: applying a prefix re-nests each namespace's content under the prefix segment path,
         * which is the inverse of the stripping done by `search()` and `getRaw()`.
         * @param bool $withEnvironment Whether to include the environment name as an outer key.
         * @return array The data of the configuration, optionally wrapped.
         */
        public function export (bool $withEnvironment = false) : array {
            $data = array_map(fn (Bucket $bucket) => $bucket->getData(), $this->buckets);

            if ($this->prefix === "") {
                return $data;
            }

            $parts = explode($this->segmentDelimiter, $this->prefix);
            $result = [];

            foreach ($data as $namespace => $content) {
                $result[$namespace] = [];
                $current = &$result[$namespace];

                foreach ($parts as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }

                $current = $content;
            }

            if ($withEnvironment && $this->name !== null) {
                $result = [$this->name => $result];
            }

            return $result;
        }

        /**
         * Exports the configuration in a flattened format, optionally including namespace and
         * environment names as key prefixes.
         *
         * When `$withNamespace` is `true` all buckets are merged into a single flat map keyed by
         * `"{namespace}/{key}"`. When `$withEnvironment` is additionally `true` and the instance has
         * a name, the environment name is prepended: `"{env}/{namespace}/{key}"`.
         * @param bool $withNamespace    Whether to include the namespace segment in each key.
         * @param bool $withEnvironment  Whether to include the environment name as an outer key (or key prefix when combined with `$withNamespace`).
         * @return array The flattened data of the configuration.
         */
        public function exportFlat (bool $withNamespace = false, bool $withEnvironment = false) : array {
            $result = [];

            foreach ($this->buckets as $bucket) {
                $result[$bucket->getName()] = $bucket->export(true, $withNamespace);
            }

            if ($withNamespace) {
                $result = array_merge(...array_values($result));

                if ($withEnvironment) {
                    $newResult = [];

                    foreach ($result as $key => $value) {
                        $variable = Variable::from($key)->withEnvironment($this->name);
                        $newResult[(string) $variable] = $value;
                    }

                    $result = $newResult;
                }
            } elseif ($withEnvironment && $this->name !== null) {
                $result = [$this->name => $result];
            }

            return $result;
        }

        /**
         * Writes the current configuration state to a file. The output format is determined by the
         * file extension: `.json` files are written as a JSON document, and all other extensions
         * produce a PHP file that returns an associative array via `return`.
         * The containing directory is created recursively when it does not exist.
         * Writes are atomic on POSIX systems thanks to `LOCK_EX`.
         * @param string $path The destination file path.
         * @return static The configuration.
         */
        public function exportTo (string $path) : static {
            $directory = dirname($path);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($extension === "json") {
                file_put_contents($path, $this->toJson(), LOCK_EX);
            } else {
                file_put_contents(
                    $path,
                    "<?php\n\n    return " . var_export($this->toArray(), true) . ";\n",
                    LOCK_EX
                );
            }

            return $this;
        }

        /**
         * Returns the raw namespace-keyed data map for all loaded buckets.
         * This is the canonical, user-facing export method; consider it the inverse of `merge()`.
         * Unlike `export()`, no prefix unwrapping or environment layering is applied.
         * @return array<string, array> A namespace-keyed map of resolved configuration data.
         */
        public function toArray () : array {
            return array_map(fn (Bucket $bucket) => $bucket->getData(), $this->buckets);
        }

        /**
         * Returns the raw namespace-keyed data map with all values associated with
         * `#[Sensitive]`-annotated properties of `$class` replaced by `$replacement`.
         *
         * Use this method instead of `toArray()` whenever the export is destined for logs,
         * dashboards, or any output context where sensitive values (passwords, tokens, secret keys)
         * must not appear in plaintext. The set of sensitive keys is resolved by
         * `ObjectHydrator::getSensitiveKeys()`, which honours any `#[ConfigGroup]` prefix declared
         * on the class.
         * @param string|object $class       A fully-qualified class name or object instance whose
         *                                   `#[Sensitive]` annotations define the redaction map.
         * @param string        $replacement The placeholder written over each sensitive value. Defaults to `"***"`.
         * @return array<string, array> A namespace-keyed data map with sensitive values masked.
         */
        public function toSafeArray (string|object $class, string $replacement = "***") : array {
            $sensitiveKeys = ObjectHydrator::getSensitiveKeys($class);
            $result = $this->toArray();

            foreach ($sensitiveKeys as $sensitiveKey) {
                $segments = explode($this->segmentDelimiter, $sensitiveKey);

                foreach ($result as $namespace => &$bucketData) {
                    $bucketData = static::redactNestedKey($bucketData, $segments, $replacement);
                }

                unset($bucketData);
            }

            return $result;
        }

        /**
         * Recursively walks a nested array and replaces the value located at the key path described
         * by `$segments` with `$replacement`. If any segment along the path is absent the array is
         * returned unchanged, making the operation non-destructive for partially-matching paths.
         * @param array    $data        The current sub-tree to walk.
         * @param string[] $segments    Remaining path segments pointing to the target leaf.
         * @param string   $replacement The replacement value to write at the target leaf.
         * @return array The tree with the target leaf replaced (or unchanged if absent).
         */
        private static function redactNestedKey (array $data, array $segments, string $replacement) : array {
            $head = array_shift($segments);

            if (!array_key_exists($head, $data)) return $data;

            if (empty($segments)) {
                $data[$head] = $replacement;
                return $data;
            }

            if (is_array($data[$head])) {
                $data[$head] = static::redactNestedKey($data[$head], $segments, $replacement);
            }

            return $data;
        }

        /**
         * Serialises the configuration to a JSON string.
         * Internally delegates to `toArray()`, so the same namespace-keyed structure applies.
         * @param int $flags JSON encoding flags forwarded to `json_encode()`. Defaults to
         *                   `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
         * @return string The JSON-encoded configuration.
         */
        public function toJson (int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : string {
            return json_encode($this->toArray(), $flags);
        }
    }
?>