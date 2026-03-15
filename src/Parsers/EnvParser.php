<?php
    /*/
     * Project Name:    Wingman — Cortex — Env Parser
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Parsers namespace.
    namespace Wingman\Cortex\Parsers;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Cortex\Interfaces\ParserInterface;

    /**
     * A parser for `.env` files that hydrates PHP's runtime environment and returns values as a flat
     * associative array. Designed to handle the full range of syntax found in real-world `.env` files.
     *
     * Supported syntax:
     * - `KEY=value`             — unquoted value; inline `# comment` requires a leading space
     * - `KEY="value"`           — double-quoted; supports `\n`, `\t`, `\r`, `\\`, `\"`, octal
     *                             escape sequences, and `${VAR}` / `$VAR` variable interpolation
     *                             from already-parsed keys and from `$_ENV` / `getenv()`
     * - `KEY='value'`           — single-quoted; completely literal, no escape processing
     * - `export KEY=value`      — shell-style export prefix; the `export` keyword is silently ignored
     * - Multi-line values       — both double- and single-quoted values may span multiple lines
     * - `# comment`             — full-line comments are silently skipped
     * - Blank lines             — silently skipped
     * - UTF-8 BOM               — stripped automatically
     *
     * When imported, every variable is written to `putenv()`, `$_ENV`, and `$_SERVER` so that all
     * common PHP environment access mechanisms reflect the values.
     *
     * @package Wingman\Cortex\Parsers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class EnvParser implements ParserInterface {
        /**
         * The UTF-8 byte-order mark that some Windows editors prepend to files.
         * @var string
         */
        private const BOM = "\xEF\xBB\xBF";

        /**
         * The regular expression pattern for a valid `.env` assignment line.
         * Supports an optional leading `export` keyword and any surrounding whitespace.
         * @var string
         */
        private const LINE_PATTERN = '/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)?$/';

        /**
         * Processes standard escape sequences within a double-quoted inner value and then resolves
         * any variable references of the form `${VAR}` or `$VAR` using the keys already parsed from
         * the same file, followed by `$_ENV` and `getenv()` as fallbacks.
         * @param string $inner The raw inner content, with surrounding double quotes already stripped.
         * @param array<string, string> $parsed The variables parsed so far in the current file.
         * @return string The fully expanded value.
         */
        private function expandEscapes (string $inner, array $parsed) : string {
            $value = preg_replace_callback(
                '/\\\\([ntr\\\\"$`])|\\\\0?([0-7]{1,3})/',
                function (array $m) : string {
                    if ($m[1] !== "") {
                        return match ($m[1]) {
                            'n'     => "\n",
                            't'     => "\t",
                            'r'     => "\r",
                            default => $m[1]
                        };
                    }
                    return chr(octdec($m[2]));
                },
                $inner
            ) ?? $inner;

            return preg_replace_callback(
                '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}|\$([A-Za-z_][A-Za-z0-9_]*)/',
                function (array $m) use ($parsed) : string {
                    $name = $m[1] !== "" ? $m[1] : $m[2];
                    return $parsed[$name] ?? $_ENV[$name] ?? (getenv($name) ?: "");
                },
                $value
            ) ?? $value;
        }

        /**
         * Scans a string character by character and returns the position of the first unescaped
         * occurrence of the given quote character, or `false` if none is found.
         * @param string $str The string to scan.
         * @param string $quote The quote character to search for (`"` or `'`).
         * @return int|false The zero-based position of the first unescaped closing quote, or `false`.
         */
        private function findUnescapedQuote (string $str, string $quote) : int|false {
            $len = strlen($str);

            for ($i = 0; $i < $len; $i++) {
                if ($str[$i] === '\\') {
                    $i++;
                    continue;
                }

                if ($str[$i] === $quote) return $i;
            }

            return false;
        }

        /**
         * Writes the given key-value pairs to `putenv()`, `$_ENV`, and `$_SERVER`, ensuring that all
         * three access mechanisms reflect the imported environment variables.
         * @param array<string, string> $values The flat map of variable names to their resolved values.
         */
        protected function hydrate (array $values) : void {
            foreach ($values as $key => $value) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }

        /**
         * Reads the file at `$path` and builds a flat associative array of variable names to their
         * resolved string values. Handles multi-line quoted values, BOM stripping, and all supported
         * quote styles.
         * @param string $path The absolute path to the `.env` file.
         * @return array<string, string> The parsed key-value pairs.
         * @throws RuntimeException If the file cannot be read.
         */
        protected function parse (string $path) : array {
            $content = @file_get_contents($path);

            if ($content === false) {
                throw new RuntimeException("EnvParser: cannot read file '{$path}'.");
            }

            if (str_starts_with($content, self::BOM)) {
                $content = substr($content, 3);
            }

            $lines  = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
            $values = [];
            $count  = count($lines);
            $i      = 0;

            while ($i < $count) {
                $line = ltrim($lines[$i++]);

                if ($line === "" || $line[0] === '#') continue;
                if (!preg_match(self::LINE_PATTERN, $line, $matches)) continue;

                $key = $matches[1];
                $raw = trim($matches[2] ?? "");

                if ($raw !== "" && $raw[0] === '"') {
                    $values[$key] = $this->parseDoubleQuoted($raw, $lines, $i, $count, $values);
                    continue;
                }

                if ($raw !== "" && $raw[0] === "'") {
                    $values[$key] = $this->parseSingleQuoted($raw, $lines, $i, $count);
                    continue;
                }

                $values[$key] = $this->parseUnquoted($raw);
            }

            return $values;
        }

        /**
         * Parses a double-quoted value, consuming additional lines from `$lines` if the closing quote
         * has not yet been found, then applies escape-sequence expansion and variable interpolation.
         * @param string $raw The remainder of the assignment line, starting with the opening `"`.
         * @param array<int, string> $lines All lines of the file.
         * @param int &$i The current line index; advanced in place as additional lines are consumed.
         * @param int $count The total number of lines.
         * @param array<string, string> $parsed The variables parsed so far in the current file.
         * @return string The fully resolved value.
         */
        protected function parseDoubleQuoted (string $raw, array $lines, int &$i, int $count, array $parsed) : string {
            $collected = substr($raw, 1);

            while (($pos = $this->findUnescapedQuote($collected, '"')) === false && $i < $count) {
                $collected .= "\n" . $lines[$i++];
            }

            $inner = $pos !== false ? substr($collected, 0, $pos) : $collected;
            return $this->expandEscapes($inner, $parsed);
        }

        /**
         * Parses a single-quoted value, consuming additional lines from `$lines` if the closing quote
         * has not yet been found. No escape processing is performed; the value is taken literally.
         * @param string $raw The remainder of the assignment line, starting with the opening `'`.
         * @param array<int, string> $lines All lines of the file.
         * @param int &$i The current line index; advanced in place as additional lines are consumed.
         * @param int $count The total number of lines.
         * @return string The literal value between the outermost single quotes.
         */
        protected function parseSingleQuoted (string $raw, array $lines, int &$i, int $count) : string {
            $collected = substr($raw, 1);

            while (($pos = strpos($collected, "'")) === false && $i < $count) {
                $collected .= "\n" . $lines[$i++];
            }

            return $pos !== false ? substr($collected, 0, $pos) : $collected;
        }

        /**
         * Parses an unquoted value by stripping any inline comment preceded by a space, matching the
         * behaviour expected by most `.env` tooling.
         * @param string $raw The raw value portion of the assignment line.
         * @return string The trimmed value with any inline comment removed.
         */
        protected function parseUnquoted (string $raw) : string {
            $commentPos = strpos($raw, ' #');
            return $commentPos !== false ? rtrim(substr($raw, 0, $commentPos)) : $raw;
        }

        /**
         * Parses a `.env` file, optionally hydrates the PHP runtime environment, and returns the values
         * as a flat associative array. This is the entry point called by the Loader.
         * @param string $path The absolute path to the `.env` file.
         * @param array $options Parser options. Supported keys:
         *   - `hydrate` (bool, default: `true`): Whether to write values to `putenv()`, `$_ENV`, and `$_SERVER`.
         * @return array<string, string> The parsed key-value pairs.
         * @throws RuntimeException If the file cannot be read.
         */
        public function import (string $path, array $options = []) : array {
            $values = $this->parse($path);

            if ($options["hydrate"] ?? true) {
                $this->hydrate($values);
            }

            return $values;
        }
    }
?>