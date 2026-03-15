<?php
    /*/
     * Project Name:    Wingman — Cortex — Validator Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Interfaces namespace.
    namespace Wingman\Cortex\Interfaces;

    /**
     * The contract for expression-based validators used by `ConfigurationSchema`.
     *
     * Implementations receive a type expression string and a runtime value, and return the set of
     * human-readable error messages describing every constraint the value violated. An empty array
     * means the value is valid.
     *
     * The expression language is the Verix DSL. Native implementations support a strict subset
     * (primitives, nullable, union, class names); the Verix bridge supports the full DSL.
     *
     * @package Wingman\Cortex\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ValidatorInterface {
        /**
         * Validates a value against a type expression and returns all constraint violations.
         * @param string $expression The Verix DSL expression describing the expected type or shape.
         * @param mixed $value The value to validate.
         * @return string[] An array of human-readable error messages; empty if the value is valid.
         */
        public function check (string $expression, mixed $value) : array;
    }
?>