<?php
    /*/
     * Project Name:    Wingman — Cortex — Interpolation Tests
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
    use Wingman\Cortex\Exceptions\CircularReferenceException;
    use Wingman\Cortex\Interpolator;
    use Wingman\Cortex\Variable;

    /**
     * Tests for the `Interpolator` class, verifying token expansion, nested
     * variable resolution, array recursion, missing-variable fallback, and the
     * circular-reference guard.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InterpolationTest extends Test {
        /**
         * The interpolator instance under test.
         * @var Interpolator
         */
        private Interpolator $interpolator;

        /**
         * Creates a fresh Interpolator before each test method.
         */
        public function setUp () : void {
            $this->interpolator = new Interpolator();
        }

        // ─── Token Expansion ───────────────────────────────────────────────────

        #[Group("Token Expansion")]
        #[Define(
            name: "Simple Variable Is Replaced",
            description: "An @{key} token is replaced with the value returned by the resolver for that key."
        )]
        public function testSimpleVariableIsReplaced () : void {
            $store = ["app.name" => "Cortex"];

            $result = $this->interpolator->interpolate(
                "@{app.name}",
                fn (Variable $v) => $store[$v->getName()] ?? null
            );

            $this->assertTrue($result === "Cortex", "The @{app.name} token should be replaced with 'Cortex'.");
        }

        #[Group("Token Expansion")]
        #[Define(
            name: "Multiple Tokens In Same String Are All Replaced",
            description: "A string with multiple @{key} tokens has all of them individually substituted."
        )]
        public function testMultipleTokensInSameStringAreAllReplaced () : void {
            $store = ["first" => "Wingman", "second" => "Cortex"];

            $result = $this->interpolator->interpolate(
                "Project: @{first} / Module: @{second}",
                fn (Variable $v) => $store[$v->getName()] ?? null
            );

            $this->assertTrue($result === "Project: Wingman / Module: Cortex", "Both tokens in the string should be replaced independently.");
        }

        #[Group("Token Expansion")]
        #[Define(
            name: "Missing Variable Returns Null And Leaves Token",
            description: "When the resolver returns null for a token, the token is left verbatim in the output string."
        )]
        public function testMissingVariableReturnsNullAndLeavesToken () : void {
            $result = $this->interpolator->interpolate(
                "prefix-@{missing}-suffix",
                fn (Variable $v) => null
            );

            $this->assertTrue(
                str_contains((string) $result, "prefix-") && str_contains((string) $result, "-suffix"),
                "A null-resolved token should leave surrounding static text intact."
            );
        }

        #[Group("Token Expansion")]
        #[Define(
            name: "Non-String Value Is Returned Directly",
            description: "When a non-string value (e.g. an integer) is passed, interpolate() returns it unchanged."
        )]
        public function testNonStringValueIsReturnedDirectly () : void {
            $result = $this->interpolator->interpolate(42, fn (Variable $v) => null);

            $this->assertTrue($result === 42, "A non-string value should be returned unchanged by interpolate().");
        }

        #[Group("Token Expansion")]
        #[Define(
            name: "Null Value Is Returned Directly",
            description: "When null is passed, interpolate() returns null without attempting pattern matching."
        )]
        public function testNullValueIsReturnedDirectly () : void {
            $result = $this->interpolator->interpolate(null, fn (Variable $v) => null);

            $this->assertTrue($result === null, "null should pass through interpolate() unchanged.");
        }

        // ─── Array Recursion ───────────────────────────────────────────────────

        #[Group("Array Recursion")]
        #[Define(
            name: "Array Values Are Interpolated Recursively",
            description: "When an array is passed, interpolate() traverses every leaf value and expands tokens."
        )]
        public function testArrayValuesAreInterpolatedRecursively () : void {
            $store = ["host" => "localhost", "port" => "5432"];

            $result = $this->interpolator->interpolate(
                ["dsn" => "pgsql://@{host}:@{port}"],
                fn (Variable $v) => $store[$v->getName()] ?? null
            );

            $this->assertTrue(
                is_array($result) && ($result["dsn"] ?? null) === "pgsql://localhost:5432",
                "Array leaf values should have their tokens expanded."
            );
        }

        #[Group("Array Recursion")]
        #[Define(
            name: "Nested Array Values Are Interpolated Recursively",
            description: "Deeply nested arrays have all leaf string values expanded by interpolate()."
        )]
        public function testNestedArrayValuesAreInterpolatedRecursively () : void {
            $store = ["env" => "production"];

            $result = $this->interpolator->interpolate(
                ["outer" => ["inner" => "Env: @{env}"]],
                fn (Variable $v) => $store[$v->getName()] ?? null
            );

            $this->assertTrue(
                is_array($result) && (($result["outer"]["inner"] ?? null) === "Env: production"),
                "Leaf values inside nested arrays should be interpolated."
            );
        }

        // ─── Entire String Replacement ─────────────────────────────────────────

        #[Group("Token Expansion")]
        #[Define(
            name: "Sole Token That Resolves To Non-String Returns Typed Value",
            description: "When the entire string is a single @{key} token and the resolver returns a non-string typed value, interpolate() returns the typed value directly."
        )]
        public function testSoleTokenThatResolvesToNonStringReturnsTypedValue () : void {
            $result = $this->interpolator->interpolate(
                "@{count}",
                fn (Variable $v) => 42
            );

            $this->assertTrue($result === 42, "A sole token resolved to int should make interpolate() return the int, not a string.");
        }

        // ─── Circular Reference Guard ──────────────────────────────────────────

        #[Group("Circular Reference Guard")]
        #[Define(
            name: "Circular Reference Throws CircularReferenceException",
            description: "When the resolver for a token internally calls interpolate() and refers back to the same token, CircularReferenceException is thrown."
        )]
        public function testCircularReferenceThrowsException () : void {
            $thrown = false;
            $interpolator = $this->interpolator;
            $resolver = function (Variable $v) use ($interpolator, &$resolver) {
                return $interpolator->interpolate("@{" . $v->getName() . "}", $resolver);
            };

            try {
                $interpolator->interpolate("@{infinite}", $resolver);
            } catch (CircularReferenceException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "CircularReferenceException should be thrown when a token resolves to itself recursively.");
        }

        // ─── VARIABLE_PATTERN constant ─────────────────────────────────────────

        #[Group("Constants")]
        #[Define(
            name: "VARIABLE_PATTERN Constant Is Non-Empty String",
            description: "Interpolator::VARIABLE_PATTERN is a non-empty string containing the @{ opening sentinel."
        )]
        public function testVariablePatternConstantIsNonEmptyString () : void {
            $this->assertTrue(
                is_string(Interpolator::VARIABLE_PATTERN) && str_contains(Interpolator::VARIABLE_PATTERN, "@{"),
                "VARIABLE_PATTERN should be a non-empty string containing the @{ sentinel."
            );
        }
    }
?>