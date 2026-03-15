# Loaders & Parsers

Cortex can load configuration data from files, directories, environment
variables, and inline arrays. All loading is routed through the `CanImport`
trait methods on `Configuration`.

---

## `import()`

```php
$config->import(string|array $sources, array $options = [])
```

Loads one or more source files or directories and merges the result into the
store. Options:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `"parent"` | `string\|null` | `null` | Parent path hint passed to the loader for relative resolution. |
| `"mapDirectoryStructure"` | `bool\|null` | `null` | When loading a directory, nest file data under the filename as a key. `null` inherits the loader default (`true`). Pass `false` to merge all file data into the current level. |
| `"parserOptions"` | `array` | `[]` | Parser-specific options forwarded to the file parser. |
| `"flat"` | `bool` | `false` | Merge using `mergeFlat()` instead of `merge()`. |
| `"const"` | `bool` | `false` | Lock every loaded scalar key as a constant after merging. |
| `"snapshot"` | `bool` | `false` | Call `snapshot()` after all sources have been loaded, creating a restore-point for `reset()`. |

```php
// A single file.
$config->import(__DIR__ . "/config/database.php");

// Multiple files in one call.
$config->import([
    __DIR__ . "/config/app.php",
    __DIR__ . "/config/mail.php",
]);

// A directory — every file inside is loaded.
$config->import(__DIR__ . "/config/");

// Flat merge — loads into the implicit namespace as dot-notation keys.
$config->import(__DIR__ . "/config/flat.json", ["flat" => true]);

// Immutable environment variables.
$config->import(__DIR__ . "/.env", ["const" => true]);
```

---

## `importLayered()`

```php
$config->importLayered(string|array $sources, ?string $env = null, array $options = [])
```

Loads base source(s) then auto-discovers and deep-merges an environment-specific
override on top.

- **File source** — loads `database.php`, then merges `database.production.php`
  from the same directory if it exists.
- **Directory source** — loads the base directory, then merges the `production/`
  subdirectory if it exists.

All `import()` options are forwarded unchanged, including `"snapshot"`, `"flat"`,
`"const"`, `"parserOptions"`, `"mapDirectoryStructure"`, and `"parent"`.

```php
$env = $_ENV["APP_ENV"] ?? "production";

$config->importLayered(__DIR__ . "/config/database.php", $env, [
    "snapshot" => true,
]);
// Loads database.php, then database.production.php if present, then snapshots.
```

---

## `mapEnvKeys()`

```php
$config->mapEnvKeys(string $prefix = "", string $separator = "_")
```

Reads all variables from `$_ENV` and `getenv()` whose names start with `$prefix`,
strips the prefix, lower-cases the remainder, replaces `$separator` with `.`, and
merges the result via `mergeFlat()`.

```php
// $_ENV: APP_DB_HOST=localhost, APP_DB_PORT=5432, PATH=...
$config->mapEnvKeys("APP_");
// Merges: ["db.host" => "localhost", "db.port" => "5432"]
```

Pass an empty string to map every environment variable without filtering.

---

## `registerNamespace()`

```php
$config->registerNamespace(string $namespace, string|array $source, array $options = [])
```

Registers a deferred load source. The source is **not** imported immediately;
it is loaded the first time any code accesses a key within `$namespace`.

```php
$config->registerNamespace("analytics", __DIR__ . "/config/analytics.php");

// Nothing loaded yet.
$config->has("analytics:enabled");   // triggers load now
$config->get("analytics:events");    // already in memory
```

If the namespace bucket already exists the registration is silently ignored.
`$options` is forwarded to `import()` when the load fires.

---

## Supported file formats

| Extension | Parser | Notes |
|-----------|--------|-------|
| `.php` | Native `require` | Must `return` an associative array. |
| `.json` | `json_decode` | Must be a JSON object at the root. |
| `.env` | `EnvParser` | Supports quoted values, multi-line (`\`-continuation), `export` keyword, `#` comments, BOM. Automatically calls `putenv()`, `$_ENV`, and `$_SERVER` on parse. |
| `.ini` | `IniParser` | Uses `INI_SCANNER_TYPED` by default — numeric and boolean literals are cast. Sections become nested arrays. |
| `.yaml` / `.yml` | `YamlParser` | Requires `symfony/yaml`. Throws `MissingDependencyException` if absent. Accepts a `"flags"` parser option forwarded to `Yaml::parseFile()`. |
| `.toml` | `TomlParser` | Optional bridge. Throws `MissingDependencyException` if the underlying library is absent. |
| `.xml` | `XmlParser` | Optional bridge for Spring-style or legacy XML configuration. |

---

## Parser options

Parser-specific options are passed under the `"parserOptions"` key:

```php
// INI: disable typed scanning.
$config->import("settings.ini", [
    "parserOptions" => ["mode" => INI_SCANNER_RAW],
]);

// YAML: parse with a specific flag.
$config->import("services.yaml", [
    "parserOptions" => ["flags" => Yaml::PARSE_DATETIME],
]);
```

---

## Writing a custom parser

Implement `Wingman\Cortex\Parsers\ParserInterface`:

```php
use Wingman\Cortex\Parsers\ParserInterface;

class MyParser implements ParserInterface
{
    /**
     * Parses the given file and returns an associative array of configuration data.
     * @param string $path    Absolute path to the file to parse.
     * @param array  $options Arbitrary parser-specific options.
     * @return array<string, mixed> Parsed configuration as a nested associative array.
     */
    public function parse (string $path, array $options = []) : array
    {
        // … read $path, return an associative array …
    }
}
```

Register the parser with the `Loader` before importing:

```php
$config->getLoader()->registerParser("myext", new MyParser());
$config->import(__DIR__ . "/config/settings.myext");
```

> **Note:** `getLoader()` is not currently exposed as a public method on
> `Configuration`. Retrieve the loader by subclassing `Configuration` or by
> passing a pre-configured `Loader` instance to the constructor if your use case
> requires a custom parser. This API surface is tracked for improvement.

---

## Directory structure mapping

When a directory is imported, Cortex walks its contents recursively and nests
each file's data under a key derived from its relative path:

```
config/
  server.php        → ["server" => [...]]
  db/
    primary.php     → ["db" => ["primary" => [...]]]
    replica.php     → ["db" => ["replica" => [...]]]
```

Pass `"mapDirectoryStructure" => false` to flatten all file data directly into
the implicit namespace without nesting:

```php
$config->import(__DIR__ . "/config/", ["mapDirectoryStructure" => false]);
```
