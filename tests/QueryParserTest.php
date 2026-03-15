<?php
    /*/
     * Project Name:    Wingman — Cortex — Query Parser Tests
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
    use Wingman\Cortex\QueryParser;

    /**
     * Tests for the `QueryParser` class, verifying query compilation, path
     * resolution, segment matching, delimiter handling, and wildcard expansion.
     * @package Wingman\Cortex\Tests
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class QueryParserTest extends Test {
        /**
         * The parser instance under test.
         * @var QueryParser
         */
        private QueryParser $parser;

        /**
         * Creates a fresh QueryParser with default delimiters before each test.
         */
        public function setUp () : void {
            $this->parser = new QueryParser();
        }

        // ─── Constants ─────────────────────────────────────────────────────────

        #[Group("Constants")]
        #[Define(
            name: "Default Namespace Delimiter Is Colon",
            description: "DEFAULT_NAMESPACE_DELIMITER is ':' as defined by the QueryParser class constant."
        )]
        public function testDefaultNamespaceDelimiterIsColon () : void {
            $this->assertTrue(QueryParser::DEFAULT_NAMESPACE_DELIMITER === ":", "Namespace delimiter constant should be ':'.");
        }

        #[Group("Constants")]
        #[Define(
            name: "Default Segment Delimiter Is Dot",
            description: "DEFAULT_SEGMENT_DELIMITER is '.' as defined by the QueryParser class constant."
        )]
        public function testDefaultSegmentDelimiterIsDot () : void {
            $this->assertTrue(QueryParser::DEFAULT_SEGMENT_DELIMITER === ".", "Segment delimiter constant should be '.'.");
        }

        #[Group("Constants")]
        #[Define(
            name: "Default Wildcard Token Is Asterisk",
            description: "DEFAULT_WILDCARD_TOKEN is '*' as defined by the QueryParser class constant."
        )]
        public function testDefaultWildcardTokenIsAsterisk () : void {
            $this->assertTrue(QueryParser::DEFAULT_WILDCARD_TOKEN === "*", "Wildcard token constant should be '*'.");
        }

        #[Group("Constants")]
        #[Define(
            name: "Default Command Delimiter Is Semicolon",
            description: "DEFAULT_COMMAND_DELIMITER is ';' which separates multiple query expressions."
        )]
        public function testDefaultCommandDelimiterIsSemicolon () : void {
            $this->assertTrue(QueryParser::DEFAULT_COMMAND_DELIMITER === ";", "Command delimiter constant should be ';'.");
        }

        // ─── Getters ───────────────────────────────────────────────────────────

        #[Group("Getters")]
        #[Define(
            name: "GetNamespaceDelimiter Returns Default",
            description: "getNamespaceDelimiter() returns the configured namespace delimiter on a default parser."
        )]
        public function testGetNamespaceDelimiterReturnsDefault () : void {
            $this->assertTrue($this->parser->getNamespaceDelimiter() === QueryParser::DEFAULT_NAMESPACE_DELIMITER, "getNamespaceDelimiter() should return the default delimiter.");
        }

        #[Group("Getters")]
        #[Define(
            name: "GetSegmentDelimiter Returns Default",
            description: "getSegmentDelimiter() returns the configured segment delimiter on a default parser."
        )]
        public function testGetSegmentDelimiterReturnsDefault () : void {
            $this->assertTrue($this->parser->getSegmentDelimiter() === QueryParser::DEFAULT_SEGMENT_DELIMITER, "getSegmentDelimiter() should return the default delimiter.");
        }

        #[Group("Getters")]
        #[Define(
            name: "GetWildcardToken Returns Default",
            description: "getWildcardToken() returns the configured wildcard token on a default parser."
        )]
        public function testGetWildcardTokenReturnsDefault () : void {
            $this->assertTrue($this->parser->getWildcardToken() === QueryParser::DEFAULT_WILDCARD_TOKEN, "getWildcardToken() should return the default token.");
        }

        // ─── Parse ─────────────────────────────────────────────────────────────

        #[Group("Parse")]
        #[Define(
            name: "Parse Bare Key Into Default Namespace",
            description: "A bare key with no namespace delimiter is parsed into the default namespace."
        )]
        public function testParseBareKeyIntoDefaultNamespace () : void {
            $result = $this->parser->parse("key");

            $this->assertTrue(is_array($result), "parse() should return an array.");

            $envName = QueryParser::DEFAULT_ENVIRONMENT_NAME;
            $nsParsed = array_key_exists($envName, $result) && count($result[$envName]) > 0;

            $this->assertTrue($nsParsed, "parse() should place a bare key under the default environment.");
        }

        #[Group("Parse")]
        #[Define(
            name: "Parse Extracts Explicit Namespace",
            description: "A query of the form 'ns:key' causes namespace 'ns' to appear in the parsed output."
        )]
        public function testParseExtractsExplicitNamespace () : void {
            $result = $this->parser->parse("myns:somekey");

            $envName = QueryParser::DEFAULT_ENVIRONMENT_NAME;
            $nsFound = isset($result[$envName]["myns"]);

            $this->assertTrue($nsFound, "parse() should extract the explicit namespace 'myns' from the query.");
        }

        #[Group("Parse")]
        #[Define(
            name: "Parse Splits Multiple Commands By Semicolon",
            description: "A query with semicolon-separated expressions produces entries from both sub-expressions."
        )]
        public function testParseSplitsMultipleCommandsBySemicolon () : void {
            $result = $this->parser->parse("ns1:alpha;ns2:beta");

            $envName = QueryParser::DEFAULT_ENVIRONMENT_NAME;
            $hasNs1 = isset($result[$envName]["ns1"]);
            $hasNs2 = isset($result[$envName]["ns2"]);

            $this->assertTrue($hasNs1 && $hasNs2, "parse() should parse both namespaces from a semicolon-separated query.");
        }

        // ─── Compile ───────────────────────────────────────────────────────────

        #[Group("Compile")]
        #[Define(
            name: "Compile Returns Normalised Array",
            description: "compile() processes a query through parse() then normalise() and returns a normalised array."
        )]
        public function testCompileReturnsNormalisedArray () : void {
            $result = $this->parser->compile("ns:key");

            $this->assertTrue(is_array($result), "compile() should return an array.");
            $this->assertTrue(!empty($result), "compile() result should not be empty for a valid query.");
        }

        // ─── MatchSegments ─────────────────────────────────────────────────────

        #[Group("MatchSegments")]
        #[Define(
            name: "MatchSegments Returns True For Identical Paths",
            description: "matchSegments() returns true when the pattern and dataPath arrays are identical."
        )]
        public function testMatchSegmentsReturnsTrueForIdenticalPaths () : void {
            $result = $this->parser->matchSegments(["db", "host"], ["db", "host"]);

            $this->assertTrue($result, "matchSegments() should return true for identical paths.");
        }

        #[Group("MatchSegments")]
        #[Define(
            name: "MatchSegments Returns True For Wildcard Pattern",
            description: "matchSegments() returns true when the pattern contains a wildcard that matches the data path."
        )]
        public function testMatchSegmentsReturnsTrueForWildcardPattern () : void {
            $result = $this->parser->matchSegments(["db", "*"], ["db", "host"]);

            $this->assertTrue($result, "Wildcard segment in pattern should match any value in the corresponding data path position.");
        }

        #[Group("MatchSegments")]
        #[Define(
            name: "MatchSegments Returns False For Different Paths",
            description: "matchSegments() returns false when the pattern and dataPath arrays differ in content."
        )]
        public function testMatchSegmentsReturnsFalseForDifferentPaths () : void {
            $result = $this->parser->matchSegments(["db", "port"], ["db", "host"]);

            $this->assertTrue(!$result, "matchSegments() should return false when paths differ.");
        }

        #[Group("MatchSegments")]
        #[Define(
            name: "MatchSegments Returns False For Different Lengths",
            description: "matchSegments() returns false when the pattern and dataPath arrays have different lengths without a top-level wildcard."
        )]
        public function testMatchSegmentsReturnsFalseForDifferentLengths () : void {
            $result = $this->parser->matchSegments(["db"], ["db", "host"]);

            $this->assertTrue(!$result, "matchSegments() should return false when pattern and dataPath have different lengths.");
        }

        // ─── Setters (fluent) ──────────────────────────────────────────────────

        #[Group("Setters")]
        #[Define(
            name: "SetNamespaceDelimiter Changes Delimiter",
            description: "setNamespaceDelimiter() updates the delimiter returned by getNamespaceDelimiter()."
        )]
        public function testSetNamespaceDelimiterChangesDelimiter () : void {
            $this->parser->setNamespaceDelimiter("|");

            $this->assertTrue($this->parser->getNamespaceDelimiter() === "|", "setNamespaceDelimiter() should update the namespace delimiter.");
        }

        #[Group("Setters")]
        #[Define(
            name: "Setter Returns Same Instance For Fluent Chaining",
            description: "setNamespaceDelimiter() returns the same QueryParser instance enabling method chaining."
        )]
        public function testSetterReturnsSameInstanceForFluentChaining () : void {
            $result = $this->parser->setNamespaceDelimiter("|");

            $this->assertTrue($result === $this->parser, "Setter should return the same instance for fluent chaining.");
        }
    }
?>