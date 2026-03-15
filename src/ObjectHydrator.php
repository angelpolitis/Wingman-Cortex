<?php
    /*/
     * Project Name:    Wingman — Cortex — Object Hydrator
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use ReflectionClass;
    use ReflectionNamedType;
    use ReflectionObject;
    use RuntimeException;
    use WeakMap;
    use Wingman\Cortex\Attributes\Configurable;
    use Wingman\Cortex\Attributes\ConfigGroup;
    use Wingman\Cortex\Attributes\ConfigSource;
    use Wingman\Cortex\Attributes\Constant as ConstantAttr;
    use Wingman\Cortex\Attributes\Deprecated as DeprecatedAttr;
    use Wingman\Cortex\Attributes\Environment as EnvironmentAttr;
    use Wingman\Cortex\Attributes\NoInterpolate;
    use Wingman\Cortex\Attributes\SchemaClass as SchemaClassAttr;
    use Wingman\Cortex\Attributes\Sensitive;
    use Wingman\Cortex\Attributes\Transform;
    use Wingman\Cortex\Bridge\Verix\Validator;
    use Wingman\Cortex\ConfigurationSchema;
    use Wingman\Cortex\Exceptions\SchemaViolationException;
    use Wingman\Cortex\Exceptions\UndefinedVariableException;

    /**
     * Hydrates PHP objects from `Configuration` instances and manages named snapshot captures of
     * `#[Configurable]`-annotated property values.
     *
     * Extracting this responsibility from `Configuration` keeps the data store focused on reading
     * and writing key-value data, while `ObjectHydrator` owns all reflection work and the ephemeral
     * snapshot state required for capture-restore test helpers.
     *
     * **Snapshot storage** uses a `WeakMap` keyed by the owning `Configuration` instance, so
     * snapshot data is automatically cleaned up when the configuration is garbage-collected — no
     * manual teardown required.
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ObjectHydrator {
        /**
         * Per-configuration snapshot storage. Keyed by `Configuration` instance so that entries
         * are automatically removed when the configuration is garbage-collected.
         * Each value is a map of `"{spl_object_id}:{name}" => [propertyName => value, ...]`.
         * @var WeakMap<Configuration, array<string, array<string, mixed>>>|null
         */
        private static ?WeakMap $snapshots = null;

        /**
         * Hydrates an object by scanning each of its properties for a `#[Configurable]` attribute
         * and resolving the declared key from the given configuration. In addition to core hydration,
         * the following property- and class-level attributes are handled in a single reflection pass:
         *
         * - `#[ConfigGroup(prefix)]`  — prepends `prefix.` to every resolved key in the class.
         * - `#[Environment(name)]`    — resolves the key from a different named `Configuration`;
         *                              class-level applies to all properties, property-level overrides it.
         * - `#[Deprecated(since, replacement)]` — emits `E_USER_DEPRECATED` before the value is fetched.
         * - `#[NoInterpolate]`        — calls `getRaw()` instead of `get()`, bypassing the `Interpolator`.
         * - `#[Transform(callable)]`  — applies one or more chained callable transforms after coercion.
         * - `#[Constant]`             — locks the resolved key via `setConst()` after the value is written.
         *
         * When `$target` is a class-string, only `static` properties are considered and values are
         * written via `ReflectionProperty::setValue(null, $value)`, which is the PHP API for
         * setting static properties through reflection.
         * @param Configuration $config The configuration to read values from.
         * @param string|object $target The target object to populate, or a class-string for static-only hydration.
         * @param bool $strict Whether to throw for missing or null-valued keys that carry no attribute default.
         * @return Configuration The configuration.
         * @throws UndefinedVariableException If strict mode is enabled and a required key is absent or null.
         * @throws SchemaViolationException If a value fails the attribute's declared schema.
         */
        private static function hydrateFromAttributes (Configuration $config, string|object $target, bool $strict) : Configuration {
            $reflection = is_string($target) ? new ReflectionClass($target) : new ReflectionObject($target);
            $instance   = is_string($target) ? null : $target;
            $validator    = null;
            $groupPrefix  = null;
            $classEnvName = null;

            foreach ($reflection->getAttributes() as $classAttr) {
                if (!class_exists($classAttr->getName(), true)) continue;

                $name = $classAttr->getName();

                if (is_a($name, ConfigGroup::class, true)) {
                    $groupPrefix = $classAttr->newInstance()->prefix;
                } elseif (is_a($name, EnvironmentAttr::class, true)) {
                    $classEnvName = $classAttr->newInstance()->name;
                }
            }

            foreach ($reflection->getProperties() as $property) {
                if (is_string($target) && !$property->isStatic()) continue;

                $configurable  = null;
                $deprecated    = null;
                $envName       = $classEnvName;
                $isConst       = false;
                $noInterpolate = false;
                $transforms    = [];

                foreach ($property->getAttributes() as $attr) {
                    if (!class_exists($attr->getName(), true)) continue;

                    $name = $attr->getName();

                    if (is_a($name, Configurable::class, true)) {
                        $configurable = $attr->newInstance();
                    } elseif (is_a($name, DeprecatedAttr::class, true)) {
                        $deprecated = $attr->newInstance();
                    } elseif (is_a($name, EnvironmentAttr::class, true)) {
                        $envName = $attr->newInstance()->name;
                    } elseif (is_a($name, ConstantAttr::class, true)) {
                        $isConst = true;
                    } elseif (is_a($name, NoInterpolate::class, true)) {
                        $noInterpolate = true;
                    } elseif (is_a($name, Transform::class, true)) {
                        $transforms[] = $attr->newInstance();
                    }
                }

                if ($configurable === null) continue;

                $configKey = $groupPrefix !== null
                    ? $groupPrefix . "." . $configurable->getKey()
                    : $configurable->getKey();

                $schema = $configurable->getSchema();

                if ($deprecated !== null) {
                    trigger_error(
                        "Configuration key \"{$configKey}\" is deprecated since {$deprecated->since}. Use \"{$deprecated->replacement}\" instead.",
                        E_USER_DEPRECATED
                    );
                }

                $resolveFrom = $envName !== null
                    ? (ConfigurationRegistry::get($envName) ?? $config)
                    : $config;

                $value = $noInterpolate
                    ? ($resolveFrom->getRaw($configKey) ?? null)
                    : $resolveFrom->get($configKey, null);

                if ($value === null) {
                    if ($configurable->hasDefault()) {
                        $property->setValue($instance, $configurable->getDefault());
                        continue;
                    }

                    if ($strict) {
                        throw new UndefinedVariableException($configKey, null, $config->getName());
                    }

                    if (!$property->hasDefaultValue() && $property->getType()?->allowsNull()) {
                        $property->setValue($instance, null);
                    }

                    continue;
                }

                if ($schema !== null) {
                    $validator ??= new Validator();
                    $errors     = $validator->check($schema, $value);

                    if (!empty($errors)) {
                        throw new SchemaViolationException($configKey, $schema, $errors);
                    }
                }

                $type = $property->getType();

                if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                    $value = match ($type->getName()) {
                        "bool"   => (bool)   $value,
                        "int"    => (int)    $value,
                        "float"  => (float)  $value,
                        "string" => (string) $value,
                        default  => $value,
                    };
                }

                foreach ($transforms as $transformAttr) {
                    $transformer = $transformAttr->transformer;

                    if (is_callable($transformer)) {
                        $value = $transformer($value);
                    }
                }

                $property->setValue($instance, $value);

                if ($isConst) {
                    $resolveFrom->setConst($configKey, $value);
                }
            }

            return $config;
        }

        /**
         * Hydrates an object using an explicit `propertyName => configKey` map.
         * Integer-keyed map entries are treated as `propertyName === configKey`. Properties that
         * do not exist on the target class are silently skipped. In strict mode, a `null`-valued
         * config key causes an `UndefinedVariableException` to be thrown.
         * @param Configuration $config The configuration to read values from.
         * @param object $target The target object to populate.
         * @param array $map The explicit property-to-config-key mapping.
         * @param bool $strict Whether to throw for missing or null-valued keys.
         * @return Configuration The configuration.
         * @throws UndefinedVariableException If strict mode is enabled and a key resolves to `null`.
         */
        private static function hydrateFromMap (Configuration $config, string|object $target, array $map, bool $strict) : Configuration {
            $reflection    = is_string($target) ? new ReflectionClass($target) : new ReflectionObject($target);
            $instance      = is_string($target) ? null : $target;
            $normalisedMap = [];

            foreach ($map as $key => $value) {
                $normalisedMap[is_int($key) ? $value : $key] = $value;
            }

            foreach ($normalisedMap as $property => $configKey) {
                if (!$reflection->hasProperty($property)) continue;

                $value = $config->get($configKey, null);
                $prop  = $reflection->getProperty($property);

                if ($value === null) {
                    if ($strict) {
                        throw new UndefinedVariableException($configKey, null, $config->getName());
                    }

                    if (!$prop->hasDefaultValue() && $prop->getType()?->allowsNull()) {
                        $prop->setValue($instance, null);
                    }

                    continue;
                }

                $prop->setValue($instance, $value);
            }

            return $config;
        }

        /**
         * Builds a qualified-key version of `$data` by resolving any simple (dot-free) keys against
         * the `#[Configurable]` attributes declared on `$target`. The last dot-segment of each
         * attribute key is used as the simple key (e.g. `"maxDepth"` for `"locator.discovery.maxDepth"`).
         * Keys that already contain a dot are passed through unchanged, as are simple keys that do
         * not match any attribute — making the transformation strictly additive and non-destructive.
         * @param object $target The object whose `#[Configurable]` attributes define the key map.
         * @param array $data The raw input array, potentially containing simple keys.
         * @return array The remapped array with all matchable keys replaced by their qualified form.
         */
        private static function qualifyKeys (object $target, array $data) : array {
            $keyMap     = [];
            $reflection = new ReflectionObject($target);

            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes() as $attribute) {
                    if (!class_exists($attribute->getName(), true) || !is_a($attribute->getName(), Configurable::class, true)) {
                        continue;
                    }

                    $qualifiedKey        = $attribute->newInstance()->getKey();
                    $keyMap[substr($qualifiedKey, strrpos($qualifiedKey, '.') + 1)] = $qualifiedKey;
                }
            }

            $result = [];

            foreach ($data as $key => $value) {
                $result[str_contains($key, '.') ? $key : ($keyMap[$key] ?? $key)] = $value;
            }

            return $result;
        }

        /**
         * Returns the snapshot store for the given `$config` instance, creating it on first access.
         * Uses a `WeakMap` so that the store is automatically freed when `$config` is garbage-collected.
         * @param Configuration $config The configuration instance whose store should be retrieved.
         * @return array<string, array<string, mixed>> The snapshot store, by reference.
         */
        private static function &store (Configuration $config) : array {
            static::$snapshots ??= new WeakMap();

            if (!isset(static::$snapshots[$config])) {
                static::$snapshots[$config] = [];
            }

            return static::$snapshots[$config];
        }

        /**
         * Captures the current values of all `#[Configurable]`-annotated properties of `$object`
         * into a named slot associated with the given `$config` instance. Calling this again with
         * the same `$name` and `$config` overwrites the previous capture. Properties that are not
         * yet initialised are silently skipped.
         * @param object $object The object whose `#[Configurable]` properties should be captured.
         * @param string $name An arbitrary label for the capture (e.g. `"defaults"`, `"test"`).
         * @param Configuration $config The configuration instance that owns this capture.
         * @return void
         */
        public static function capture (object $object, string $name, Configuration $config) : void {
            $reflection = new ReflectionObject($object);
            $slot       = spl_object_id($object) . ":" . $name;
            $captured   = [];

            foreach ($reflection->getProperties() as $property) {
                $hasConfigurable = !empty(array_filter(
                    $property->getAttributes(),
                    fn ($a) => class_exists($a->getName(), true) && is_a($a->getName(), Configurable::class, true)
                ));

                if (!$hasConfigurable) continue;

                if ($property->isInitialized($object)) {
                    $captured[$property->getName()] = $property->getValue($object);
                }
            }

            static::store($config)[$slot] = $captured;
        }

        /**
         * Reflects all `#[Configurable]`-annotated properties from `$class` and its full parent
         * hierarchy and returns a `ConfigurationSchema` populated from the discovered attributes.
         *
         * Rules are generated as follows:
         * - A property whose attribute declares a `$schema` expression generates a rule.
         * - Rules for properties without a `$default` are registered as **required** (`set()`).
         * - Rules for properties that declare a `$default` are registered as **optional** (`setOptional()`).
         * - Properties with no `$schema` on their attribute are silently skipped.
         * - When the same configuration key appears on multiple levels of the hierarchy (e.g.
         *   overridden via a child attribute), only the first occurrence encountered is recorded;
         *   subsequent duplicates are ignored.
         *
         * The resulting schema can be used directly:
         * ```php
         * $schema = ObjectHydrator::getSchemaFromClass(MyService::class);
         * $schema->assert($config); // throws ConfigurationSchemaException on violation
         * ```
         *
         * @param string|object $class A fully-qualified class name or any object instance.
         * @return ConfigurationSchema A schema whose rules correspond to the class's configurable properties.
         */
        public static function getSchemaFromClass (string|object $class) : ConfigurationSchema {
            $reflection  = is_object($class) ? new ReflectionObject($class) : new ReflectionClass($class);
            $schema      = new ConfigurationSchema();
            $groupPrefix = null;
            $seen        = [];

            foreach ($reflection->getAttributes() as $classAttr) {
                if (!class_exists($classAttr->getName(), true)) continue;

                $name = $classAttr->getName();

                if (is_a($name, ConfigGroup::class, true)) {
                    $groupPrefix = $classAttr->newInstance()->prefix;
                }
                elseif (is_a($name, SchemaClassAttr::class, true)) {
                    $schemaClass = $classAttr->newInstance()->class;

                    if (is_a($schemaClass, ConfigurationSchema::class, true)) {
                        $schema = new $schemaClass();
                    }
                }
            }

            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes() as $attribute) {
                    if (!class_exists($attribute->getName(), true) || !is_a($attribute->getName(), Configurable::class, true)) {
                        continue;
                    }

                    $instance = $attribute->newInstance();
                    $key      = $groupPrefix !== null
                        ? $groupPrefix . "." . $instance->getKey()
                        : $instance->getKey();
                    $expr     = $instance->getSchema();

                    if ($expr === null || isset($seen[$key])) {
                        $seen[$key] = true;
                        continue;
                    }

                    $seen[$key] = true;

                    if ($instance->hasDefault()) {
                        $schema->setOptional($key, $expr);
                    }
                    else $schema->set($key, $expr);
                }
            }

            return $schema;
        }

        /**
         * Reflects all `#[Sensitive]`-annotated `#[Configurable]` properties from `$class` and its
         * full parent hierarchy and returns the resolved configuration key strings for each one.
         * Respects any `#[ConfigGroup]` prefix declared on the class.
         *
         * Consumed by `CanExport::toSafeArray()` to identify which keys must be redacted from
         * exported configuration data before the result is surfaced to callers.
         * @param string|object $class A fully-qualified class name or any object instance.
         * @return string[] Configuration key strings whose corresponding properties are marked sensitive.
         */
        public static function getSensitiveKeys (string|object $class) : array {
            $reflection  = is_object($class) ? new ReflectionObject($class) : new ReflectionClass($class);
            $groupPrefix = null;
            $keys        = [];

            foreach ($reflection->getAttributes() as $classAttr) {
                if (!class_exists($classAttr->getName(), true)) continue;

                if (is_a($classAttr->getName(), ConfigGroup::class, true)) {
                    $groupPrefix = $classAttr->newInstance()->prefix;
                    break;
                }
            }

            foreach ($reflection->getProperties() as $property) {
                $configKey    = null;
                $hasSensitive = false;

                foreach ($property->getAttributes() as $attr) {
                    if (!class_exists($attr->getName(), true)) continue;

                    $name = $attr->getName();

                    if (is_a($name, Configurable::class, true)) {
                        $rawKey    = $attr->newInstance()->getKey();
                        $configKey = $groupPrefix !== null ? $groupPrefix . "." . $rawKey : $rawKey;
                    } elseif (is_a($name, Sensitive::class, true)) {
                        $hasSensitive = true;
                    }
                }

                if ($configKey !== null && $hasSensitive) {
                    $keys[] = $configKey;
                }
            }

            return $keys;
        }

        /**
         * Hydrates an object's `#[Configurable]` properties from a configuration source.
         *
         * The `$source` parameter accepts either an existing `Configuration` instance or a flat
         * associative array of dot-notation key-value pairs. When an array is supplied it is wrapped
         * in a new anonymous `Configuration` via `mergeFlat()` before hydration proceeds.
         *
         * Two hydration modes are available, selected by whether `$map` is supplied:
         * - Mode A — Explicit map: `$map` is an associative `propertyName => configKey` array.
         *   Integer-keyed entries imply `propertyName === configKey`.
         * - Mode B — Attribute mode: every property decorated with `#[Configurable]` is resolved
         *   automatically using the key declared in the attribute. Values are coerced to the
         *   property's declared primitive type (`bool`, `int`, `float`, `string`) before assignment.
         *   If the key is absent and the attribute declares a `$default`, that default is used;
         *   otherwise the property is left untouched (or `null` is assigned when the type allows it).
         *
         * In strict mode, a missing or null-valued config key with no attribute default throws an
         * `UndefinedVariableException`. In non-strict mode, such keys are silently skipped.
         *
         * @param object $target The object whose properties should be hydrated.
         * @param array|Configuration $source A flat key-value array or an existing `Configuration` instance.
         *                                     Passing an empty array falls back to the default registered
         *                                     `Configuration` (`ConfigurationRegistry::get()`), or a new
         *                                     empty instance when none is registered.
         * @param array $map An optional explicit `propertyName => configKey` override map.
         * @param bool $strict When `true`, throws for missing or null-valued keys that carry no attribute default.
         * @return Configuration The `Configuration` instance that was used for hydration.
         * @throws UndefinedVariableException If strict mode is enabled and a required key is absent or null.
         */
        public static function hydrate (string|object $target, array|Configuration $source = [], array $map = [], bool $strict = false) : Configuration {
            if (is_array($source)) {
                if (!empty($source) && empty($map) && !is_string($target)) {
                    $source = static::qualifyKeys($target, $source);
                }

                $config = !empty($source)
                    ? (new Configuration())->mergeFlat($source)
                    : (ConfigurationRegistry::get() ?? new Configuration());
            }
            else {
                $config = $source;
            }

            if (empty($map)) {
                $reflection = is_string($target) ? new ReflectionClass($target) : new ReflectionObject($target);

                foreach ($reflection->getAttributes() as $classAttr) {
                    if (!class_exists($classAttr->getName(), true)) continue;

                    if (is_a($classAttr->getName(), ConfigSource::class, true)) {
                        $config->import($classAttr->newInstance()->paths);
                    }
                }
            }

            return !empty($map)
                ? static::hydrateFromMap($config, $target, $map, $strict)
                : static::hydrateFromAttributes($config, $target, $strict);
        }

        /**
         * Restores the values of all `#[Configurable]`-annotated properties of `$object` from a
         * slot previously written by `capture()`. Writes are performed directly via reflection
         * regardless of property visibility.
         * @param object $object The object whose `#[Configurable]` properties should be restored.
         * @param string $name The label of the capture to restore.
         * @param Configuration $config The configuration instance that owns the capture.
         * @return void
         * @throws RuntimeException If no capture with the given name exists for the object.
         */
        public static function restore (object $object, string $name, Configuration $config) : void {
            $slot      = spl_object_id($object) . ":" . $name;
            $snapshots = static::store($config);

            if (!isset($snapshots[$slot])) {
                throw new RuntimeException("No capture named '$name' found for the given object.");
            }

            $reflection = new ReflectionObject($object);

            foreach ($snapshots[$slot] as $propertyName => $value) {
                if (!$reflection->hasProperty($propertyName)) continue;
                $reflection->getProperty($propertyName)->setValue($object, $value);
            }
        }
    }
?>