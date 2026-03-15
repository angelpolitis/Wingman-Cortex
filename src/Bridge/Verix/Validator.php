<?php
    /*/
     * Project Name:    Wingman — Cortex — Verix Bridge Validator
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Bridge.Verix namespace.
    namespace Wingman\Cortex\Bridge\Verix;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the class is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\Validator', false)) return;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Interfaces\ValidatorInterface;
    use Wingman\Cortex\Validation\NativeValidator;
    use Wingman\Verix\Facades\Schema;

    # If Verix is installed, use it for full DSL validation; otherwise fall back to the native validator.
    if (class_exists(Schema::class)) {
        /**
         * A validator that delegates to the Verix schema engine, supporting the full Verix DSL including
         * range constraints, string formats, struct shapes, tuples, and all other Verix node types.
         * This class is only defined when the `Wingman/Verix` package is available.
         * @package Wingman\Cortex\Bridge\Verix
         * @author Angel Politis <info@angelpolitis.com>
         * @since 1.0
         */
        class Validator implements ValidatorInterface {
            /**
             * Validates a value against a Verix DSL expression using the full Verix schema engine.
             * @param string $expression The Verix DSL expression.
             * @param mixed $value The value to validate.
             * @return string[] An array of error messages; empty if the value is valid.
             */
            public function check (string $expression, mixed $value) : array {
                $result = Schema::from($expression)->validate($value);

                if ($result->valid) {
                    return [];
                }

                return array_map(
                    fn ($error) => $error->getMessage(),
                    $result->errors
                );
            }
        }
        return;
    }

    /**
     * A no-op bridge used when Verix is not available.
     * Delegates to `NativeValidator`, which supports primitives, nullable, union, and class names.
     * Install `Wingman/Verix` to unlock range constraints, struct shapes, string formats, and the
     * full Verix DSL.
     * @package Wingman\Cortex\Bridge\Verix
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Validator extends NativeValidator {}
?>