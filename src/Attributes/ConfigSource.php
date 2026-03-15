<?php
    /*/
     * Project Name:    Wingman — Cortex — ConfigSource
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Declares one or more configuration source files that should be automatically imported into
     * the `Configuration` instance before `ObjectHydrator::hydrate()` resolves any properties.
     *
     * This makes a class self-describing about its data requirements: the files needed to satisfy
     * its `#[Configurable]` properties are co-located with the property declarations.
     *
     * Multiple `#[ConfigSource]` attributes may be stacked on a single class; they are imported in
     * declaration order. Each attribute may list multiple paths as variadic arguments.
     *
     * Example:
     * ```php
     * #[ConfigSource("config/database.yaml")]
     * #[ConfigSource("config/database.local.yaml")]
     * #[ConfigGroup("database")]
     * class DatabaseConfig {
     *     #[Configurable("host", schema: "string")]
     *     protected string $host;
     * }
     *
     * // Automatically imports both files before hydration:
     * ObjectHydrator::hydrate($dbConfig);
     * ```
     *
     * Source paths are forwarded to `Configuration::import()` using the default loader options.
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
    class ConfigSource {
        /**
         * The file paths to import before hydration.
         * @var string[]
         */
        public readonly array $paths;

        /**
         * Creates a new attribute.
         * @param string ...$paths One or more file paths to import before hydrating the class.
         */
        public function __construct (string ...$paths) {
            $this->paths = $paths;
        }
    }
?>