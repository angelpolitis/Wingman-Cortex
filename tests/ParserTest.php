<?php
    /*/
     * Project Name:    Wingman — Cortex — Parser Tests
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
    use Wingman\Cortex\Parsers\EnvParser;
    use Wingman\Cortex\Parsers\IniParser;
    use Wingman\Cortex\Interfaces\ParserInterface;

    /**
     * Tests for the file-format parsers (`IniParser` and `EnvParser`),
     * verifying correct parsing of typed values, sections, quoted strings,
     * comments, and error handling for invalid or missing files.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ParserTest extends Test {
        /**
         * Tracks temp files created during tests so they can be cleaned up.
         * @var list<string>
         */
        private array $tempFiles = [];

        /**
         * Removes all temporary files created during each test.
         */
        public function tearDown () : void {
            foreach ($this->tempFiles as $path) {
                @unlink($path);
            }

            $this->tempFiles = [];
        }

        // ─── Shared Helper ──────────────────────────────────────────────────────

        /**
         * Writes content to a unique temporary file and returns its path.
         * @param string $content The text to write to the file.
         * @return string The absolute path to the written temp file.
         */
        private function writeTempFile (string $content) : string {
            $path = tempnam(sys_get_temp_dir(), "cortex_parser_");
            file_put_contents($path, $content);

            $this->tempFiles[] = $path;

            return $path;
        }

        // ─── IniParser Interface ───────────────────────────────────────────────

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Implements ParserInterface",
            description: "IniParser must implement ParserInterface so that the Loader can accept it as a registered parser."
        )]
        public function testIniParserImplementsParserInterface () : void {
            $parser = new IniParser();

            $this->assertTrue($parser instanceof ParserInterface, "IniParser should implement ParserInterface.");
        }

        // ─── IniParser Parsing ─────────────────────────────────────────────────

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Parses Flat Key-Value Pairs",
            description: "IniParser correctly parses a well-formed .ini file with simple key = value pairs into a flat associative array."
        )]
        public function testIniParserParsesFlatKeyValuePairs () : void {
            $path = $this->writeTempFile("[main]\napp_name = Cortex\napp_version = 2");
            $parser = new IniParser();
            $result = $parser->import($path);

            $this->assertTrue(isset($result["main"]["app_name"]), "Parsed INI should contain the 'main.app_name' key.");
            $this->assertTrue($result["main"]["app_name"] === "Cortex", "app_name should equal 'Cortex'.");
        }

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Parses Sections As Nested Arrays By Default",
            description: "IniParser uses sections=true by default, turning [section] headers into nested array keys."
        )]
        public function testIniParserParsesSectionsAsNestedArraysByDefault () : void {
            $path = $this->writeTempFile("[database]\nhost = localhost\nport = 3306");
            $parser = new IniParser();
            $result = $parser->import($path);

            $this->assertTrue(isset($result["database"]), "Section 'database' should be a key in the parsed array.");
            $this->assertTrue(is_array($result["database"]), "Section value should be an array.");
            $this->assertTrue(($result["database"]["host"] ?? null) === "localhost", "Nested 'host' key should equal 'localhost'.");
        }

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Applies Typed Scanner By Default",
            description: "IniParser uses INI_SCANNER_TYPED by default, casting numeric literals to int and boolean literals to bool."
        )]
        public function testIniParserAppliesTypedScannerByDefault () : void {
            $path = $this->writeTempFile("[settings]\nport = 8080\nenabled = true");
            $parser = new IniParser();
            $result = $parser->import($path);

            $this->assertTrue(($result["settings"]["port"] ?? null) === 8080, "Numeric INI value should be cast to int with TYPED scanner.");
            $this->assertTrue(($result["settings"]["enabled"] ?? null) === true, "Boolean INI value 'true' should be cast to bool true.");
        }

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Flattens Sections When Sections Option Is False",
            description: "When the 'sections' option is false, section headers are ignored and all keys are placed in a flat array."
        )]
        public function testIniParserFlattensSectionsWhenSectionsOptionIsFalse () : void {
            $path = $this->writeTempFile("[block]\nkey = flat_value");
            $parser = new IniParser();
            $result = $parser->import($path, ["sections" => false]);

            $this->assertTrue(isset($result["key"]), "Key should be at the top level when sections option is false.");
            $this->assertTrue($result["key"] === "flat_value", "Flattened key should equal 'flat_value'.");
        }

        #[Group("IniParser")]
        #[Define(
            name: "IniParser Throws RuntimeException For Malformed File",
            description: "import() throws RuntimeException when the file does not exist or is an invalid path."
        )]
        public function testIniParserThrowsRuntimeExceptionForMalformedFile () : void {
            $thrown = false;

            try {
                $parser = new IniParser();
                $parser->import("/non/existent/path/file.ini");
            } catch (\Exception $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "An exception should be thrown when IniParser::import() receives a non-existent file path.");
        }

        // ─── EnvParser Interface ───────────────────────────────────────────────

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Implements ParserInterface",
            description: "EnvParser must implement ParserInterface so the Loader accepts it as a registered parser."
        )]
        public function testEnvParserImplementsParserInterface () : void {
            $parser = new EnvParser();

            $this->assertTrue($parser instanceof ParserInterface, "EnvParser should implement ParserInterface.");
        }

        // ─── EnvParser Parsing ─────────────────────────────────────────────────

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Parses Simple KEY=value Lines",
            description: "EnvParser correctly reads a .env file where each line contains a plain KEY=value assignment."
        )]
        public function testEnvParserParsesSimpleKeyValueLines () : void {
            $path = $this->writeTempFile("APP_ENV=production\nDEBUG=false\n");
            $parser = new EnvParser();
            $result = $parser->import($path);

            $this->assertTrue(isset($result["APP_ENV"]), "APP_ENV should be present in the parsed result.");
            $this->assertTrue($result["APP_ENV"] === "production", "APP_ENV should equal 'production'.");
        }

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Skips Comment Lines",
            description: "Lines beginning with '#' in a .env file are treated as comments and are not included in the result."
        )]
        public function testEnvParserSkipsCommentLines () : void {
            $path = $this->writeTempFile("# This is a comment\nVALID_KEY=valid_value\n");
            $parser = new EnvParser();
            $result = $parser->import($path);

            $this->assertTrue(!isset($result["# This is a comment"]), "Comment lines should not appear as keys.");
            $this->assertTrue(($result["VALID_KEY"] ?? null) === "valid_value", "Non-comment keys should still be parsed.");
        }

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Handles Double-Quoted Values",
            description: "Values wrapped in double quotes have their surrounding quotes stripped and standard escape sequences processed."
        )]
        public function testEnvParserHandlesDoubleQuotedValues () : void {
            $path = $this->writeTempFile("GREETING=\"Hello World\"\n");
            $parser = new EnvParser();
            $result = $parser->import($path);

            $this->assertTrue(($result["GREETING"] ?? null) === "Hello World", "Double-quoted value should be parsed without the surrounding quotes.");
        }

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Handles Single-Quoted Literal Values",
            description: "Values wrapped in single quotes are treated as completely literal with no escape processing."
        )]
        public function testEnvParserHandlesSingleQuotedLiteralValues () : void {
            $path = $this->writeTempFile("LITERAL='raw\\nno-escape'\n");
            $parser = new EnvParser();
            $result = $parser->import($path);

            $this->assertTrue(($result["LITERAL"] ?? null) === "raw\\nno-escape", "Single-quoted value should be treated as a literal string with no escape processing.");
        }

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Handles Export Keyword Prefix",
            description: "Lines starting with 'export KEY=value' are parsed as if 'export' were absent; the key retains only the variable name."
        )]
        public function testEnvParserHandlesExportKeywordPrefix () : void {
            $path = $this->writeTempFile("export SERVICE_PORT=9090\n");
            $parser = new EnvParser();
            $result = $parser->import($path);

            $this->assertTrue(isset($result["SERVICE_PORT"]), "Export-prefixed key should be parsed as SERVICE_PORT without the export keyword.");
            $this->assertTrue($result["SERVICE_PORT"] === "9090", "Export-prefixed value should be '9090'.");
        }

        #[Group("EnvParser")]
        #[Define(
            name: "EnvParser Throws RuntimeException For Non-Existent File",
            description: "import() throws RuntimeException when the requested .env file does not exist on the filesystem."
        )]
        public function testEnvParserThrowsRuntimeExceptionForNonExistentFile () : void {
            $thrown = false;

            try {
                $parser = new EnvParser();
                $parser->import("/non/existent/path/.env");
            } catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "RuntimeException should be thrown when EnvParser::import() receives a non-existent file path.");
        }
    }
?>