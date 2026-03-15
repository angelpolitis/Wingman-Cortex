<?php
    /*/
     * Project Name:    Wingman — Cortex — TOML Parser
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
     * An optional bridge parser for TOML configuration files (`.toml`) backed by the
     * `yosymfony/toml` package. Because TOML support is not part of PHP's standard
     * library, this parser only works when `yosymfony/toml` is present in the project.
     * Attempting to load a TOML file without it installed throws a
     * `MissingDependencyException` with a clear installation instruction.
     *
     * Install the bridge:
     * ```bash
     * composer require yosymfony/toml
     * ```
     *
     * Once installed, register this parser with the `Loader` for any TOML extension
     * you want to support (the `Loader` already registers `toml` automatically when it
     * detects the class at bootstrap time — if you use a custom loader you may need to
     * do this yourself):
     * ```php
     * $loader->addParser("toml", new TomlParser());
     * ```
     *
     * @package Wingman\Cortex\Parsers
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class TomlParser implements ParserInterface {
        /**
         * The fully-qualified class name of the yosymfony/toml entry point.
         * @var string
         * @disregard P1009
         */
        private const TOML_CLASS = \Yosymfony\Toml\Toml::class;

        /**
         * Parses a TOML configuration file and returns its contents as an associative array.
         * Throws `MissingDependencyException` when `yosymfony/toml` is not installed.
         * @param string $path    The absolute path to the `.toml` file to parse.
         * @param array  $options Parser options (currently unused; reserved for future flags).
         * @return array The parsed configuration data.
         * @throws MissingDependencyException If `yosymfony/toml` is not installed.
         */
        public function import (string $path, array $options = []) : array {
            if (!class_exists(self::TOML_CLASS)) {
                throw new MissingDependencyException("yosymfony/toml");
            }

            $tomlClass = self::TOML_CLASS;
            $result = $tomlClass::parseFile($path);

            return is_array($result) ? $result : [];
        }
    }
?>