<?php
    /*/
     * Project Name:    Wingman — Cortex — Configurable
     * Created by:      Angel Politis
     * Creation Date:   Feb 21 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex.Attributes namespace.
    namespace Wingman\Cortex\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a class property as configurable, meaning that it can be set via a configuration file and accessed via the `Configuration` class.
     * @package Wingman\Cortex\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY)]
    class Configurable {
        /**
         * A sentinel value used to distinguish "no default was provided" from an explicit `null` default.
         * @var string
         */
        public const NO_DEFAULT = "\0configurable.no_default\0";

        /**
         * The key associated with the configurable property. This is used to retrieve the value from the configuration.
         * If not provided, the property name will be used as the key.
         * @var string
         */
        protected string $key;

        /**
         * An optional human-readable description of the configurable property.
         * Used for documentation purposes only and has no effect on runtime behaviour.
         * @var string|null
         */
        protected ?string $description;

        /**
         * An optional Verix DSL expression that describes the expected type or shape of the value.
         * When this property is decorated with `#[Configurable(schema: 'int<min=1, max=65535>')]`, the value
         * is validated against the expression during `Configuration::populate()`. If the
         * `wingman/verix` package is not installed, only primitives, nullable shorthand, union types,
         * and class names are evaluated natively.
         * @var string|null
         */
        protected ?string $schema;

        /**
         * The fallback value to assign when the configuration key is absent and `Configuration::hydrate()` is called.
         * Only meaningful when `$hasDefault` is `true`.
         * @var mixed
         */
        protected mixed $default = null;

        /**
         * Whether an explicit default was declared on this attribute instance.
         * @var bool
         */
        protected bool $hasDefault = false;

        /**
         * Creates a new attribute.
         * @param string $key The key associated with the configurable property.
         * @param string|null $description An optional human-readable description for documentation purposes.
         * @param string|null $schema An optional Verix DSL expression for value validation during populate().
         * @param mixed $default An optional fallback value used by `Configuration::hydrate()` when the key is absent.
         *                       Pass `Configurable::NO_DEFAULT` (the default) to indicate no fallback.
         */
        public function __construct (string $key, ?string $description = null, ?string $schema = null, mixed $default = Configurable::NO_DEFAULT) {
            $this->key         = $key;
            $this->description = $description;
            $this->schema      = $schema;

            if ($default !== self::NO_DEFAULT) {
                $this->hasDefault = true;
                $this->default    = $default;
            }
        }

        /**
         * Gets the human-readable description of the configurable property, if provided.
         * @return string|null The description, or null if none was given.
         */
        public function getDescription () : ?string {
            return $this->description;
        }

        /**
         * Gets the fallback value declared on this attribute instance.
         * Only meaningful when `hasDefault()` returns `true`.
         * @return mixed The declared default, or `null` if none was declared.
         */
        public function getDefault () : mixed {
            return $this->default;
        }

        /**
         * Gets the key associated with the configurable property.
         * @return string The key associated with the configurable property.
         */
        public function getKey () : string {
            return $this->key;
        }

        /**
         * Gets the Verix DSL schema expression associated with the configurable property, if one was declared.
         * @return string|null The schema expression, or null if none was given.
         */
        public function getSchema () : ?string {
            return $this->schema;
        }

        /**
         * Returns whether an explicit default was declared on this attribute instance.
         * @return bool `true` if a default value was provided; `false` if the key is required.
         */
        public function hasDefault () : bool {
            return $this->hasDefault;
        }
    }
?>