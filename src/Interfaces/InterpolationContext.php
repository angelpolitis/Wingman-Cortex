<?php
    /*/
     * Project Name:    Wingman — Cortex — Interpolation Context
     * Created by:      Angel Politis
     * Creation Date:   Feb 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Interfaces namespace.
    namespace Wingman\Cortex\Interfaces;

    /**
     * Defines the context in which an `Interpolator` searches for and validates variable tokens.
     *
     * Implementing this interface lets callers replace `Interpolator`'s built-in `VARIABLE_PATTERN`
     * regex and add a secondary validity predicate that runs after the pattern matches but before the
     * resolver is invoked. Both hooks are optional in the sense that a minimal implementation can
     * return `true` from `isValid()` and delegate `getPattern()` to the constant:
     *
     * ```php
     * public function getPattern () : string { return Interpolator::VARIABLE_PATTERN; }
     * public function isValid (string $match, int $offset, string $expression) : bool { return true; }
     * ```
     *
     * Typical use-cases:
     * - A context that replaces `@{...}` tokens with a different syntax (e.g. `{{...}}`).
     * - A context that rejects matches inside HTML attributes or string literals.
     * - A context that limits interpolation to a specific sub-expression whitelist.
     *
     * @package Wingman\Cortex\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface InterpolationContext {
        /**
         * Returns the raw regular expression (without delimiters) used by the `Interpolator` to
         * locate variable tokens inside a string. The expression must contain exactly one capture
         * group whose content is passed verbatim to `Variable::from()` for parsing.
         *
         * When `setContext()` has been called on an `Interpolator` instance, this value replaces
         * the built-in `Interpolator::VARIABLE_PATTERN` for the lifetime of the context.
         *
         * @return string The raw regex pattern (no delimiters, no flags).
         */
        public function getPattern () : string;

        /**
         * Determines whether a pattern match is valid and should be resolved. Called once per match
         * found by `getPattern()`, before the resolver callable is invoked. Returning `false` causes
         * the match to be skipped (treated as if the resolver returned `null`), leaving the token
         * untouched in the output string.
         *
         * @param string $match      The raw capture-group content of the matched token (the part used
         *                           as the variable expression, e.g. `"db.host"`).
         * @param int    $offset     The byte offset of the full match within the string being processed.
         * @param string $expression The full string currently being interpolated.
         * @return bool `true` to proceed with resolution; `false` to skip this match.
         */
        public function isValid (string $match, int $offset, string $expression) : bool;
    }
?>