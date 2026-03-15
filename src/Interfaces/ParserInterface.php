<?php
    /*/
     * Project Name:    Wingman — Cortex — Parser Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Interfaces namespace.
    namespace Wingman\Cortex\Interfaces;

    /**
     * The contract that all first-class parser objects must satisfy to be accepted by the Loader.
     *
     * Implementing this interface allows a parser to receive both the file path and the full set of
     * caller-supplied options, which raw callables cannot because the Loader only forwards the path
     * to them. Prefer implementing this interface over a plain callable whenever you need access to
     * parser options or need to maintain reusable, stateful parsing logic.
     *
     * @package Wingman\Cortex\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ParserInterface {
        /**
         * Parses a configuration file and returns its contents as an associative array.
         * @param string $path The absolute path to the file to parse.
         * @param array $options Caller-supplied options specific to this parser implementation.
         * @return array The parsed configuration data.
         */
        public function import (string $path, array $options = []) : array;
    }
?>