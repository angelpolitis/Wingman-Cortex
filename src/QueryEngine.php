<?php
    /*/
     * Project Name:    Wingman — Cortex — Query Engine
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    /**
     * Stateless service that executes Cortex DSL queries against a `Configuration` instance.
     *
     * Responsibilities:
     * - Walk a key-tree and collect entries whose segment path matches a compiled query pattern
     *   (via `fnmatch()`-style wildcards), returning a flat `path → value` map.
     * - Evaluate a compiled pattern set against all relevant buckets and return the matching
     *   data as a nested array, identical in shape to `Registry::unflatten()` output.
     * - Normalise a single-entry result tree into the concrete PHP value or a `Scope` proxy,
     *   so `getRaw()` can return the expected scalar/array/Scope without extra work.
     * - Implement the `search()` and `except()` public APIs, now that those are too large to
     *   live inline on `Configuration`.
     *
     * All methods are static and side-effect-free with respect to the engine itself; the
     * `Configuration $config` argument is read-only except where a snapshot is assembled.
     *
     * @package Wingman\Cortex
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class QueryEngine {
        /**
         * Recursively walks `$tree`, collecting every entry whose segment path matches `$remaining`.
         * Wildcards (`*`) match any single key. A flat `implode($segmentDelimiter, $path) => $value`
         * map is built into `$output` by reference.
         * @param string $segmentDelimiter The delimiter used to join path segments into a key string.
         * @param array $remaining Unprocessed query segments; the first element is consumed on each call.
         * @param array $tree The current sub-tree being traversed.
         * @param array $path Segments accumulated on the way to the current node.
         * @param array &$output Flat map populated with matched `path => value` pairs.
         */
        private static function collectMatches (
            string $segmentDelimiter,
            array $remaining,
            array $tree,
            array $path,
            array &$output
        ) : void {
            if (empty($remaining)) return;

            $segment = array_shift($remaining);

            foreach ($tree as $key => $value) {
                if ($segment !== '*' && $segment !== (string) $key) continue;

                $currentPath = [...$path, $key];

                if (empty($remaining)) {
                    $output[implode($segmentDelimiter, $currentPath)] = $value;
                    continue;
                }

                if (is_array($value)) {
                    static::collectMatches($segmentDelimiter, $remaining, $value, $currentPath, $output);
                }
            }
        }

        /**
         * Returns the full configuration export with all keys matched by the given queries removed.
         * Useful for safe serialisation and logging where sensitive keys must be omitted.
         * Each query uses the standard Cortex DSL (the same syntax accepted by `search()`),
         * e.g. `["[security]", "db[password]"]` to strip an entire namespace and a single key.
         * @param Configuration $config The configuration whose data is filtered.
         * @param array<string> $queries One or more Cortex DSL queries whose matched keys are excluded.
         * @return array<string, mixed> Nested array matching `toArray()` output, minus excluded keys.
         */
        public static function except (Configuration $config, array $queries) : array {
            $name = $config->getName();
            $parser = $config->getParser();
            $segmentDelimiter = $config->getSegmentDelimiter();
            $data = $config->toArray();

            foreach ($queries as $query) {
                $patterns = $parser->compile((string) $query);

                foreach ($patterns as $environment => $namespaces) {
                    if ($name !== null && $environment !== $name) continue;
                    if ($name === null && $environment !== $parser->getDefaultEnvironment()) continue;

                    foreach ($namespaces as $ns => $rules) {
                        if (!array_key_exists($ns, $data)) continue;

                        $sourceBucket = $config->getBucket($ns);

                        foreach ($rules as $rule) {
                            $matches = [];
                            static::collectMatches($segmentDelimiter, $rule["segments"], $sourceBucket->getData(), [], $matches);

                            foreach (array_keys($matches) as $path) {
                                $segments = explode($segmentDelimiter, $path);
                                $ref = &$data[$ns];

                                foreach (array_slice($segments, 0, -1) as $seg) {
                                    if (!is_array($ref) || !array_key_exists($seg, $ref)) continue 2;
                                    $ref = &$ref[$seg];
                                }

                                unset($ref[end($segments)]);
                                unset($ref);
                            }
                        }
                    }
                }
            }

            return $data;
        }

        /**
         * Evaluates compiled query patterns against the configuration's buckets and returns matching
         * data as a nested array in `Registry::unflatten()` shape, scoped to the configuration's prefix.
         * @param Configuration $config The configuration to query.
         * @param array $patterns Compiled pattern set from `QueryParser::compile()`.
         * @return array<string, mixed> Nested key-value array of all matched entries.
         */
        public static function extractQuery (Configuration $config, array $patterns) : array {
            $name = $config->getName();
            $parser = $config->getParser();
            $segmentDelimiter = $config->getSegmentDelimiter();
            $prefix = $config->getPrefix();

            $resultSet = $config->createSnapshot();
            $resultSet->setPrefix($prefix);

            foreach ($patterns as $environment => $namespaces) {
                if ($name !== null && $environment !== $name) continue;
                if ($name === null && $environment !== $parser->getDefaultEnvironment()) continue;

                foreach ($namespaces as $namespace => $queryData) {
                    if (!$config->hasNamespace($namespace)) continue;

                    $bucket = $config->getBucket($namespace);

                    foreach ($queryData as $pattern) {
                        $matches = [];
                        static::collectMatches($segmentDelimiter, $pattern["segments"], $bucket->getData(), [], $matches);

                        foreach ($matches as $path => $value) {
                            $resultSet->set(new Variable($path, $namespace, $name), $value, true);
                        }
                    }
                }
            }

            return Registry::unflatten(
                $resultSet->export(true),
                $prefix,
                true,
                $segmentDelimiter
            );
        }

        /**
         * Returns a new `Configuration` snapshot containing only the keys whose paths match `$query`.
         * The snapshot inherits the source configuration's settings (delimiters, prefix, name) but
         * carries no other data. Useful for narrowing a configuration down to a relevant slice.
         * @param Configuration $config The configuration to search.
         * @param string $query A Cortex DSL query string (e.g. `"app[debug, version]"`).
         * @return Configuration A new configuration instance containing only the matched keys.
         */
        public static function search (Configuration $config, string $query) : Configuration {
            $name = $config->getName();
            $parser = $config->getParser();
            $segmentDelimiter = $config->getSegmentDelimiter();
            $patterns = $parser->compile($query);
            $snapshot = $config->createSnapshot();

            foreach ($patterns as $environment => $namespaces) {
                if ($name !== null && $environment !== $name) continue;
                if ($name === null && $environment !== $parser->getDefaultEnvironment()) continue;

                foreach ($namespaces as $ns => $rules) {
                    if (!$config->hasNamespace($ns)) continue;

                    $sourceBucket = $config->getBucket($ns);

                    foreach ($rules as $rule) {
                        $matches = [];
                        static::collectMatches($segmentDelimiter, $rule["segments"], $sourceBucket->getData(), [], $matches);

                        foreach ($matches as $path => $value) {
                            $snapshot->set(new Variable($path, $ns, $name), $value, true);
                        }
                    }
                }
            }

            return $snapshot;
        }

        /**
         * Unwraps a single-entry result tree produced by `extractQuery()` into the concrete PHP
         * value that the caller expects. Single-element arrays are peeled layer by layer; the first
         * key that resolves to a known namespace is recorded as `$detectedNamespace` and excluded
         * from the path. A multi-entry array becomes a `Scope` proxy; a scalar is returned as-is.
         * @param Configuration $config  The owning configuration (used for namespace check and Scope construction).
         * @param array|Configuration $results The result map returned by `extractQuery()`.
         * @return mixed A scalar, or a `Scope` for multi-key results.
         */
        public static function unwrapSelection (Configuration $config, array|Configuration $results) : mixed {
            $data = ($results instanceof Configuration) ? $results->export() : $results;

            $currentPath = [];
            $detectedNamespace = $config->getImplicitNamespace();

            while (is_array($data) && count($data) === 1) {
                $key = key($data);

                if (empty($currentPath) && $config->hasNamespace($key)) {
                    $detectedNamespace = $key;
                }
                else $currentPath[] = $key;

                $data = reset($data);
            }

            if (is_array($data)) {
                $pathString = implode($config->getSegmentDelimiter(), $currentPath);
                return new Scope($config, $pathString, $detectedNamespace);
            }

            return $data;
        }
    }
?>