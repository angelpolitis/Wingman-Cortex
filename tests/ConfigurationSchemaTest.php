<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Schema Tests
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
    use Wingman\Cortex\Configuration;
    use Wingman\Cortex\ConfigurationRegistry;
    use Wingman\Cortex\ConfigurationSchema;
    use Wingman\Cortex\Exceptions\ConfigurationSchemaException;

    /**
     * Tests for the `ConfigurationSchema` class, covering rule registration,
     * validation against a configuration, required vs optional keys, section
     * support, and the assert() shorthand that throws on violation.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationSchemaTest extends Test {
        /**
         * The schema instance under test.
         * @var ConfigurationSchema
         */
        private ConfigurationSchema $schema;

        /**
         * The configuration instance used as a validation source.
         * @var Configuration
         */
        private Configuration $config;

        /**
         * Creates a fresh schema and configuration before each test method.
         */
        public function setUp () : void {
            $this->schema = new ConfigurationSchema();
            $this->config = new Configuration();
        }

        /**
         * Resets the global registry after each test method.
         */
        public function tearDown () : void {
            ConfigurationRegistry::reset();
        }

        // ─── Rule Registration ─────────────────────────────────────────────────

        #[Group("Rule Registration")]
        #[Define(
            name: "Set Adds Required Rule",
            description: "set() registers a required rule; validate() reports a violation when the key is absent."
        )]
        public function testSetAddsRequiredRule () : void {
            $this->schema->set("required.key", "string");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) > 0, "A missing required key should generate a schema violation.");
        }

        #[Group("Rule Registration")]
        #[Define(
            name: "SetOptional Adds Optional Rule That Does Not Violate When Absent",
            description: "setOptional() registers a non-required rule; validate() produces no violation when the key is absent."
        )]
        public function testSetOptionalAddsOptionalRuleNoViolationWhenAbsent () : void {
            $this->schema->setOptional("optional.key", "string");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) === 0, "An absent optional key should not generate a schema violation.");
        }

        // ─── Validation ────────────────────────────────────────────────────────

        #[Group("Validation")]
        #[Define(
            name: "Validate Returns Empty Array For Passing Configuration",
            description: "validate() returns an empty array when all required keys are present and conform to their type expressions."
        )]
        public function testValidateReturnsEmptyArrayForPassingConfiguration () : void {
            $this->config->set("db.host", "localhost");
            $this->config->set("db.port", 5432);

            $this->schema->set("db.host", "string");
            $this->schema->set("db.port", "int");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) === 0, "validate() should return no violations when all rules pass.");
        }

        #[Group("Validation")]
        #[Define(
            name: "Validate Reports Violation For Wrong Type",
            description: "validate() returns a non-empty array when a key holds a value that does not match the expected type expression."
        )]
        public function testValidateReportsViolationForWrongType () : void {
            $this->config->set("timeout", 0);

            $this->schema->set("timeout", "int<min=1>");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) > 0, "A type mismatch should result in at least one violation entry.");
        }

        #[Group("Validation")]
        #[Define(
            name: "Validate Accepts Correct Boolean Value",
            description: "validate() returns no violations when a bool-typed rule is satisfied by a true/false value."
        )]
        public function testValidateAcceptsCorrectBooleanValue () : void {
            $this->config->set("debug", true);

            $this->schema->set("debug", "bool");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) === 0, "A boolean value should satisfy a 'bool' rule without violations.");
        }

        #[Group("Validation")]
        #[Define(
            name: "Validate Reports Violation For Missing Required Key",
            description: "validate() lists the absent key as a violation when a required rule exists but the key is not in the configuration."
        )]
        public function testValidateReportsViolationForMissingRequiredKey () : void {
            $this->schema->set("must.exist", "string");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) > 0, "A missing required key should be listed as a violation.");
        }

        #[Group("Validation")]
        #[Define(
            name: "Validate Returns Violation For Optional Key With Wrong Type",
            description: "When an optional key is present but holds a value of the wrong type, validate() still reports the type violation."
        )]
        public function testValidateReportsViolationForOptionalKeyWithWrongType () : void {
            $this->config->set("limit", 0);

            $this->schema->setOptional("limit", "int<min=1>");

            $violations = $this->schema->validate($this->config);

            $this->assertTrue(count($violations) > 0, "An optional key with the wrong type should still generate a violation.");
        }

        // ─── Assert ────────────────────────────────────────────────────────────

        #[Group("Assert")]
        #[Define(
            name: "Assert Throws ConfigurationSchemaException On Violation",
            description: "assert() calls validate() and throws ConfigurationSchemaException when any violations are found."
        )]
        public function testAssertThrowsConfigurationSchemaExceptionOnViolation () : void {
            $thrown = false;

            try {
                $this->schema->set("critical.key", "string");
                $this->schema->assert($this->config);
            } catch (ConfigurationSchemaException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConfigurationSchemaException should be thrown when assert() finds violations.");
        }

        #[Group("Assert")]
        #[Define(
            name: "Assert Does Not Throw When All Rules Pass",
            description: "assert() returns the schema instance and does not throw when the configuration satisfies every rule."
        )]
        public function testAssertDoesNotThrowWhenAllRulesPass () : void {
            $this->config->set("name", "valid_string");
            $this->schema->set("name", "string");

            $result = $this->schema->assert($this->config);

            $this->assertTrue($result instanceof ConfigurationSchema, "assert() should return the ConfigurationSchema instance when no violations occur.");
        }

        // ─── Sections ──────────────────────────────────────────────────────────

        #[Group("Sections")]
        #[Define(
            name: "Section Groups Rules For AssertSection",
            description: "Rules added inside a section() block are validated in isolation by assertSection()."
        )]
        public function testSectionGroupsRulesForAssertSection () : void {
            $this->config->set("redis.host", "127.0.0.1");
            $this->config->set("redis.port", 6379);

            $this->schema
                ->section("redis")
                    ->set("host", "string")
                    ->set("port", "int")
                ->endSection();

            $violations = $this->schema->validateSection($this->config, "redis");

            $this->assertTrue(count($violations) === 0, "assertSection() should produce no violations when section rules are satisfied.");
        }

        #[Group("Sections")]
        #[Define(
            name: "Assert Section Throws On Section Violation",
            description: "assertSection() throws ConfigurationSchemaException when at least one rule in the section is violated."
        )]
        public function testAssertSectionThrowsOnSectionViolation () : void {
            $thrown = false;

            try {
                $this->schema
                    ->section("cache")
                        ->set("driver", "string")
                    ->endSection();

                $this->schema->assertSection($this->config, "cache");
            } catch (ConfigurationSchemaException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConfigurationSchemaException should be thrown when assertSection() finds a section violation.");
        }
    }
?>