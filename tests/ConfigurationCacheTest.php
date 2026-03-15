<?php
    /*/
     * Project Name:    Wingman — Cortex — Configuration Cache Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 14 2025
     * Last Modified:   Mar 14 2025
    /*/

    # Use the Cortex.Tests namespace.
    namespace Wingman\Cortex\Tests;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Cortex\Configuration;
    use Wingman\Cortex\ConfigurationCache;
    use Wingman\Cortex\ConfigurationRegistry;

    /**
     * Tests for the `ConfigurationCache` class, covering file creation,
     * existence checks, round-trip serialisation, staleness detection,
     * and error handling for missing or malformed cache files.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigurationCacheTest extends Test {
        /**
         * The temporary directory used for cache files during testing.
         * @var string
         */
        private string $tmpDir;

        /**
         * The absolute path to the cache file under test.
         * @var string
         */
        private string $cachePath;

        /**
         * Creates a fresh temporary cache directory and path before each test.
         */
        public function setUp () : void {
            $this->tmpDir = sys_get_temp_dir() . "/cortex_cache_test_" . uniqid("", true);
            $this->cachePath = $this->tmpDir . "/config.cache.php";
        }

        /**
         * Removes all temporary files and directories created during the test,
         * and resets the global configuration registry.
         */
        public function tearDown () : void {
            if (is_file($this->cachePath)) {
                @unlink($this->cachePath);
            }

            if (is_dir($this->tmpDir)) {
                @rmdir($this->tmpDir);
            }

            ConfigurationRegistry::reset();
        }

        // ─── Existence ─────────────────────────────────────────────────────────

        #[Group("Existence")]
        #[Define(
            name: "Exists Returns False Before Write",
            description: "exists() returns false when the cache file has not yet been written."
        )]
        public function testExistsReturnsFalseBeforeWrite () : void {
            $cache = new ConfigurationCache($this->cachePath);

            $this->assertTrue(!$cache->exists(), "Cache should not exist before the first write().");
        }

        #[Group("Existence")]
        #[Define(
            name: "Exists Returns True After Write",
            description: "exists() returns true once write() has serialised the configuration to disk."
        )]
        public function testExistsReturnsTrueAfterWrite () : void {
            $config = new Configuration();
            $config->set("key", "value");

            $cache = new ConfigurationCache($this->cachePath);
            $cache->write($config);

            $this->assertTrue($cache->exists(), "Cache should exist after write().");
        }

        // ─── GetPath ───────────────────────────────────────────────────────────

        #[Group("GetPath")]
        #[Define(
            name: "GetPath Returns Configured Path",
            description: "getPath() returns the exact path string provided at construction."
        )]
        public function testGetPathReturnsConfiguredPath () : void {
            $cache = new ConfigurationCache($this->cachePath);

            $this->assertTrue($cache->getPath() === $this->cachePath, "getPath() should return the path given to the constructor.");
        }

        // ─── Write Creates Directory ───────────────────────────────────────────

        #[Group("Write")]
        #[Define(
            name: "Write Creates Target Directory Automatically",
            description: "write() creates the target directory recursively if it does not yet exist."
        )]
        public function testWriteCreatesTargetDirectoryAutomatically () : void {
            $deepPath = $this->tmpDir . "/deep/sub/config.cache.php";
            $config = new Configuration();
            $config->set("nested", "dir_created");

            $cache = new ConfigurationCache($deepPath);
            $cache->write($config);

            $this->assertTrue(is_file($deepPath), "write() should create the directory structure and write the cache file.");

            @unlink($deepPath);
            @rmdir(dirname($deepPath));
            @rmdir(dirname(dirname($deepPath)));
        }

        #[Group("Write")]
        #[Define(
            name: "Write Returns Cache Instance For Chaining",
            description: "write() returns the same ConfigurationCache instance to enable fluent chaining."
        )]
        public function testWriteReturnsCacheInstanceForChaining () : void {
            $config = new Configuration();
            $cache = new ConfigurationCache($this->cachePath);

            $result = $cache->write($config);

            $this->assertTrue($result === $cache, "write() should return the same ConfigurationCache instance.");
        }

        // ─── Round-trip ────────────────────────────────────────────────────────

        #[Group("Round-trip")]
        #[Define(
            name: "Write And Load Round-trips Configuration Data",
            description: "A configuration written to cache and then loaded into a fresh instance preserves all key-value pairs."
        )]
        public function testWriteAndLoadRoundtripsConfigurationData () : void {
            $source = new Configuration();
            $source->set("alpha", "first");
            $source->set("beta", 42);
            $source->set("gamma", true);

            $cache = new ConfigurationCache($this->cachePath);
            $cache->write($source);

            $target = new Configuration();
            $cache->load($target);

            $this->assertTrue($target->get("alpha") === "first", "Round-tripped 'alpha' should equal 'first'.");
            $this->assertTrue($target->get("beta") === 42, "Round-tripped 'beta' should equal 42.");
            $this->assertTrue($target->get("gamma") === true, "Round-tripped 'gamma' should equal true.");
        }

        #[Group("Round-trip")]
        #[Define(
            name: "Load Returns Cache Instance For Chaining",
            description: "load() returns the same ConfigurationCache instance to enable fluent chaining."
        )]
        public function testLoadReturnsCacheInstanceForChaining () : void {
            $config = new Configuration();
            $cache = new ConfigurationCache($this->cachePath);
            $cache->write($config);

            $result = $cache->load(new Configuration());

            $this->assertTrue($result === $cache, "load() should return the same ConfigurationCache instance.");
        }

        // ─── Staleness ─────────────────────────────────────────────────────────

        #[Group("Staleness")]
        #[Define(
            name: "IsStale Returns True When Cache Does Not Exist",
            description: "isStale() returns true when the cache file is absent."
        )]
        public function testIsStaleReturnsTrueWhenCacheDoesNotExist () : void {
            $cache = new ConfigurationCache($this->cachePath);

            $this->assertTrue($cache->isStale(), "isStale() should return true when the cache file does not exist.");
        }

        // ─── Error Handling ────────────────────────────────────────────────────

        #[Group("Error Handling")]
        #[Define(
            name: "Load Throws RuntimeException For Missing Cache File",
            description: "load() throws RuntimeException when the configured cache file does not exist on disk."
        )]
        public function testLoadThrowsRuntimeExceptionForMissingCacheFile () : void {
            $thrown = false;

            try {
                $cache = new ConfigurationCache($this->cachePath);
                $cache->load(new Configuration());
            } catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "RuntimeException should be thrown when load() is called with a non-existent cache file.");
        }
    }
?>