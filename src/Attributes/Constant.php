<?php
    /*/
     * Project Name:    Wingman — Cortex — Constant
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Instructs `ObjectHydrator` to call `Configuration::setConst()` on the resolved key
     * immediately after writing the hydrated value. This locks the key permanently within the
     * owning `Configuration` instance so that any subsequent `set()` or `mergeFlat()` call
     * targeting the same key throws a `ConstantViolationException`.
     *
     * The semantics are identical to passing `"const" => true` to `Configuration::import()`,
     * but expressed at the property level for cases where only specific keys should be immutable
     * rather than an entire imported file.
     *
     * Typical uses:
     * - Application-level constants derived at boot (e.g. `app.env`, `app.version`).
     * - Keys that must not be overridden by later merge operations.
     *
     * Example:
     * ```php
     * class AppConfig {
     *     #[Configurable("app.env", schema: "string")]
     *     #[Constant]
     *     protected string $env;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Constant {}
?>