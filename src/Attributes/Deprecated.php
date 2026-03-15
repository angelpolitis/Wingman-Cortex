<?php
    /*/
     * Project Name:    Wingman — Cortex — Deprecated
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a `#[Configurable]`-annotated property as deprecated. When `ObjectHydrator` resolves
     * this property during hydration it emits an `E_USER_DEPRECATED` notice, directing callers to
     * migrate to the replacement key. The property is still resolved and assigned normally — the
     * deprecation is non-fatal.
     *
     * Primarily useful for managing configuration key renames or schema restructuring across
     * package versions: the old key continues to work but loudly signals that it will be removed
     * in a future release.
     *
     * Example:
     * ```php
     * class DbConfig {
     *     #[Configurable("db.server")]
     *     #[Deprecated(since: "2.1", replacement: "db.host")]
     *     protected string $server;
     *
     *     #[Configurable("db.host", schema: "string")]
     *     protected string $host;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Deprecated {
        /**
         * The version in which the configuration key was deprecated.
         * @var string
         */
        public readonly string $since;

        /**
         * The replacement configuration key that should be used instead.
         * @var string
         */
        public readonly string $replacement;

        /**
         * Creates a new attribute.
         * @param string $since The version since which the configuration key is deprecated.
         * @param string $replacement The replacement key that should be used instead.
         */
        public function __construct (string $since, string $replacement) {
            $this->since       = $since;
            $this->replacement = $replacement;
        }
    }
?>