<?php
    /*/
     * Project Name:    Wingman — Cortex — XML Parser
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Parsers namespace.
    namespace Wingman\Cortex\Parsers;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Exceptions\InvalidSourceException;
    use Wingman\Cortex\Exceptions\MissingDependencyException;
    use Wingman\Cortex\Interfaces\ParserInterface;

    /**
     * An optional bridge parser for XML configuration files (`.xml`) backed by PHP's
     * built-in `SimpleXML` extension. Although `SimpleXML` ships with PHP and is
     * enabled by default, it can be disabled at compile time, so the parser checks for
     * availability before attempting to load. If the extension is absent a
     * `MissingDependencyException` is thrown with a clear message pointing to the
     * missing PHP extension.
     *
     * The parser flattens XML attributes and child elements into a nested associative
     * array by round-tripping through JSON, which is portable and requires no
     * additional dependencies. XML attributes appear as regular keys; text nodes that
     * coexist with child elements are stored under the empty-string key `""`.
     *
     * Supported `$options` keys:
     * - `"flags"` *(int, default `LIBXML_NOCDATA`)* — libxml option flags forwarded to
     *   `simplexml_load_file()`. Common values: `LIBXML_NOERROR`, `LIBXML_NOWARNING`.
     *
     * @package Wingman\Cortex\Parsers
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class XmlParser implements ParserInterface {
        /**
         * Parses an XML configuration file and returns its contents as an associative
         * array. Throws `MissingDependencyException` when `ext-simplexml` is unavailable
         * and `InvalidSourceException` when the file cannot be parsed.
         * @param string $path    The absolute path to the `.xml` file to parse.
         * @param array  $options Parser options — see class docblock for supported keys.
         * @return array The parsed configuration data.
         * @throws MissingDependencyException If `ext-simplexml` is not available.
         * @throws InvalidSourceException     If the XML file cannot be parsed.
         */
        public function import (string $path, array $options = []) : array {
            if (!extension_loaded("simplexml")) {
                throw new MissingDependencyException("ext-simplexml");
            }

            $flags = $options["flags"] ?? LIBXML_NOCDATA;
            $xml = simplexml_load_file($path, "SimpleXMLElement", $flags);

            if ($xml === false) {
                throw new InvalidSourceException($path);
            }

            return json_decode(json_encode($xml), true) ?? [];
        }
    }
?>