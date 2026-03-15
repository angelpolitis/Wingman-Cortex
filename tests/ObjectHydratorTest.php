<?php
    /*/
     * Project Name:    Wingman — Cortex — Object Hydrator Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2025
     * Last Modified:   Mar 14 2025
    /*/

    # Use the Cortex.Tests namespace.
    namespace Wingman\Cortex\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Cortex\Attributes\Configurable;
    use Wingman\Cortex\Configuration;
    use Wingman\Cortex\ConfigurationRegistry;
    use Wingman\Cortex\Exceptions\SchemaViolationException;
    use Wingman\Cortex\ObjectHydrator;
    use Wingman\Cortex\Registry;

    /**
     * Tests for the `ObjectHydrator` class, covering attribute-driven hydration,
     * key-map hydration, static property hydration, capture, and schema
     * derivation from annotated classes.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ObjectHydratorTest extends Test {
        /**
         * The configuration instance used as a hydration source.
         * @var Configuration
         */
        private Configuration $config;

        /**
         * Creates a fresh anonymous configuration before each test method.
         */
        public function setUp () : void {
            $this->config = new Configuration();
        }

        /**
         * Resets the global registry and restores the Registry cache size to
         * its original value after every test.
         */
        public function tearDown () : void {
            Registry::setMaxKeyCacheSize(1000);
            ConfigurationRegistry::reset();
        }

        // ─── Map Mode ──────────────────────────────────────────────────────────

        #[Group("Map Mode")]
        #[Define(
            name: "Map Mode Hydrates Public Properties",
            description: "hydrate() with a key-map populates public object properties from the matching configuration keys."
        )]
        public function testMapModeHydratesPublicProperties () : void {
            $this->config->set("app.name", "Cortex");
            $this->config->set("app.version", "2.0");

            $target = new class {
                public string $name = "";
                public string $version = "";
            };

            Configuration::hydrate($target, $this->config, ["name" => "app.name", "version" => "app.version"]);

            $this->assertTrue($target->name === "Cortex", "Property 'name' should be hydrated with 'Cortex'.");
            $this->assertTrue($target->version === "2.0", "Property 'version' should be hydrated with '2.0'.");
        }

        #[Group("Map Mode")]
        #[Define(
            name: "Map Mode Skips Missing Keys Silently",
            description: "hydrate() in map mode silently skips property mappings whose configuration keys do not exist."
        )]
        public function testMapModeSkipsMissingKeysSilently () : void {
            $target = new class {
                public string $name = "unchanged";
            };

            Configuration::hydrate($target, $this->config, ["name" => "non.existent.key"]);

            $this->assertTrue($target->name === "unchanged", "Property should remain unchanged when the mapped key does not exist.");
        }

        #[Group("Map Mode")]
        #[Define(
            name: "Map Mode Handles Integer Values",
            description: "hydrate() in map mode correctly assigns integer configuration values to typed int properties."
        )]
        public function testMapModeHandlesIntegerValues () : void {
            $this->config->set("server.port", 8080);

            $target = new class {
                public int $port = 0;
            };

            Configuration::hydrate($target, $this->config, ["port" => "server.port"]);

            $this->assertTrue($target->port === 8080, "Integer value should be correctly assigned to an int property.");
        }

        // ─── Attribute Mode ────────────────────────────────────────────────────

        #[Group("Attribute Mode")]
        #[Define(
            name: "Attribute Mode Hydrates Configurable Properties",
            description: "hydrate() with no map reads #[Configurable] annotations and populates the corresponding properties."
        )]
        public function testAttributeModeHydratesConfigurableProperties () : void {
            $this->config->set("cortex.host", "localhost");
            $this->config->set("cortex.port", 9200);

            $target = new class {
                #[Configurable("cortex.host")]
                public string $host = "";

                #[Configurable("cortex.port")]
                public int $port = 0;
            };

            Configuration::hydrate($target, $this->config);

            $this->assertTrue($target->host === "localhost", "Attribute-mapped 'host' should be hydrated from the configuration.");
            $this->assertTrue($target->port === 9200, "Attribute-mapped 'port' should be hydrated from the configuration.");
        }

        #[Group("Attribute Mode")]
        #[Define(
            name: "Attribute Mode Uses Default When Key Is Absent",
            description: "When a #[Configurable] key is absent from the configuration, the declared default value is applied."
        )]
        public function testAttributeModeUsesDefaultWhenKeyIsAbsent () : void {
            $target = new class {
                #[Configurable("cortex.absent.key", default: "default_value")]
                public string $value = "";
            };

            Configuration::hydrate($target, $this->config);

            $this->assertTrue($target->value === "default_value", "The declared default should be used when the configuration key is missing.");
        }

        // ─── Static Property Hydration ─────────────────────────────────────────

        #[Group("Static Properties")]
        #[Define(
            name: "Static Hydration Updates Registry MaxKeyCacheSize",
            description: "Passing a class-string to hydrate() updates the static property annotated with #[Configurable] on the Registry class."
        )]
        public function testStaticHydrationUpdatesRegistryMaxKeyCacheSize () : void {
            $this->config->set("cortex.registry.maxKeyCacheSize", 2500);

            Configuration::hydrate(Registry::class, $this->config);

            $this->assertTrue(Registry::getMaxKeyCacheSize() === 2500, "Static property should be updated by class-string hydration.");
        }

        #[Group("Static Properties")]
        #[Define(
            name: "Static Hydration Throws For Schema Constraint Violation",
            description: "When the configuration carries a value that violates the property's schema (int<min=1>), hydrate() throws SchemaViolationException."
        )]
        public function testStaticHydrationIgnoresValuesBelowMinimum () : void {
            $thrown = false;

            try {
                $this->config->set("cortex.registry.maxKeyCacheSize", 0);
                Configuration::hydrate(Registry::class, $this->config);
            } catch (SchemaViolationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "SchemaViolationException should be thrown when hydrating a property with a value that violates its schema constraint.");
        }

        // ─── Capture & Restore ─────────────────────────────────────────────────

        #[Group("Capture & Restore")]
        #[Define(
            name: "Capture And Restore Preserves Property Values",
            description: "ObjectHydrator::capture() snapshots the current property values; restore() reverts them to that snapshot after modification."
        )]
        public function testCaptureWritesConfigurablePropertiesIntoConfiguration () : void {
            $source = new class {
                #[Configurable("captured.name")]
                public string $name = "Wingman";

                #[Configurable("captured.level")]
                public int $level = 7;
            };

            ObjectHydrator::capture($source, "test_snapshot", $this->config);

            $source->name = "Changed";
            $source->level = 99;

            ObjectHydrator::restore($source, "test_snapshot", $this->config);

            $this->assertTrue($source->name === "Wingman", "capture/restore should preserve the original 'name' property value.");
            $this->assertTrue($source->level === 7, "capture/restore should preserve the original 'level' property value.");
        }

        // ─── Schema From Class ─────────────────────────────────────────────────

        #[Group("Schema From Class")]
        #[Define(
            name: "GetSchemaFromClass Returns ConfigurationSchema",
            description: "getSchemaFromClass() introspects a class's #[Configurable] annotations and returns a populated ConfigurationSchema."
        )]
        public function testGetSchemaFromClassReturnsConfigurationSchema () : void {
            $subject = new class {
                #[Configurable("schema.host", schema: "string")]
                public string $host = "";

                #[Configurable("schema.port", schema: "int")]
                public int $port = 0;
            };

            $schema = ObjectHydrator::getSchemaFromClass($subject);

            $this->assertTrue(
                $schema instanceof \Wingman\Cortex\ConfigurationSchema,
                "getSchemaFromClass() should return a ConfigurationSchema instance."
            );
        }

        #[Group("Schema From Class")]
        #[Define(
            name: "Schema From Class Validates Correctly",
            description: "A ConfigurationSchema derived from class annotations correctly detects constraint violations on the configuration."
        )]
        public function testSchemaFromClassValidatesCorrectly () : void {
            $subject = new class {
                #[Configurable("validated.count", schema: "int<min=1>")]
                public int $count = 1;
            };

            $schema = ObjectHydrator::getSchemaFromClass($subject);

            $this->config->set("validated.count", 0);
            $violations = $schema->validate($this->config);

            $this->assertTrue(count($violations) > 0, "Schema derived from class should detect a constraint violation.");
        }
    }
?>