<?php
    /*/
     * Project Name:    Wingman — Cortex — Loader
     * Created by:      Angel Politis
     * Creation Date:   Feb 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Exceptions\InvalidSourceException;
    use Wingman\Cortex\Parsers\EnvParser;
    use Wingman\Cortex\Parsers\IniParser;
    use Wingman\Cortex\Interfaces\ParserInterface;
    use Wingman\Cortex\Parsers\TomlParser;
    use Wingman\Cortex\Parsers\XmlParser;
    use Wingman\Cortex\Parsers\YamlParser;

    /**
     * A class responsible for loading configuration data from various sources (arrays, files, directories) and formats (JSON, YAML, etc.).
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Loader {
        /**
         * Whether to build a nested configuration path based on the directory structure.
         * If true, "services/mail.json" becomes ["services => ["mail => [...]]].
         * @var bool
         */
        protected bool $mapDirectoryStructure = true;

        /**
         * A map of file extensions to parsers; each entry is either a plain callable (receives only
         * the file path) or a ParserInterface implementation (receives both the path and options).
         * @var array<string, callable|ParserInterface>
         */
        protected array $parsers = [];

        /**
         * The delimiter used for grouping configuration keys when mapping directory structures.
         * @var string
         */
        protected string $pathDelimiter = '.';

        /**
         * Creates a new loader.
         * @param bool $mapDirectoryStructure Whether to build a nested configuration path based on the directory structure.
         * @param string $pathDelimiter The delimiter used for grouping configuration keys when mapping directory structures.
         */
        public function __construct (bool $mapDirectoryStructure = true, string $pathDelimiter = '.') {
            $this->mapDirectoryStructure = $mapDirectoryStructure;
            $this->pathDelimiter = $pathDelimiter;
            $this->addParser("env",  new EnvParser());
            $this->addParser("ini",  new IniParser());
            $this->addParser("php",  fn ($path) => require $path);
            $this->addParser("json", fn ($path) => json_decode(file_get_contents($path), true));
            $this->addParser("toml", new TomlParser());
            $this->addParser("xml",  new XmlParser());
            $this->addParser("yaml", new YamlParser());
            $this->addParser("yml",  new YamlParser());
        }

        /**
         * Recursively loads all configuration files from a directory, optionally mapping the directory structure to nested configuration keys.
         * @param string $directory The directory to load from.
         * @param array $options Optional options for loading (e.g., parser-specific options).
         * @param string $subPath The current sub-path within the directory (used for recursion and grouping).
         * @param bool|null $mapDirectoryStructure An optional override for whether to map the directory structure to nested configuration keys when loading from directories (overrides the loader's default setting).
         * @return array The combined configuration loaded from the directory.
         * @throws Exception If a file cannot be loaded.
         */
        protected function loadDirectory (string $directory, array $options = [], string $subPath = "", ?bool $mapDirectoryStructure = null) :  array {
            $combined = [];
            $fullPath = $subPath ? rtrim($directory, '/') . '/' . $subPath : $directory;
            $mapDirectoryStructure ??= $this->mapDirectoryStructure;
            
            foreach (scandir($fullPath) as $file) {
                if ($file[0] === '.') continue;

                $itemPath = rtrim($fullPath, '/') . '/' . $file;
                $relativeKey = $subPath ? $subPath . '/' . $file : $file;

                if (is_dir($itemPath)) {
                    $subData = $this->loadDirectory($directory, $options, $relativeKey, $mapDirectoryStructure);
                    $combined = array_replace_recursive($combined, $subData);
                    continue;
                }
                
                $info = pathinfo($file);
                $internalName = null;
                if ($mapDirectoryStructure) {
                    $internalName = ($subPath !== "") 
                        ? str_replace('/', $this->pathDelimiter, $subPath) . $this->pathDelimiter . $info["filename"] 
                        : $info["filename"];
                }

                $fileData = $this->loadFile($itemPath, $options, $internalName, $mapDirectoryStructure);
                $combined = array_replace_recursive($combined, $fileData);
            }
            return $combined;
        }

        /**
         * Loads a configuration from a file, based on its extension.
         * The loaded data is then prepared and optionally grouped under a name.
         * @param string $path The file path to load from.
         * @param array $options Optional options for loading (e.g., parser-specific options).
         * @param string|null $parent An optional parent for grouping the configuration.
         * @param bool|null $mapDirectoryStructure An optional override for whether to map the directory structure when loading from directories (overrides the loader's default setting).
         * @return array The loaded configuration as an associative array.
         * @throws Exception If the file cannot be loaded or has an unsupported extension.
         */
        protected function loadFile (string $path, array $options = [], ?string $parent = null, ?bool $mapDirectoryStructure = null) : array {
            $info = pathinfo($path);
            $extension = strtolower($info["extension"] ?? "");
            $finalName = $parent ?? ($mapDirectoryStructure ?? $this->mapDirectoryStructure ? $info["filename"] : null);

            if (!isset($this->parsers[$extension])) {
                return $this->prepare([], $finalName);
            }

            $parser = $this->parsers[$extension];
            $data = [];

            if ($parser instanceof ParserInterface) {
                $data = $parser->import($path, $options);
            }
            elseif (is_callable($parser)) {
                $data = $parser($path);
            }

            return $this->prepare((array) $data, $finalName);
        }

        /**
         * Prepares the loaded configuration data, optionally grouping it under a dot-notated name.
         * @param array $data The configuration data to prepare.
         * @param string|null $parent An optional dot-notated parent name for grouping the configuration.
         * @return array The prepared configuration array.
         */
        protected function prepare (array $data, ?string $parent = null) : array {
            if (!$parent) return $data;

            $keys = explode($this->pathDelimiter, $parent);
            $result = [];
            $current = &$result;

            foreach ($keys as $key) {
                $current[$key] = [];
                $current = &$current[$key];
            }

            $current = $data;
            return $result;
        }

        /**
         * Adds a parser for a specific file extension.
         * A parser may be a plain callable (receives only the file path) or a ParserInterface
         * implementation (receives both the file path and the full options array).
         * @param string $extension The file extension to associate with the parser (e.g., "yaml").
         * @param callable|ParserInterface $parser The parser to register.
         * @return static The loader instance (for chaining).
         */
        public function addParser (string $extension, callable|ParserInterface $parser) : static {
            $this->parsers[strtolower($extension)] = $parser;
            return $this;
        }

        /**
         * Clears all registered parsers.
         * @return static The loader instance (for chaining).
         */
        public function clearParsers () : static {
            $this->parsers = [];
            return $this;
        }

        /**
         * Gets whether the loader is set to map the directory structure to nested configuration keys when loading from directories.
         * @return bool Whether the directory structure is mapped to nested keys.
         */
        public function getMapDirectoryStructure () : bool {
            return $this->mapDirectoryStructure;
        }

        /**
         * Gets the parser associated with a specific file extension.
         * @param string $extension The file extension to get the parser for (e.g., "yaml").
         * @return callable|null The parser callable if found, or `null` if no parser is registered for the extension.
         */
        public function getParser (string $extension) : ?callable {
            return $this->parsers[strtolower($extension)] ?? null;
        }

        /**
         * Gets the currently registered parsers.
         * @return array A map of file extensions to their associated parsing callables.
         */
        public function getParsers () : array {
            return $this->parsers;
        }

        /**
         * Gets the delimiter used for grouping configuration keys when mapping directory structures.
         * @return string The path delimiter.
         */
        public function getPathDelimiter () : string {
            return $this->pathDelimiter;
        }

        /**
         * Loads a configuration from an array, file, or directory.
         * - If an array is provided, it is returned as-is (after preparation).
         * - If a file path is provided, it is loaded based on its extension (PHP or JSON).
         * - If a directory path is provided, it is recursively scanned and all files are loaded and combined.
         * @param array|string $source The source to load from (array, file path, or directory path).
         * @param array $options Optional options for loading (e.g., parser-specific options).
         * @param string|null $parent An optional parent name for grouping the configuration (used when loading from files or directories).
         * @param bool|null $mapDirectoryStructure An optional override for whether to map the directory structure when loading from directories (overrides the loader's default setting).
         * @return array The loaded configuration as an associative array.
         * @throws Exception If the source is invalid or cannot be loaded.
         */
        public function load (array|string $source, array $options = [], ?string $parent = null, ?bool $mapDirectoryStructure = null) : array {
            if (is_array($source)) {
                return $this->prepare($source, $parent);
            }

            if (!is_string($source) || !file_exists($source)) {
                throw new InvalidSourceException($source);
            }

            if (is_dir($source)) {
                $data = $this->loadDirectory($source, $options, "", $mapDirectoryStructure);
                return $this->prepare($data, $parent);
            }
            return $this->loadFile($source, $options, $parent, $mapDirectoryStructure);
        }

        /**
         * Sets whether to map the directory structure to nested configuration keys when loading from directories.
         * @param bool $map Whether to map the directory structure (true) or not (false).
         * @return static The loader instance (for chaining).
         */
        public function mapDirectoryStructure (bool $map) : static {
            $this->mapDirectoryStructure = $map;
            return $this;
        }

        /**
         * Removes a parser for a specific file extension.
         * @param string $extension The file extension whose parser should be removed.
         * @return static The loader instance (for chaining).
         */
        public function removeParser (string $extension) : static {
            unset($this->parsers[strtolower($extension)]);
            return $this;
        }

        /**
         * Sets the delimiter used for grouping configuration keys when mapping directory structures.
         * @param string $delimiter The path delimiter to set.
         * @return static The loader instance (for chaining).
         */
        public function setPathDelimiter (string $delimiter) : static {
            $this->pathDelimiter = $delimiter;
            return $this;
        }
    }
?>