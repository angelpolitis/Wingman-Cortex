<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Schema
     * Created by:      Angel Politis
     * Creation Date:   Mar 13 2026
     * Last Modified:   Mar 13 2026
    /*/

    # Use the Cortex namespace.
    namespace Wingman\Cortex;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Bridge\Verix\Validator;
    use Wingman\Cortex\Exceptions\ConfigurationSchemaException;
    use Wingman\Cortex\Exceptions\SchemaViolationException;
    use Wingman\Cortex\Interfaces\ValidatorInterface;

    /**
     * Declares a set of type and constraint rules for a `Configuration` instance and validates the
     * configuration against them.
     *
     * Rules are expressed as Verix DSL strings (e.g. `"string"`, `"int<min:1, max:65535>"`, `"email"`,
     * `"bool"`, `"ClassName"`, `"string|null"`). When the `Wingman/Verix` package is available,
     * the full DSL is supported. When it is not, only primitive types, nullable shorthand, union
     * types, and class/interface names are evaluated natively.
     *
     * Usage:
     * ```php
     * $schema = (new ConfigurationSchema())
     *     ->set("db.host", "string")
     *     ->set("db.port", "int<min:1, max:65535>")  // requires Verix for range
     *     ->setOptional("db.password", "?string");
     *
     * $schema->assert($config); // throws ConfigurationSchemaException if invalid
     * ```
     * @package Wingman\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationSchema {
        /**
         * The stack of active section prefixes. Each call to `section()` pushes a name onto the
         * stack; each call to `endSection()` pops the most recent one. Keys registered via `set()`
         * and `setOptional()` are prefixed with the dot-joined stack, so nested calls compound:
         * `section("db")->section("primary")` produces the prefix `"db.primary"`.
         * @var string[]
         */
        protected array $sectionStack = [];

        /**
         * The rules registered on this schema, indexed by configuration key.
         * Each entry carries the expression string and whether the key is required.
         * @var array<string, array{expression: string, required: bool}>
         */
        protected array $rules = [];

        /**
         * The validator to use for expression evaluation.
         * Lazily resolved to the Verix bridge (or NativeValidator fallback) on first use.
         * @var ValidatorInterface|null
         */
        protected ?ValidatorInterface $validator = null;

        /**
         * Validates a configuration instance against all registered rules and throws a
         * `ConfigurationSchemaException` containing every violation found if any rules are broken.
         * Unlike `validate()`, this method performs an all-or-nothing assertion — it always runs all
         * checks before throwing, so every violation is reported at once.
         * @param Configuration $config The configuration instance to validate.
         * @return static The schema, for fluent chaining.
         * @throws ConfigurationSchemaException If one or more rules are violated.
         */
        public function assert (Configuration $config) : static {
            $violations = $this->validate($config);

            if (!empty($violations)) {
                throw new ConfigurationSchemaException($violations);
            }

            return $this;
        }

        /**
         * Validates all rules belonging to a named section and throws a
         * `ConfigurationSchemaException` if any are violated. The section prefix is the
         * string passed to `section()` when the rules were registered — e.g. calling
         * `assertSection($config, "db")` validates every key that starts with `"db."`.
         * If no rules are registered under the given section the call is a no-op.
         * @param Configuration $config The configuration instance to validate.
         * @param string        $name   The section name (prefix) to assert.
         * @return static The schema, for fluent chaining.
         * @throws ConfigurationSchemaException If one or more section rules are violated.
         */
        public function assertSection (Configuration $config, string $name) : static {
            $violations = $this->validateSection($config, $name);

            if (!empty($violations)) {
                throw new ConfigurationSchemaException($violations);
            }

            return $this;
        }

        /**
         * Deactivates the most recently opened section prefix. Calls can be nested: each
         * `endSection()` corresponds to the most recent `section()`, restoring the prefix to
         * whatever was active before that call. If called when no section is open, it is a no-op.
         * @return static The schema, for fluent chaining.
         */
        public function endSection () : static {
            array_pop($this->sectionStack);
            return $this;
        }

        /**
         * Gets the validator in use, lazily instantiating the Verix bridge (or native fallback) if
         * no explicit validator has been set.
         * @return ValidatorInterface The validator.
         */
        public function getValidator () : ValidatorInterface {
            return $this->validator ??= new Validator();
        }

        /**
         * Pushes a named section prefix onto the section stack so that all subsequent calls to
         * `set()` and `setOptional()` automatically prepend the accumulated prefix to the key.
         * Calls can be nested: `section("db")->section("primary")` produces the prefix
         * `"db.primary"`. Call `endSection()` to pop the most recent prefix and return to the
         * enclosing scope.
         *
         * Usage:
         * ```php
         * $schema
         *     ->section("db")
         *         ->section("primary")
         *             ->set("host", "string")  // stored as "db.primary.host"
         *             ->set("port", "int")     // stored as "db.primary.port"
         *         ->endSection()
         *         ->set("name", "string")      // stored as "db.name"
         *     ->endSection()
         *     ->set("app.name", "string");     // stored as-is
         * ```
         * @param string $name The section name to push onto the prefix stack.
         * @return static The schema, for fluent chaining.
         */
        public function section (string $name) : static {
            $this->sectionStack[] = $name;
            return $this;
        }

        /**
         * Registers a required rule for a configuration key.
         * @param string $key The dot-notated configuration key.
         * @param string $expression The Verix DSL expression describing the expected type or shape.
         * @param bool $required Whether the key is required.
         * @return static The schema, for fluent chaining.
         */
        public function set (string $key, string $expression, bool $required = true) : static {
            $resolvedKey = !empty($this->sectionStack)
                ? implode(".", $this->sectionStack) . "." . $key
                : $key;

            $this->rules[$resolvedKey] = ["expression" => $expression, "required" => $required];
            return $this;
        }

        /**
         * Registers an optional rule for a configuration key.
         * If the key is absent from the configuration, no violation is raised; if it is present, the
         * value is still validated against the expression.
         * @param string $key The dot-notated configuration key.
         * @param string $expression The Verix DSL expression describing the expected type or shape.
         * @return static The schema, for fluent chaining.
         */
        public function setOptional (string $key, string $expression) : static {
            return $this->set($key, $expression, false);
        }

        /**
         * Sets a custom validator, overriding the default Verix bridge / native fallback.
         * @param ValidatorInterface $validator The validator to use.
         * @return static The schema, for fluent chaining.
         */
        public function setValidator (ValidatorInterface $validator) : static {
            $this->validator = $validator;
            return $this;
        }

        /**
         * Validates a configuration instance against all registered rules and returns a map of every
         * violated key to its `SchemaViolationException`. An empty array means the configuration is
         * fully valid.
         * @param Configuration $config The configuration instance to validate.
         * @return array<string, SchemaViolationException> A map of key to violation; empty on success.
         */
        public function validate (Configuration $config) : array {
            $validator  = $this->getValidator();
            $violations = [];

            foreach ($this->rules as $key => $rule) {
                $value = $config->get($key, null);

                if ($value === null) {
                    if ($rule["required"]) {
                        $violations[$key] = new SchemaViolationException(
                            $key,
                            $rule["expression"],
                            ["Key '{$key}' is required but is not set in the configuration."]
                        );
                    }
                    continue;
                }

                $errors = $validator->check($rule["expression"], $value);

                if (!empty($errors)) {
                    $violations[$key] = new SchemaViolationException($key, $rule["expression"], $errors);
                }
            }

            return $violations;
        }

        /**
         * Validates all rules belonging to a named section and returns a map of
         * violated keys to their `SchemaViolationException`. The section prefix is the
         * name passed to `section()` when the rules were registered — only keys that
         * start with `"{$name}."` are evaluated. An empty array means all section rules
         * pass. If no rules are registered under the given section an empty array is
         * returned.
         * @param Configuration $config The configuration instance to validate.
         * @param string        $name   The section name (prefix) to validate.
         * @return array<string, SchemaViolationException> A map of key to violation; empty on success.
         */
        public function validateSection (Configuration $config, string $name) : array {
            $prefix    = $name . ".";
            $validator = $this->getValidator();
            $violations = [];

            foreach ($this->rules as $key => $rule) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }

                $value = $config->get($key, null);

                if ($value === null) {
                    if ($rule["required"]) {
                        $violations[$key] = new SchemaViolationException(
                            $key,
                            $rule["expression"],
                            ["Key '{$key}' is required but is not set in the configuration."]
                        );
                    }
                    continue;
                }

                $errors = $validator->check($rule["expression"], $value);

                if (!empty($errors)) {
                    $violations[$key] = new SchemaViolationException($key, $rule["expression"], $errors);
                }
            }

            return $violations;
        }
    }
?>