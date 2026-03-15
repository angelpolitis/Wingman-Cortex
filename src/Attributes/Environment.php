<?php
    /*/
     * Project Name:    Wingman — Cortex — Environment
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Pins the configuration resolution for a class or a single property to a specific named
     * `Configuration` instance registered in `ConfigurationRegistry`.
     *
     * When applied to a **class**, all `#[Configurable]` properties are resolved from the named
     * configuration instance (looked up via `ConfigurationRegistry::get($name)`). When applied to
     * a **property**, it overrides any class-level `#[Environment]` declaration for that property
     * only, allowing fine-grained per-property environment targeting.
     *
     * If the named configuration does not exist in the registry, resolution silently falls back to
     * the `Configuration` instance passed to `ObjectHydrator::hydrate()`.
     *
     * This attribute is a structured alternative to embedding the environment name directly in a
     * `#[Configurable]` key string (e.g. `"production::db.host"`).
     *
     * Example:
     * ```php
     * #[Environment("production")]
     * class ProductionOnlyConfig {
     *     #[Configurable("db.host", schema: "string")]
     *     protected string $host;
     *
     *     #[Configurable("db.ro_host", schema: "string")]
     *     #[Environment("read-replica")]       // overrides class-level for this property
     *     protected string $readHost;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
    class Environment {
        /**
         * The name of the `Configuration` instance to resolve values from.
         * @var string
         */
        public readonly string $name;

        /**
         * Creates a new attribute.
         * @param string $name The name of the registered `Configuration` to use for resolution.
         */
        public function __construct (string $name) {
            $this->name = $name;
        }
    }
?>