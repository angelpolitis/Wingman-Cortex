<?php
    /*/
     * Project Name:    Wingman — Cortex — Sensitive
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a `#[Configurable]`-annotated property as sensitive, meaning the value it holds
     * (e.g. a password, API key, or secret token) must never appear in plaintext in serialised
     * exports or debug output.
     *
     * This attribute has no effect on runtime hydration. Its impact is purely on the export layer:
     * `Configuration::toSafeArray(string|object $class)` identifies all sensitive keys via
     * `ObjectHydrator::getSensitiveKeys()` and replaces their values with a configurable
     * redaction placeholder (default `"***"`) before returning the export array.
     *
     * Example:
     * ```php
     * class AppConfig {
     *     #[Configurable("db.password", schema: "string")]
     *     #[Sensitive]
     *     protected string $password;
     * }
     *
     * // Produces ["default" => ["db" => ["password" => "***", ...]]]
     * $config->toSafeArray(AppConfig::class);
     * ```
     *
     * `#[Sensitive]` is most useful when:
     * - Serialising configurations for logging, caching, or diagnostics.
     * - Exporting configurations for display in dashboards.
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Sensitive {}
?>