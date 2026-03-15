<?php
    /*/
	 * Project Name:    Wingman — Cortex — Query Parser
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 09 2025
	 * Last Modified:   Feb 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    /**
     * A class that provides parsing capabilities for Cortex queries.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryParser {
        /**
         * The default command delimiter used to separate multiple queries in a single string.
         * @var string
         */
        public const string DEFAULT_COMMAND_DELIMITER = ";";

        /**
         * The default environment delimiter used to separate the environment from the rest of the query.
         * @var string
         */
        public const string DEFAULT_ENVIRONMENT_DELIMITER = "::";

        /**
         * The default environment name used when no environment is specified in a query.
         * @var string
         */
        public const string DEFAULT_ENVIRONMENT_NAME = "default";

        /**
         * The default group end delimiter used to signify the end of a group.
         * @var string
         */
        public const string DEFAULT_GROUP_END_DELIMITER = ']';

        /**
         * The default group start delimiter used to signify the start of a group.
         * @var string
         */
        public const string DEFAULT_GROUP_START_DELIMITER = '[';

        /**
         * The default namespace delimiter used to separate the namespace from the rest of the query.
         * @var string
         */
        public const string DEFAULT_NAMESPACE_DELIMITER = ":";

        /**
         * The default namespace name used when no namespace is specified in a query.
         * @var string
         */
        public const string DEFAULT_NAMESPACE_NAME = "default";

        /**
         * The default segment delimiter used to separate segments in a path.
         * @var string
         */
        public const string DEFAULT_SEGMENT_DELIMITER = ".";

        /**
         * The default value delimiter used to separate values in a group.
         * @var string
         */
        public const string DEFAULT_VALUE_DELIMITER = ",";

        /**
         * The default token used to represent a wildcard in a query.
         * @var string
         */
        public const string DEFAULT_WILDCARD_TOKEN = '*';

        /**
         * The cache of a query parser; maps raw query strings to their compiled forms.
         * @var array<string, mixed>
         */
        protected array $cache = [];

        /**
         * The delimiter for separating multiple queries in a single string.
         * @var string
         */
        protected string $commandDelimiter = self::DEFAULT_COMMAND_DELIMITER;

        /**
         * The delimiter for separating the environment from the rest of the query.
         * @var string
         */
        protected string $environmentDelimiter = self::DEFAULT_ENVIRONMENT_DELIMITER;

        /**
         * The delimiter for the end of a group.
         * @var string
         */
        protected string $groupEndDelimiter = self::DEFAULT_GROUP_END_DELIMITER;

        /**
         * The delimiter for the start of a group.
         * @var string
         */
        protected string $groupStartDelimiter = self::DEFAULT_GROUP_START_DELIMITER;

        /**
         * The delimiter for separating namespaces from the rest of the query.
         * @var string
         */
        protected string $namespaceDelimiter = self::DEFAULT_NAMESPACE_DELIMITER;

        /**
         * The delimiter for separating segments in a path.
         * @var string
         */
        protected string $segmentDelimiter = self::DEFAULT_SEGMENT_DELIMITER;

        /**
         * The delimiter for separating values in a group.
         * @var string
         */
        protected string $valueDelimiter = self::DEFAULT_VALUE_DELIMITER;

        /**
         * The token for representing a wildcard.
         * @var string
         */
        protected string $wildcardToken = self::DEFAULT_WILDCARD_TOKEN;

        /**
         * The default environment to use when none is specified in a query.
         * @var string
         */
        protected string $defaultEnvironment;

        /**
         * The default namespace to use when none is specified in a query.
         * @var string
         */
        protected string $defaultNamespace;

        /**
         * Creates a new query parser.
         * @param string $defaultEnvironment The default environment to use.
         * @param string $defaultNamespace The default namespace to use.
         * @param string $environmentDelimiter The delimiter for separating environment from the rest of the query (default "::").
         * @param string $namespaceDelimiter The delimiter for separating namespace from the rest of the query (default ":").
         * @param string $segmentDelimiter The delimiter for separating segments in a path (default ".").
         * @param string $valueDelimiter The delimiter for separating values in a group (default ",").
         * @param string $commandDelimiter The delimiter for separating multiple queries (default ";").
         * @param string $groupStartDelimiter The delimiter for the start of a group (default "[").
         * @param string $groupEndDelimiter The delimiter for the end of a group (default "]").
         * @param string $wildcardToken The token for representing a wildcard (default "*").
         */
        public function __construct (
            string $defaultEnvironment = self::DEFAULT_ENVIRONMENT_NAME,
            string $defaultNamespace = self::DEFAULT_NAMESPACE_NAME,
            string $environmentDelimiter = self::DEFAULT_ENVIRONMENT_DELIMITER,
            string $namespaceDelimiter = self::DEFAULT_NAMESPACE_DELIMITER,
            string $segmentDelimiter = self::DEFAULT_SEGMENT_DELIMITER,
            string $valueDelimiter = self::DEFAULT_VALUE_DELIMITER,
            string $commandDelimiter = self::DEFAULT_COMMAND_DELIMITER,
            string $groupStartDelimiter = self::DEFAULT_GROUP_START_DELIMITER,
            string $groupEndDelimiter = self::DEFAULT_GROUP_END_DELIMITER,
            string $wildcardToken = self::DEFAULT_WILDCARD_TOKEN

        ) {
            $this->defaultEnvironment = $defaultEnvironment;
            $this->defaultNamespace = $defaultNamespace;
            $this->environmentDelimiter = $environmentDelimiter;
            $this->namespaceDelimiter = $namespaceDelimiter;
            $this->segmentDelimiter = $segmentDelimiter;
            $this->valueDelimiter = $valueDelimiter;
            $this->commandDelimiter = $commandDelimiter;
            $this->groupStartDelimiter = $groupStartDelimiter;
            $this->groupEndDelimiter = $groupEndDelimiter;
            $this->wildcardToken = $wildcardToken;
        }

        /**
         * Expands a namespace expression into individual paths.
         * This method handles grouping (e.g., "app[db, security]") and wildcards.
         * @param string $expr The namespace expression to expand.
         * @return array An array of expanded paths (e.g., ["app.db", "app.security"]).
         */
        protected function expandNamespaceExpression (string $expr) : array {
            $items = $this->splitTopLevel($expr, $this->valueDelimiter);
            $output = [];

            foreach ($items as $item) {
                $item = trim($item);
                if ($item === "") continue;
                foreach ($this->expandToken($item) as $segments) {
                    $output[] = implode($this->segmentDelimiter, $segments);
                }
            }

            return $output;
        }

        /**
         * Recursively expands a token, handling grouping and wildcards.
         * @param string $token The token to expand (e.g., "app[db, security]").
         * @return array An array of segment arrays (e.g., [["app", "db"], ["app", "security"]]).
         */
        protected function expandToken (string $token) : array {
            $token = trim($token);

            # 1. Find the position of the first top-level group.
            $pos = $this->findTopLevelGroup($token);
            if ($pos === -1) {
                return [$this->explodeSegments($token)];
            }

            # 2. Extract prefix, inner group content, and suffix.
            $prefix = trim(substr($token, 0, $pos));
            $inner = $this->extractGroup($token, $pos);

            # 3. Find the end of the group to determine the suffix.
            $end = $this->findMatchingGroupEnd($token, $pos);
            $suffix = trim(substr($token, $end + 1));

            $prefixSeg = $prefix === "" ? [] : $this->explodeSegments($prefix);
            
            # 4. Expand the suffix if it exists; otherwise, treat it as an empty expansion.
            if ($suffix === "") {
                $suffixExpansions = [[]];
            }
            else {
                $suffixExpansions = [];
                foreach ($this->expandToken($suffix) as $sx) {
                    $suffixExpansions[] = $sx;
                }
            }

            # 5. Split the inner content by commas to get child tokens, then recursively expand each child token.
            $childrenRaw = $this->splitTopLevel($inner, $this->valueDelimiter);
            $children = [];

            foreach ($childrenRaw as $child) {
                $child = trim($child);
                if ($child === "") continue;
                foreach ($this->expandToken($child) as $childSeg) {
                    $children[] = $childSeg;
                }
            }

            # 6. For each combination of prefix, child, and suffix segments, merge them into a single path and add to output.
            $out = [];
            foreach ($children as $childSeg) {
                foreach ($suffixExpansions as $suffixSeg) {
                    $out[] = array_merge($prefixSeg, $childSeg, $suffixSeg);
                }
                
            }
            return $out;
        }

        /**
         * Splits a string into segments based on the segment delimiter, treating consecutive delimiters as a single separator and ignoring empty segments.
         * This method is used to break down paths into their constituent parts for matching and processing.
         * @param string $s The string to split into segments (e.g., "app.db.connection").
         * @return array An array of segments (e.g., ["app", "db", "connection"]).
         */
        protected function explodeSegments (string $s) : array {
            return preg_split('/' . preg_quote($this->segmentDelimiter, '/') . '+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        }

        /**
         * Extracts the content within a pair of matching groups starting from a given index.
         * This method is used to retrieve the inner expression for further processing.
         * @param string $string The string containing the groups.
         * @param int $start The index of the opening group.
         * @return string|null The content within the groups, or null if no valid groups are found.
         */
        protected function extractGroup (string $string, int $start) : ?string {
            $end = $this->findMatchingGroupEnd($string, $start);
            if ($end <= $start) return null;
            return substr($string, $start + 1, $end - $start - 1);
        }

        /**
         * Finds the index of the matching closing group for a given opening group in a string.
         * This method accounts for nested groups to ensure correct matching.
         * @param string $s The string to search within.
         * @param int $start The index of the opening group to match.
         * @return int The index of the matching closing group, or the end of the string if not found.
         */
        protected function findMatchingGroupEnd (string $s, int $start) : int {
            $depth = 0;
            for ($i = $start; $i < strlen($s); $i++) {
                if ($s[$i] === $this->groupStartDelimiter) $depth++;
                elseif ($s[$i] === $this->groupEndDelimiter) {
                    $depth--;
                    if ($depth === 0) return $i;
                }
            }
            return strlen($s) - 1;
        }

        /**
         * Finds the index of the first top-level opening group in a string.
         * This method is used to identify the start of grouped expressions for proper parsing.
         * @param string $string The string to search for the top-level group.
         * @return int The index of the first top-level opening group, or -1 if none is found.
         */
        protected function findTopLevelGroup (string $string) : int {
            $level = 0;
            for ($i = 0, $l = strlen($string); $i < $l; $i++) {
                if ($string[$i] === $this->groupStartDelimiter && $level === 0) return $i;
                if ($string[$i] === $this->groupStartDelimiter) $level++;
                if ($string[$i] === $this->groupEndDelimiter) $level--;
            }
            return -1;
        }

        /**
         * Splits a string by a separator at the top level, ignoring separators that are inside groups.
         * This method is used to correctly split expressions while respecting grouping indicated by groups.
         * @param string $string The string to split (e.g., "app[db, security], sys").
         * @param string $separator The separator to split by (e.g., ",").
         * @return array An array of split segments (e.g., ["app[db, security]", "sys"]).
         */
        protected function splitTopLevel (string $string, string $separator) : array {
            $level = 0; 
            $lastPos = 0; 
            $out = [];
            $l = strlen($string);

            for ($i = 0; $i < $l; $i++) {
                $char = $string[$i];
                if ($char === $this->groupStartDelimiter) $level++;
                elseif ($char === $this->groupEndDelimiter) $level--;
                elseif ($char === $separator && $level === 0) {
                    $out[] = trim(substr($string, $lastPos, $i - $lastPos));
                    $lastPos = $i + 1;
                }
            }

            if ($lastPos < $l) {
                $out[] = trim(substr($string, $lastPos));
            }
            
            return $out;
        }

        /**
         * Compiles a query string into a normalised match map.
         * This method utilises an internal cache to avoid redundant parsing of identical queries.
         * @param string $query The raw query string (e.g., "ns: app[db, security]; sys: *").
         * @return array A normalised array of namespaces and segment-based patterns.
         */
        public function compile (string $query) : array {
            if (isset($this->cache[$query])) {
                return $this->cache[$query];
            }

            $parsed = $this->parse($query);
            $normalised = $this->normalise($parsed);

            return $this->cache[$query] = $normalised;
        }

        /**
         * Gets the command delimiter.
         * @return string The command delimiter.
         */

        public function getCommandDelimiter () : string {
            return $this->commandDelimiter;
        }

        /**
         * Gets the default environment.
         * @return string The default environment.
         */
        public function getDefaultEnvironment () : string {
            return $this->defaultEnvironment;
        }

        /**
         * Gets the default namespace.
         * @return string The default namespace.
         */
        public function getDefaultNamespace () : string {
            return $this->defaultNamespace;
        }

        /**
         * Gets the environment delimiter.
         * @return string The environment delimiter.
         */

        public function getEnvironmentDelimiter () : string {
            return $this->environmentDelimiter;
        }

        /**
         * Gets the group end delimiter.
         * @return string The group end delimiter.
         */
        public function getGroupEndDelimiter () : string {
            return $this->groupEndDelimiter;
        }

        /**
         * Gets the group start delimiter.
         * @return string The group start delimiter.
         */
        public function getGroupStartDelimiter () : string {
            return $this->groupStartDelimiter;
        }

        /**
         * Gets the namespace delimiter.
         * @return string The namespace delimiter.
         */
        public function getNamespaceDelimiter () : string {
            return $this->namespaceDelimiter;
        }

        /**
         * Gets the segment delimiter.
         * @return string The segment delimiter.
         */
        public function getSegmentDelimiter () : string {
            return $this->segmentDelimiter;
        }

        /**
         * Gets the value delimiter.
         * @return string The value delimiter.
         */
        public function getValueDelimiter () : string {
            return $this->valueDelimiter;
        }

        /**
         * Gets the wildcard token.
         * @return string The wildcard token.
         */
        public function getWildcardToken () : string {
            return $this->wildcardToken;
        }

        /**
         * Checks whether a pattern of segments matches a data path of segments, considering wildcards.
         * @param array $pattern An array of pattern segments, where '*' is treated as a wildcard.
         * @param array $dataPath An array of data path segments to match against the pattern.
         * @return bool Whether the pattern matches the data path.
         */
        public function matchSegments (array $pattern, array $dataPath) : bool {
            $plen = count($pattern);
            $dlen = count($dataPath);

            if ($plen !== $dlen) return false;

            for ($i = 0; $i < $plen; $i++) {
                if ($pattern[$i] === $this->wildcardToken) continue;
                if ($pattern[$i] !== $dataPath[$i]) return false;
            }

            return true;
        }

        /**
         * Normalises a parsed query into a structured format suitable for matching.
         * It categorises paths based on whether they contain wildcards and prepares them for efficient matching.
         * @param array $parsed The initial parsed map of namespaces to raw paths.
         * @return array A normalised map of namespaces to structured patterns with match types.
         */
        public function normalise (array $parsed) : array {
            $normalised = [];
            foreach ($parsed as $env => $namespaces) {
                foreach ($namespaces as $ns => $paths) {
                    foreach ($paths as $path) {
                        $segments = explode($this->segmentDelimiter, $path);
                        $normalised[$env][$ns][] = [
                            "segments" => $segments,
                            "matchType" => in_array($this->wildcardToken, $segments, true) ? "wildcard" : "exact"
                        ];
                    }
                }
            }
            return $normalised;
        }

        /**
         * Parses a query into an initial namespace-grouped expansion map.
         * It separates multiple queries by semicolons and identifies the target namespace 
         * before delegating to the expansion engine.
         * @param string $input a query.
         * @return array An associative array where keys are namespaces and values are expanded paths.
         */
        public function parse (string $input) : array {
            $result = [];

            # 1. Split by semicolons to separate groups.
            $groups = array_filter(array_map("trim", explode($this->commandDelimiter, $input)), fn ($v) => $v !== "");

            foreach ($groups as $group) {
                $env = null;
                $ns = $this->defaultNamespace;
                $expr = $group;

                # 2. Extract Environment (e.g., "wingman::").
                if (($envPos = strpos($expr, $this->environmentDelimiter)) !== false) {
                    $env = trim(substr($expr, 0, $envPos));
                    $expr = trim(substr($expr, $envPos + strlen($this->environmentDelimiter)));
                    if ($env === "") $env = null;
                }

                # 3. Extract Namespace (e.g., "html:").
                if (($nsPos = strpos($expr, $this->namespaceDelimiter)) !== false) {
                    $ns = trim(substr($expr, 0, $nsPos));
                    $expr = trim(substr($expr, $nsPos + strlen($this->namespaceDelimiter)));
                    if ($ns === "") $ns = $this->defaultNamespace;
                }

                $expanded = $this->expandNamespaceExpression($expr);

                # 4. Group results by Environment then Namespace.
                $envKey = $env ?? $this->defaultEnvironment;
                if (!isset($result[$envKey])) $result[$envKey] = [];
                if (!isset($result[$envKey][$ns])) $result[$envKey][$ns] = [];
                
                $result[$envKey][$ns] = array_merge($result[$envKey][$ns], $expanded);
            }

            return $result;
        }

        /**
         * Resolves a query string into an array of discrete Variable objects.
         * @param string $input The raw query (e.g., "frq:app.debug.type ; wm:script[startTime, referrer]")
         * @return Variable[] An array of Variable instances.
         */
        public function resolve (string $input) : array {
            $variables = [];
            $parsed = $this->parse($input);

            foreach ($parsed as $env => $namespaces) {
                foreach ($namespaces as $ns => $paths) {
                    foreach ($paths as $path) {
                        $variables[] = new Variable($path, $ns, $env);
                    }
                }
            }

            return $variables;
        }

        /**
         * Sets the command delimiter used to separate multiple queries in a single string.
         * @param string $delimiter The new command delimiter (e.g., ";").
         * @return static The parser.
         */
        public function setCommandDelimiter (string $delimiter) : static {
            $this->commandDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the default environment to use when none is specified in a query.
         * @param string $env The new default environment (e.g., "main").
         * @return static The parser.
         */
        public function setDefaultEnvironment (string $env) : static {
            $this->defaultEnvironment = $env;
            return $this;
        }

        /**
         * Sets the default namespace to use when none is specified in a query.
         * @param string $ns The new default namespace (e.g., "default").
         * @return static The parser.
         */
        public function setDefaultNamespace (string $ns) : static {
            $this->defaultNamespace = $ns;
            return $this;
        }

        /**
         * Sets the group delimiters used to define groups in namespace expressions.
         * @param string|null $start The new group start delimiter (e.g., "[").
         * @param string|null $end The new group end delimiter (e.g., "]").
         * @return static The parser.
         */
        public function setGroupDelimiters (?string $start = null, ?string $end = null) : static {
            if ($start !== null) $this->groupStartDelimiter = $start;
            if ($end !== null) $this->groupEndDelimiter = $end;
            return $this;
        }

        /**
         * Sets the environment delimiter used to separate the environment from the rest of the query.
         * @param string $delimiter The new environment delimiter (e.g., "::").
         * @return static The parser.
         */
        public function setEnvironmentDelimiter (string $delimiter) : static {
            $this->environmentDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the namespace delimiter used to separate the namespace from the rest of the query.
         * @param string $delimiter The new namespace delimiter (e.g., ":").
         * @return static The parser.
         */
        public function setNamespaceDelimiter (string $delimiter) : static {
            $this->namespaceDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the segment delimiter used to separate segments in a path.
         * @param string $delimiter The new segment delimiter (e.g., ".").
         * @return static The parser.
         */
        public function setSegmentDelimiter (string $delimiter) : static {
            $this->segmentDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the value delimiter used to separate values in a group.
         * @param string $delimiter The new value delimiter (e.g., ",").
         * @return static The parser.
         */
        public function setValueDelimiter (string $delimiter) : static {
            $this->valueDelimiter = $delimiter;
            return $this;
        }

        /**
         * Sets the wildcard token used to represent a wildcard in patterns.
         * @param string $token The new wildcard token (e.g., "*").
         * @return static The parser.
         */
        public function setWildcardToken (string $token) : static {
            $this->wildcardToken = $token;
            return $this;
        }
    }
?>