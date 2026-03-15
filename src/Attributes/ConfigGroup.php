<?php
    /*/
     * Project Name:    Wingman — Cortex — ConfigGroup
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Applies a shared key prefix to every `#[Configurable]`-annotated property in the decorated
     * class, eliminating per-property repetition of common path segments.
     *
     * `ObjectHydrator` detects this attribute on the concrete class before iterating its properties
     * and automatically prepends `$prefix . "."` to each resolved `#[Configurable]` key. Inherited
     * properties are included — the prefix covers the entire class hierarchy as seen from the
     * concrete type.
     *
     * Example:
     * ```php
     * #[ConfigGroup("database")]
     * class DatabaseConfig {
     *     #[Configurable("host")]   // resolves as "database.host"
     *     protected string $host;
     *
     *     #[Configurable("port")]   // resolves as "database.port"
     *     protected int $port;
     * }
     * ```
     *
     * When `#[ConfigGroup]` is active, the value passed to `#[Configurable]` should be only the
     * final segment(s) of the key (i.e. without the group prefix). The standard `.` separator is
     * used to join the prefix and the property key regardless of the `Configuration` instance's
     * `$segmentDelimiter`.
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS)]
    class ConfigGroup {
        /**
         * The prefix to prepend to all `#[Configurable]` keys declared in the class.
         * @var string
         */
        public readonly string $prefix;

        /**
         * Creates a new attribute.
         * @param string $prefix The prefix to prepend to every `#[Configurable]` key in the class.
         */
        public function __construct (string $prefix) {
            $this->prefix = $prefix;
        }
    }
?>