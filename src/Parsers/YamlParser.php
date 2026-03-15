<?php
    /*/
     * Project Name:    Wingman — Cortex — YAML Parser
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Parsers namespace.
    namespace Wingman\Cortex\Parsers;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Exceptions\MissingDependencyException;
    use Wingman\Cortex\Interfaces\ParserInterface;

    /**
     * An optional bridge parser for YAML configuration files (`.yaml` / `.yml`) backed by the
     * `symfony/yaml` package. Because YAML support is not part of PHP's standard library,
     * this parser only works when `symfony/yaml` is present in the project. Attempting to load
     * a YAML file without it installed throws a `MissingDependencyException` with a clear
     * installation instruction.
     *
     * Install the bridge:
     * ```bash
     * composer require symfony/yaml
     * ```
     *
     * Once installed, register this parser with the `Loader` for any YAML extension you want
     * to support (the `Loader` already registers both `yaml` and `yml` automatically when it
     * detects the class at bootstrap time — if you use a custom loader you may need to do
     * this yourself):
     * ```php
     * $loader->addParser("yaml", new YamlParser());
     * $loader->addParser("yml",  new YamlParser());
     * ```
     *
     * Supported `$options` keys:
     * - `"flags"` *(int, default `0`)* — bitwise flags forwarded to
     *   `Symfony\Component\Yaml\Yaml::parseFile()`. Common values:
     *   `Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE`, `Yaml::PARSE_OBJECT_FOR_MAP`.
     *
     * @package Wingman\Cortex\Parsers
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class YamlParser implements ParserInterface {
        /**
         * The fully-qualified class name of the symfony/yaml entry point.
         * @var string
         * @disregard P1009
         */
        private const YAML_CLASS = \Symfony\Component\Yaml\Yaml::class;

        /**
         * Parses a YAML configuration file and returns its contents as an associative array.
         * Throws `MissingDependencyException` when `symfony/yaml` is not installed.
         * @param string $path    The absolute path to the `.yaml` or `.yml` file to parse.
         * @param array  $options Parser options — see class docblock for supported keys.
         * @return array The parsed configuration data.
         * @throws MissingDependencyException If `symfony/yaml` is not installed.
         */
        public function import (string $path, array $options = []) : array {
            if (!class_exists(self::YAML_CLASS)) {
                throw new MissingDependencyException("symfony/yaml");
            }

            $flags = $options["flags"] ?? 0;
            $yamlClass = self::YAML_CLASS;
            $result = $yamlClass::parseFile($path, $flags);

            return is_array($result) ? $result : [];
        }
    }
?>