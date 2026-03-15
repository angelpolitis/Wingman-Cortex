<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Tests
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
    use Wingman\Cortex\Enums\MergeStrategy;
    use Wingman\Cortex\Exceptions\ConstantViolationException;
    use Wingman\Cortex\Exceptions\FrozenConfigurationException;

    /**
     * Comprehensive tests for the `Configuration` class, covering all core
     * data-store operations: CRUD, namespace resolution, immutability guards,
     * snapshot/reset, merge strategies, batch mode, and change notification.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationTest extends Test {
        /**
         * The configuration instance under test.
         * @var Configuration
         */
        private Configuration $config;

        /**
         * The implicit (default) namespace used by the configuration.
         * @var string
         */
        private string $ns;

        /**
         * Creates a fresh anonymous configuration before each test method.
         */
        public function setUp () : void {
            $this->config = new Configuration();
            $this->ns = $this->config->getImplicitNamespace();
        }

        /**
         * Resets the global configuration registry after each test method to
         * prevent cross-test pollution from any named configuration instances.
         */
        public function tearDown () : void {
            ConfigurationRegistry::reset();
        }

        // ─── Core Store ────────────────────────────────────────────────────────

        #[Group("Core Store")]
        #[Define(
            name: "Set And Get Scalar",
            description: "A scalar value set under a bare key is retrievable via get()."
        )]
        public function testSetAndGetScalar () : void {
            $this->config->set("colour", "blue");

            $this->assertTrue($this->config->get("colour") === "blue", "Expected get() to return the set scalar value.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Set And Get Nested Key",
            description: "A value stored under a dot-separated path is retrievable with the same path."
        )]
        public function testSetAndGetNestedKey () : void {
            $this->config->set("db.host", "localhost");

            $this->assertTrue($this->config->get("db.host") === "localhost", "Expected nested key to be retrievable.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Get Returns Default When Key Absent",
            description: "get() returns the supplied default value when the requested key does not exist."
        )]
        public function testGetReturnsDefaultWhenKeyAbsent () : void {
            $result = $this->config->get("missing.key", "fallback");

            $this->assertTrue($result === "fallback", "Expected the default value to be returned for an absent key.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Get Returns Null By Default",
            description: "get() returns null when no default is specified and the key does not exist."
        )]
        public function testGetReturnsNullByDefault () : void {
            $result = $this->config->get("ghost");

            $this->assertTrue($result === null, "Expected null for a missing key with no explicit default.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Has Returns True For Existing Bare Key",
            description: "has() returns true for a bare key that was previously set via set()."
        )]
        public function testHasReturnsTrueForExistingBareKey () : void {
            $this->config->set("alive", true);

            $this->assertTrue($this->config->has("alive"), "has() should return true for a key that was set.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Has Returns False For Missing Key",
            description: "has() returns false for a key that was never written."
        )]
        public function testHasReturnsFalseForMissingKey () : void {
            $this->assertTrue(!$this->config->has("phantom"), "has() should return false for an absent key.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Has Applies Implicit Namespace For Bare Keys",
            description: "has() must use the implicit namespace when no explicit namespace is given, matching the behaviour of set() and get()."
        )]
        public function testHasAppliesImplicitNamespaceForBareKeys () : void {
            $this->config->set("omega", 99);

            $this->assertTrue($this->config->has("omega"), "has() should find a bare key stored in the implicit namespace.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Unset Removes Key",
            description: "unset() removes a previously set key so that has() subsequently returns false."
        )]
        public function testUnsetRemovesKey () : void {
            $this->config->set("temp", "value");
            $this->config->unset("temp");

            $this->assertTrue(!$this->config->has("temp"), "Key should be absent after unset().");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Unset Applies Implicit Namespace For Bare Keys",
            description: "unset() uses the implicit namespace when no explicit namespace is present in the key, matching set() and has() behaviour."
        )]
        public function testUnsetAppliesImplicitNamespaceForBareKeys () : void {
            $this->config->set("erasable", "bye");
            $this->config->unset("erasable");

            $this->assertTrue(!$this->config->has("erasable"), "Bare-key unset() should remove the key from the implicit namespace.");
        }

        #[Group("Core Store")]
        #[Define(
            name: "IsSet Returns True After Set",
            description: "isSet() returns true immediately after set() writes a value."
        )]
        public function testIsSetReturnsTrueAfterSet () : void {
            $this->config->set("present", 42);

            $this->assertTrue($this->config->isSet("present"), "isSet() should return true after set().");
        }

        #[Group("Core Store")]
        #[Define(
            name: "Set Force Overwrites Array Container",
            description: "Calling set() with force=true replaces an existing array node without throwing ContainerOverwriteException."
        )]
        public function testSetForceOverwritesArrayContainer () : void {
            $this->config->set("group.key", "inner");
            $this->config->set("group", "scalar", true);

            $this->assertTrue($this->config->get("group") === "scalar", "Force flag should allow overwriting an array node with a scalar.");
        }

        // ─── Namespacing ───────────────────────────────────────────────────────

        #[Group("Namespacing")]
        #[Define(
            name: "Explicit Namespace Set And Get",
            description: "A value stored under an explicit namespace is retrievable only via that namespace."
        )]
        public function testExplicitNamespaceSetAndGet () : void {
            $this->config->set("services:cache.driver", "redis");

            $this->assertTrue($this->config->get("services:cache.driver") === "redis", "Namespaced key should be retrievable with the full key.");
        }

        #[Group("Namespacing")]
        #[Define(
            name: "Set Implicit Namespace Changes Resolution",
            description: "After setImplicitNamespace(), bare keys are resolved in the new namespace rather than the original default."
        )]
        public function testSetImplicitNamespaceChangesResolution () : void {
            $this->config->setImplicitNamespace("app");
            $this->config->set("locale", "en_GB");

            $this->assertTrue($this->config->get("app:locale") === "en_GB", "Bare key should be stored in the newly set implicit namespace.");
        }

        #[Group("Namespacing")]
        #[Define(
            name: "Default Implicit Namespace Is Slash",
            description: "A fresh Configuration uses '/' as its implicit namespace, as documented by DEFAULT_NAMESPACE."
        )]
        public function testDefaultImplicitNamespaceIsSlash () : void {
            $this->assertTrue($this->config->getImplicitNamespace() === Configuration::DEFAULT_NAMESPACE, "Default implicit namespace should be '/'.");
        }

        // ─── Freeze ────────────────────────────────────────────────────────────

        #[Group("Freeze")]
        #[Define(
            name: "Freeze Prevents Set",
            description: "Calling set() on a frozen configuration throws FrozenConfigurationException."
        )]
        public function testFreezePreventsMutation () : void {
            $thrown = false;

            try {
                $this->config->freeze();
                $this->config->set("key", "value");
            } catch (FrozenConfigurationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "FrozenConfigurationException should be thrown when writing to a frozen configuration.");
        }

        #[Group("Freeze")]
        #[Define(
            name: "IsFrozen Returns True After Freeze",
            description: "isFrozen() returns true after freeze() has been called."
        )]
        public function testIsFrozenReturnsTrueAfterFreeze () : void {
            $this->config->freeze();

            $this->assertTrue($this->config->isFrozen(), "isFrozen() should return true after freeze().");
        }

        #[Group("Freeze")]
        #[Define(
            name: "FreezeNamespace Prevents Mutation To That Namespace",
            description: "set() on a namespace-frozen configuration throws FrozenConfigurationException for keys in that namespace."
        )]
        public function testFreezeNamespacePreventsMutationToThatNamespace () : void {
            $thrown = false;

            try {
                $this->config->freezeNamespace("locked");
                $this->config->set("locked:key", "value");
            } catch (FrozenConfigurationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "FrozenConfigurationException should be thrown when writing to a namespace-frozen namespace.");
        }

        #[Group("Freeze")]
        #[Define(
            name: "FreezeNamespace Allows Mutation To Other Namespaces",
            description: "Freezing one namespace does not prevent writes to other namespaces."
        )]
        public function testFreezeNamespaceAllowsMutationToOtherNamespace () : void {
            $this->config->freezeNamespace("sealed");
            $this->config->set("open:key", "writable");

            $this->assertTrue($this->config->get("open:key") === "writable", "Writes to non-frozen namespaces should succeed.");
        }

        #[Group("Freeze")]
        #[Define(
            name: "IsNamespaceFrozen Returns True After FreezeNamespace",
            description: "isNamespaceFrozen() returns true for the frozen namespace and false for others."
        )]
        public function testIsNamespaceFrozenReturnsTrueAfterFreeze () : void {
            $this->config->freezeNamespace("frozen");

            $this->assertTrue($this->config->isNamespaceFrozen("frozen"), "isNamespaceFrozen() should return true for the frozen namespace.");
            $this->assertTrue(!$this->config->isNamespaceFrozen("other"), "isNamespaceFrozen() should return false for an unrelated namespace.");
        }

        #[Group("Freeze")]
        #[Define(
            name: "Unset On Frozen Config Throws",
            description: "Calling unset() on a frozen configuration throws FrozenConfigurationException."
        )]
        public function testUnsetOnFrozenConfigThrows () : void {
            $thrown = false;

            try {
                $this->config->set("perishable", "yes");
                $this->config->freeze();
                $this->config->unset("perishable");
            } catch (FrozenConfigurationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "FrozenConfigurationException should be thrown when unsetting a key in a frozen configuration.");
        }

        // ─── Constants ─────────────────────────────────────────────────────────

        #[Group("Constants")]
        #[Define(
            name: "SetConst Locks Key Against Set",
            description: "After setConst(), calling set() for the same key throws ConstantViolationException."
        )]
        public function testSetConstLocksKey () : void {
            $thrown = false;

            try {
                $this->config->setConst("version", "1.0");
                $this->config->set("version", "2.0");
            } catch (ConstantViolationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConstantViolationException should be thrown when overwriting a constant key via set().");
        }

        #[Group("Constants")]
        #[Define(
            name: "IsConst Returns True After SetConst",
            description: "isConst() returns true for a key registered as a constant."
        )]
        public function testIsConstReturnsTrueAfterSetConst () : void {
            $this->config->setConst("immutable", "yes");

            $this->assertTrue($this->config->isConst("immutable"), "isConst() should return true after setConst().");
        }

        #[Group("Constants")]
        #[Define(
            name: "Unset On Const Key Throws",
            description: "Calling unset() on a constant key throws ConstantViolationException."
        )]
        public function testUnsetConstThrows () : void {
            $thrown = false;

            try {
                $this->config->setConst("pinned", "forever");
                $this->config->unset("pinned");
            } catch (ConstantViolationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConstantViolationException should be thrown when unsetting a constant key.");
        }

        #[Group("Constants")]
        #[Define(
            name: "MergeFlat Throws On Const Key Overwrite",
            description: "mergeFlat() throws ConstantViolationException when the incoming flat data attempts to overwrite a constant key."
        )]
        public function testMergeFlatThrowsOnConstKeyOverwrite () : void {
            $thrown = false;

            try {
                $this->config->setConst("api.token", "secret");
                $this->config->mergeFlat(["api.token" => "hacked"]);
            } catch (ConstantViolationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConstantViolationException should be thrown when mergeFlat() targets a constant key.");
        }

        #[Group("Constants")]
        #[Define(
            name: "Merge In Strict Mode Throws On Const Key",
            description: "merge() in strict mode throws ConstantViolationException when an incoming key would overwrite a constant."
        )]
        public function testMergeInStrictModeThrowsOnConstKey () : void {
            $thrown = false;

            try {
                $this->config->setConst("locked", "original");
                $this->config->setStrict(true);
                $this->config->merge(["locked" => "overwritten"]);
            } catch (ConstantViolationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConstantViolationException should be thrown when merge() in strict mode targets a constant key.");
        }

        #[Group("Constants")]
        #[Define(
            name: "Merge In Non-Strict Mode Skips Const Key",
            description: "merge() in non-strict mode silently skips an incoming key that would overwrite a constant, leaving the original value intact."
        )]
        public function testMergeInNonStrictModeSkipsConstKey () : void {
            $this->config->set("locked", "original");
            $this->config->setConst("locked", "original");
            $this->config->setStrict(false);
            $this->config->merge(["locked" => "overwritten", "fresh" => "new"]);

            $this->assertTrue($this->config->get("locked") === "original", "Constant key should remain unchanged after non-strict merge.");
            $this->assertTrue($this->config->get("fresh") === "new", "Non-constant key should be merged normally.");
        }

        #[Group("Constants")]
        #[Define(
            name: "SetConst With Schema Validates Value",
            description: "setConst() with a schema expression validates the value against that schema before locking it."
        )]
        public function testSetConstWithSchemaValidatesValue () : void {
            $this->config->setConst("port", 8080, "int");

            $this->assertTrue($this->config->isConst("port"), "Key should be registered as a constant after successful schema validation.");
            $this->assertTrue($this->config->get("port") === 8080, "The validated const value should be stored correctly.");
        }

        // ─── Snapshot & Reset ──────────────────────────────────────────────────

        #[Group("Snapshot & Reset")]
        #[Define(
            name: "Snapshot And Reset Rolls Back Mutations",
            description: "After taking a snapshot, any subsequent mutations are fully undone by reset()."
        )]
        public function testSnapshotAndResetRollsBackMutations () : void {
            $this->config->set("baseline", "A");
            $this->config->snapshot();
            $this->config->set("baseline", "B");
            $this->config->set("extra", "C");
            $this->config->reset();

            $this->assertTrue($this->config->get("baseline") === "A", "reset() should restore the snapshotted value.");
            $this->assertTrue($this->config->get("extra") === null, "Keys added after the snapshot should be removed by reset().");
        }

        #[Group("Snapshot & Reset")]
        #[Define(
            name: "Reset Without Snapshot Clears All Data",
            description: "Calling reset() before any snapshot() has been taken clears all buckets and constants entirely."
        )]
        public function testResetWithoutSnapshotClearsAll () : void {
            $this->config->set("foo", "bar");
            $this->config->reset();

            $this->assertTrue($this->config->get("foo") === null, "reset() without a snapshot should remove all stored keys.");
        }

        #[Group("Snapshot & Reset")]
        #[Define(
            name: "ResetAll Rolls Back All Registered Configurations",
            description: "Configuration::resetAll() rolls every named configuration in the registry back to its last snapshot."
        )]
        public function testResetAllRollsBackAllRegisteredConfigs () : void {
            $config = new Configuration("test_reset_all");
            $config->set("state", "before");
            $config->snapshot();
            $config->set("state", "after");
            Configuration::resetAll();

            $this->assertTrue($config->get("state") === "before", "resetAll() should roll the configuration back to its snapshot.");
        }

        // ─── Merging ───────────────────────────────────────────────────────────
        #[Group("Merging")]
        #[Define(
            name: "Merge Inserts Data Into Target Namespace",
            description: "merge() with a namespaced map makes the incoming keys accessible via the standard get() API."
        )]
        public function testMergeInsertsDataIntoTargetNamespace () : void {
            $this->config->merge(["alpha" => 1, "beta" => 2]);

            $this->assertTrue($this->config->get("alpha") === 1, "Merged key 'alpha' should be retrievable.");
            $this->assertTrue($this->config->get("beta") === 2, "Merged key 'beta' should be retrievable.");
        }

        #[Group("Merging")]
        #[Define(
            name: "MergeFlat Inserts From Dot Notation",
            description: "mergeFlat() with a flat dot-notation array makes every key accessible via the standard get() API."
        )]
        public function testMergeFlatInsertsDotNotation () : void {
            $this->config->mergeFlat(["server.host" => "127.0.0.1", "server.port" => 3000]);

            $this->assertTrue($this->config->get("server.host") === "127.0.0.1", "mergeFlat() should insert 'server.host'.");
            $this->assertTrue($this->config->get("server.port") === 3000, "mergeFlat() should insert 'server.port'.");
        }

        #[Group("Merging")]
        #[Define(
            name: "Merge Strategy Append Appends Array Values",
            description: "When the merge strategy is APPEND, array values in the incoming data are appended rather than replaced."
        )]
        public function testMergeStrategyAppendAppendsArrayValues () : void {
            $this->config->set("tags", ["php"]);
            $this->config->setMergeStrategy(MergeStrategy::APPEND);
            $this->config->merge(["tags" => ["cortex"]]);

            $allData = $this->config->toArray();
            $tags = $allData[$this->ns]["tags"] ?? [];

            $this->assertTrue(is_array($tags), "Merged tags should be an array.");
            $this->assertTrue(in_array("php", $tags, true), "Original tag 'php' should still be present after APPEND merge.");
            $this->assertTrue(in_array("cortex", $tags, true), "New tag 'cortex' should be present after APPEND merge.");
        }

        // ─── Batch Mode ────────────────────────────────────────────────────────

        #[Group("Batch Mode")]
        #[Define(
            name: "Batch Dispatches Single Signal After All Mutations",
            description: "Mutations inside batch() do not each individually fire onChange listeners; a single aggregate signal is dispatched when the batch ends."
        )]
        public function testBatchDispatchesSingleSignalAfterAllMutations () : void {
            $firedCount = 0;

            $this->config->onChange("*", function () use (&$firedCount) {
                $firedCount++;
            });

            $this->config->batch(function (Configuration $c) {
                $c->set("a", 1);
                $c->set("b", 2);
                $c->set("c", 3);
            });

            $this->assertTrue($firedCount <= 3, "Listener should not fire individually for every key inside a batch.");
        }

        #[Group("Batch Mode")]
        #[Define(
            name: "Batch Data Is Accessible After Execution",
            description: "All mutations performed inside the batch() callback are visible through get() after the batch completes."
        )]
        public function testBatchDataIsAccessibleAfterExecution () : void {
            $this->config->batch(function (Configuration $c) {
                $c->set("x", 10);
                $c->set("y", 20);
            });

            $this->assertTrue($this->config->get("x") === 10, "Key 'x' should be retrievable after batch().");
            $this->assertTrue($this->config->get("y") === 20, "Key 'y' should be retrievable after batch().");
        }

        // ─── Change Notification ───────────────────────────────────────────────

        #[Group("Change Notification")]
        #[Define(
            name: "OnChange Is Fired On Set",
            description: "A listener registered via onChange() is invoked when a matching key is written via set()."
        )]
        public function testOnChangeIsFiredOnSet () : void {
            $fired = false;

            $this->config->onChange("*", function () use (&$fired) {
                $fired = true;
            });

            $this->config->set("colour", "green");

            $this->assertTrue($fired, "onChange listener should be fired after set() for a matching key.");
        }

        #[Group("Change Notification")]
        #[Define(
            name: "OnChange Wildcard Pattern Matches Any Key",
            description: "A wildcard '*' pattern in onChange() matches every set() operation regardless of the key name."
        )]
        public function testOnChangeWildcardMatchesAnyKey () : void {
            $changedKeys = [];

            $this->config->onChange("*", function (string $key) use (&$changedKeys) {
                $changedKeys[] = $key;
            });

            $this->config->set("alpha", 1);
            $this->config->set("beta", 2);

            $this->assertTrue(count($changedKeys) >= 2, "Wildcard listener should fire for every set() call.");
        }

        #[Group("Change Notification")]
        #[Define(
            name: "OffChange Removes Listener",
            description: "A listener deregistered via offChange() is no longer invoked on subsequent mutations."
        )]
        public function testOffChangeRemovesListener () : void {
            $fired = false;
            $handle = $this->config->onChange("*", function () use (&$fired) {
                $fired = true;
            });

            $this->config->offChange($handle);
            $this->config->set("anything", "value");

            $this->assertTrue(!$fired, "Deregistered listener should not fire after offChange().");
        }

        // ─── ArrayAccess ───────────────────────────────────────────────────────

        #[Group("ArrayAccess")]
        #[Define(
            name: "OffsetSet And OffsetGet Work Via Array Syntax",
            description: "Configuration instances support array-style \$config['key'] = 'value' assignment and retrieval."
        )]
        public function testOffsetSetAndOffsetGetWorkViaArraySyntax () : void {
            $this->config["language"] = "php";

            $this->assertTrue($this->config["language"] === "php", "Array-write and array-read should behave identically to set/get.");
        }

        #[Group("ArrayAccess")]
        #[Define(
            name: "OffsetExists Returns True For Existing Key",
            description: "isset(\$config['key']) returns true for a key that has been set."
        )]
        public function testOffsetExistsReturnsTrueForExistingKey () : void {
            $this->config["check"] = true;

            $this->assertTrue(isset($this->config["check"]), "isset() on an existing key should return true via ArrayAccess.");
        }

        #[Group("ArrayAccess")]
        #[Define(
            name: "OffsetUnset Removes Key Via Array Syntax",
            description: "unset(\$config['key']) removes the key so that it is no longer accessible."
        )]
        public function testOffsetUnsetRemovesKeyViaArraySyntax () : void {
            $this->config["disposable"] = "value";
            unset($this->config["disposable"]);

            $this->assertTrue(!isset($this->config["disposable"]), "Key should be absent after unset via ArrayAccess.");
        }

        // ─── Branch & Immutable ────────────────────────────────────────────────

        #[Group("Branch & Immutable")]
        #[Define(
            name: "Branch Creates Independent Copy",
            description: "branch() returns a cloned configuration that can be mutated without affecting the original."
        )]
        public function testBranchCreatesIndependentCopy () : void {
            $this->config->set("shared", "original");
            $branch = $this->config->branch();
            $branch->set("shared", "modified");

            $this->assertTrue($this->config->get("shared") === "original", "Mutating a branch should not affect the original configuration.");
            $this->assertTrue($branch->get("shared") === "modified", "Branch mutation should only affect the branch.");
        }

        #[Group("Branch & Immutable")]
        #[Define(
            name: "Immutable Branch Is Frozen",
            description: "immutable() returns a branch that is immediately frozen; any write to it throws FrozenConfigurationException."
        )]
        public function testImmutableBranchIsFrozen () : void {
            $thrown = false;

            try {
                $this->config->set("data", "value");
                $frozen = $this->config->immutable();
                $frozen->set("data", "new");
            } catch (FrozenConfigurationException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "FrozenConfigurationException should be thrown when writing to an immutable branch.");
        }

        // ─── Misc ──────────────────────────────────────────────────────────────

        #[Group("Misc")]
        #[Define(
            name: "FromIterable Creates Populated Configuration",
            description: "Configuration::fromIterable() returns a configuration with all key-value pairs from the iterable accessible via get()."
        )]
        public function testFromIterableCreatesPopulatedConfiguration () : void {
            $config = Configuration::fromIterable(["a" => 1, "b" => 2]);

            $this->assertTrue($config->get("a") === 1, "Key 'a' should be accessible after fromIterable().");
            $this->assertTrue($config->get("b") === 2, "Key 'b' should be accessible after fromIterable().");
        }

        #[Group("Misc")]
        #[Define(
            name: "GetName Returns Registered Name",
            description: "getName() returns the name the configuration was constructed with."
        )]
        public function testGetNameReturnsRegisteredName () : void {
            $named = new Configuration("my_config");

            $this->assertTrue($named->getName() === "my_config", "getName() should return the name given at construction.");
        }

        #[Group("Misc")]
        #[Define(
            name: "Named Configuration Is Retrievable From Registry",
            description: "A named configuration is registered automatically and can be retrieved via Configuration::find()."
        )]
        public function testNamedConfigurationIsRetrievableFromRegistry () : void {
            $config = new Configuration("findable");

            $found = Configuration::find("findable");

            $this->assertTrue($found === $config, "Configuration::find() should return the registered instance by name.");
        }

        #[Group("Misc")]
        #[Define(
            name: "Lazy Namespace Is Loaded On First Access",
            description: "registerNamespace() defers loading until the namespace is first accessed via get() or has()."
        )]
        public function testLazyNamespaceIsLoadedOnFirstAccess () : void {
            $tmpFile = sys_get_temp_dir() . "/lazy_ns_test.php";
            file_put_contents($tmpFile, "<?php return [\"lazy_key\" => \"lazy_value\"];");

            $this->config->registerNamespace("deferred", $tmpFile, ["mapDirectoryStructure" => false]);

            $this->assertTrue(!$this->config->isNamespaceLoaded("deferred"), "Namespace should not be loaded before first access.");

            $this->config->has("deferred:lazy_key");

            $this->assertTrue($this->config->isNamespaceLoaded("deferred"), "Namespace should be loaded after first access via has().");

            $value = $this->config->get("deferred:lazy_key");

            $this->assertTrue($value === "lazy_value", "Value from lazy-loaded namespace should be accessible.");

            @unlink($tmpFile);
        }
    }
?>