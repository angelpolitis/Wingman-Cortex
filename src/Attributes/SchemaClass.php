<?php
    /*/
     * Project Name:    Wingman — Cortex — SchemaClass
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Points `ObjectHydrator::getSchemaFromClass()` to a concrete `ConfigurationSchema` subclass
     * that supplies cross-field or class-level validation rules that cannot be expressed as
     * per-property `#[Configurable(schema: '...')]` annotations.
     *
     * When this attribute is present, `getSchemaFromClass()` instantiates the referenced class as
     * the base schema object (instead of a plain `ConfigurationSchema`) and then adds the
     * property-derived rules on top of it. The referenced class must extend `ConfigurationSchema`
     * and be instantiable with no constructor arguments.
     *
     * This is particularly valuable for:
     * - Validating relationships between keys (e.g. `port` must be set if `host` is set).
     * - Registering a curated set of rules outside of class annotations for readability.
     * - Reusing a shared schema definition across multiple hydrated classes.
     *
     * Example:
     * ```php
     * class DatabaseSchema extends ConfigurationSchema {
     *     public function __construct () {
     *         $this->setOptional("db.ssl", "bool");
     *         // more complex rules, cross-field checks, etc.
     *     }
     * }
     *
     * #[SchemaClass(DatabaseSchema::class)]
     * #[ConfigGroup("db")]
     * class DatabaseConfig {
     *     #[Configurable("host", schema: "string")]
     *     protected string $host;
     * }
     * ```
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS)]
    class SchemaClass {
        /**
         * The fully-qualified class name of the `ConfigurationSchema` subclass to use as the base.
         * @var string
         */
        public readonly string $class;

        /**
         * Creates a new attribute.
         * @param string $class Fully-qualified class name of a `ConfigurationSchema` subclass.
         */
        public function __construct (string $class) {
            $this->class = $class;
        }
    }
?>