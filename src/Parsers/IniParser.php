<?php
    /*/
     * Project Name:    Wingman — Cortex — INI Parser
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Parsers namespace.
    namespace Wingman\Cortex\Parsers;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Cortex\Interfaces\ParserInterface;

    /**
     * A parser for `.ini` files backed by PHP's native `parse_ini_file()` function.
     *
     * By default, sections are preserved as nested arrays, so:
     * ```ini
     * [database]
     * host = localhost
     * port = 3306
     * ```
     * parses as `["database" => ["host" => "localhost", "port" => 3306]]`.
     *
     * Type scanning is enabled (`INI_SCANNER_TYPED`) by default, which means numeric and boolean
     * literals are cast to their PHP equivalents (`3306` → `int`, `true` → `bool`).
     *
     * Supported `$options` keys:
     * - `"sections"` *(bool, default `true`)* — whether to parse `[section]` headers as nested arrays.
     *   When `false`, all keys are flattened into a single-level array and section headers are ignored.
     * - `"mode"` *(int, default `INI_SCANNER_TYPED`)* — the scanner mode forwarded directly to
     *   `parse_ini_file()`. Use `INI_SCANNER_RAW` to disable type casting, or `INI_SCANNER_NORMAL`
     *   for classic behaviour where `true`/`false` are returned as `"1"` and `""`.
     *
     * @package Wingman\Cortex\Parsers
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class IniParser implements ParserInterface {
        /**
         * Parses an INI configuration file and returns its contents as a nested associative array.
         * @param string $path    The absolute path to the `.ini` file to parse.
         * @param array  $options Parser options — see class docblock for supported keys.
         * @return array The parsed configuration data.
         * @throws RuntimeException If `parse_ini_file()` returns `false` (malformed file or unreadable path).
         */
        public function import (string $path, array $options = []) : array {
            $sections = $options["sections"] ?? true;
            $mode     = $options["mode"]     ?? INI_SCANNER_TYPED;
            $result   = parse_ini_file($path, $sections, $mode);

            if ($result === false) {
                throw new RuntimeException("Failed to parse INI file: {$path}.");
            }

            return $result;
        }
    }
?>