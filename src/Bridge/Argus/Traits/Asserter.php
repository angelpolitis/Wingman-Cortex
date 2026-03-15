<?php
    /*/
     * Project Name:    Wingman — Cortex — Asserter Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2026
     * Last Modified:   Mar 14 2026
    /*/

    # Use the Cortex.Bridge.Argus.Traits namespace.
    namespace Wingman\Cortex\Bridge\Argus\Traits;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the trait is already in place there is nothing to do.
    if (trait_exists(__NAMESPACE__ . '\Asserter', false)) return;

    # Import the following classes to the current scope.
    use Throwable;
    use Wingman\Cortex\Configuration;

    /**
     * Provides assertion methods for verifying the state of a Configuration instance, including key existence,
     * key values, constant protection, frozen state, and namespace loading.
     * This trait is intended for use in test classes that need to assert Cortex-specific state. It follows the
     * same pattern as the Helix, Verix, and Locator Asserter traits, delegating result recording to the abstract
     * `recordAssertion` method that the consuming test class must supply.
     * @package Wingman\Cortex\Bridge\Argus\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Asserter {
        /**
         * Checks whether a key does or does not exist in the given configuration and records the result.
         * Calls `has()` on the supplied `Configuration` instance and compares the outcome against `$shouldExist`.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param bool $shouldExist Whether the key is expected to exist (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runKeyExistenceAssertion (Configuration $config, string $key, bool $shouldExist, string $message) : void {
            try {
                $status = $config->has($key);

                $this->recordAssertion(
                    $shouldExist ? $status : !$status,
                    ($shouldExist ? "Key exists: " : "Key does not exist: ") . $key,
                    $status ? "Key found" : "Key not found",
                    $message ?: "Cortex key existence assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    ($shouldExist ? "Key exists: " : "Key does not exist: ") . $key,
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex key existence assertion failed."
                );
            }
        }

        /**
         * Retrieves the value of a key from the given configuration, compares it to an expected value, and records the result.
         * Uses strict equality (`===`) for the comparison. If the key is absent, `get()` returns `null`, which is
         * itself a valid comparison target.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to retrieve, using full Cortex DSL notation.
         * @param mixed $expected The expected value to compare the retrieved value against.
         * @param bool $shouldMatch Whether the retrieved value is expected to equal `$expected` (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runKeyValueAssertion (Configuration $config, string $key, mixed $expected, bool $shouldMatch, string $message) : void {
            try {
                $actual = $config->get($key);
                $status = $actual === $expected;

                $this->recordAssertion(
                    $shouldMatch ? $status : !$status,
                    ($shouldMatch ? "Key equals: " : "Key does not equal: ") . $key . " => " . var_export($expected, true),
                    "Actual: " . var_export($actual, true),
                    $message ?: "Cortex key value assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    ($shouldMatch ? "Key equals: " : "Key does not equal: ") . $key . " => " . var_export($expected, true),
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex key value assertion failed."
                );
            }
        }

        /**
         * Checks whether a key is or is not registered as a constant in the given configuration and records the result.
         * Calls `isConst()` on the supplied `Configuration` instance.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param bool $shouldBeConst Whether the key is expected to be constant (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runKeyConstantAssertion (Configuration $config, string $key, bool $shouldBeConst, string $message) : void {
            try {
                $status = $config->isConst($key);

                $this->recordAssertion(
                    $shouldBeConst ? $status : !$status,
                    ($shouldBeConst ? "Key is constant: " : "Key is not constant: ") . $key,
                    $status ? "Key is constant" : "Key is not constant",
                    $message ?: "Cortex key constant assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    ($shouldBeConst ? "Key is constant: " : "Key is not constant: ") . $key,
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex key constant assertion failed."
                );
            }
        }

        /**
         * Checks whether the given configuration is or is not globally frozen and records the result.
         * Calls `isFrozen()` on the supplied `Configuration` instance.
         * @param Configuration $config The configuration instance to inspect.
         * @param bool $shouldBeFrozen Whether the configuration is expected to be frozen (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runFrozenAssertion (Configuration $config, bool $shouldBeFrozen, string $message) : void {
            try {
                $status = $config->isFrozen();

                $this->recordAssertion(
                    $shouldBeFrozen ? $status : !$status,
                    $shouldBeFrozen ? "Configuration is frozen" : "Configuration is not frozen",
                    $status ? "Configuration is frozen" : "Configuration is not frozen",
                    $message ?: "Cortex frozen state assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    $shouldBeFrozen ? "Configuration is frozen" : "Configuration is not frozen",
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex frozen state assertion failed."
                );
            }
        }

        /**
         * Checks whether a specific namespace within the given configuration is or is not frozen and records the result.
         * Calls `isNamespaceFrozen()` on the supplied `Configuration` instance.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param bool $shouldBeFrozen Whether the namespace is expected to be frozen (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runNamespaceFrozenAssertion (Configuration $config, string $namespace, bool $shouldBeFrozen, string $message) : void {
            try {
                $status = $config->isNamespaceFrozen($namespace);

                $this->recordAssertion(
                    $shouldBeFrozen ? $status : !$status,
                    ($shouldBeFrozen ? "Namespace is frozen: " : "Namespace is not frozen: ") . $namespace,
                    $status ? "Namespace is frozen" : "Namespace is not frozen",
                    $message ?: "Cortex namespace frozen assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    ($shouldBeFrozen ? "Namespace is frozen: " : "Namespace is not frozen: ") . $namespace,
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex namespace frozen assertion failed."
                );
            }
        }

        /**
         * Checks whether a namespace has or has not been loaded into the given configuration and records the result.
         * Calls `isNamespaceLoaded()` on the supplied `Configuration` instance.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param bool $shouldBeLoaded Whether the namespace is expected to be loaded (true) or not (false).
         * @param string $message An optional message providing additional context about the assertion.
         */
        private function runNamespaceLoadedAssertion (Configuration $config, string $namespace, bool $shouldBeLoaded, string $message) : void {
            try {
                $status = $config->isNamespaceLoaded($namespace);

                $this->recordAssertion(
                    $shouldBeLoaded ? $status : !$status,
                    ($shouldBeLoaded ? "Namespace is loaded: " : "Namespace is not loaded: ") . $namespace,
                    $status ? "Namespace is loaded" : "Namespace is not loaded",
                    $message ?: "Cortex namespace loaded assertion failed."
                );
            }
            catch (Throwable $e) {
                $this->recordAssertion(
                    false,
                    ($shouldBeLoaded ? "Namespace is loaded: " : "Namespace is not loaded: ") . $namespace,
                    "Error: " . $e->getMessage(),
                    $message ?: "Cortex namespace loaded assertion failed."
                );
            }
        }

        /**
         * Records the result of an assertion, including its status, expected and actual values, and an optional message.
         * This method is intended to be implemented by the consuming class to handle assertion recording in a way that fits its architecture.
         * @param bool $status The result of the assertion (true for pass, false for fail).
         * @param mixed $expected The expected value in the assertion.
         * @param mixed $actual The actual value obtained during the test.
         * @param string $message An optional message providing additional context about the assertion.
         */
        abstract protected function recordAssertion (bool $status, mixed $expected, mixed $actual, string $message) : void;

        /**
         * Asserts that the given configuration is globally frozen.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertFrozen (Configuration $config, string $message = "") : void {
            $this->runFrozenAssertion($config, true, $message);
        }

        /**
         * Asserts that the given key exists in the configuration.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyExists (Configuration $config, string $key, string $message = "") : void {
            $this->runKeyExistenceAssertion($config, $key, true, $message);
        }

        /**
         * Asserts that the given key is registered as a constant in the configuration.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyIsConst (Configuration $config, string $key, string $message = "") : void {
            $this->runKeyConstantAssertion($config, $key, true, $message);
        }

        /**
         * Asserts that the given key is not registered as a constant in the configuration.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyIsNotConst (Configuration $config, string $key, string $message = "") : void {
            $this->runKeyConstantAssertion($config, $key, false, $message);
        }

        /**
         * Asserts that the given key does not exist in the configuration.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to test, using full Cortex DSL notation.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyNotExists (Configuration $config, string $key, string $message = "") : void {
            $this->runKeyExistenceAssertion($config, $key, false, $message);
        }

        /**
         * Asserts that the given key's value is strictly equal to the expected value.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to retrieve, using full Cortex DSL notation.
         * @param mixed $expected The expected value.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyEquals (Configuration $config, string $key, mixed $expected, string $message = "") : void {
            $this->runKeyValueAssertion($config, $key, $expected, true, $message);
        }

        /**
         * Asserts that the given key's value is not strictly equal to the expected value.
         * @param Configuration $config The configuration instance to query.
         * @param string $key The query key to retrieve, using full Cortex DSL notation.
         * @param mixed $expected The value the key is expected not to hold.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertKeyNotEquals (Configuration $config, string $key, mixed $expected, string $message = "") : void {
            $this->runKeyValueAssertion($config, $key, $expected, false, $message);
        }

        /**
         * Asserts that the given namespace is frozen within the configuration.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertNamespaceFrozen (Configuration $config, string $namespace, string $message = "") : void {
            $this->runNamespaceFrozenAssertion($config, $namespace, true, $message);
        }

        /**
         * Asserts that the given namespace has been loaded into the configuration.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertNamespaceLoaded (Configuration $config, string $namespace, string $message = "") : void {
            $this->runNamespaceLoadedAssertion($config, $namespace, true, $message);
        }

        /**
         * Asserts that the given namespace is not frozen within the configuration.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertNamespaceNotFrozen (Configuration $config, string $namespace, string $message = "") : void {
            $this->runNamespaceFrozenAssertion($config, $namespace, false, $message);
        }

        /**
         * Asserts that the given namespace has not been loaded into the configuration.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $namespace The namespace name to check.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertNamespaceNotLoaded (Configuration $config, string $namespace, string $message = "") : void {
            $this->runNamespaceLoadedAssertion($config, $namespace, false, $message);
        }

        /**
         * Asserts that the given configuration is not globally frozen.
         * @param Configuration $config The configuration instance to inspect.
         * @param string $message An optional message providing additional context about the assertion.
         */
        public function assertNotFrozen (Configuration $config, string $message = "") : void {
            $this->runFrozenAssertion($config, false, $message);
        }
    }
?>