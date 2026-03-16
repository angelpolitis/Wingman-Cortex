# Wingman — Cortex

Cortex is the configuration engine for the Wingman framework. It provides a
namespace-scoped hierarchical key-value store with lazy loading, string
interpolation, schema validation, object hydration, change observation, and a
production-grade opcode-cacheable persistence layer.

---

## Requirements

- PHP 8.2 or later
- Optional: `symfony/yaml` for YAML support
- Optional: `yosymfony/toml` for YAML support
- Optional: `wingman/verix` for full DSL validation expressions

---

## Installation

Cortex ships with its own classloader. Include `autoload.php` in your bootstrap:

```php
require_once __DIR__ . '/path/to/cortex/autoload.php';
```

---

## Quick-start

```php
use Wingman\Cortex\Configuration;

$config = new Configuration("app");

// Load a PHP or JSON configuration file.
$config->import(__DIR__ . "/config/app.php");

// Read and write values.
$config->set("app.name", "Wingman");
$name = $config->get("app.name");           // "Wingman"
$name = $config->app->name;                 // identical — property-chain syntax

// Typed accessors.
$port = $config->getInt("server.port", 8080);
$debug = $config->getBool("app.debug", false);
$tags = $config->getArray("app.tags", []);

// Namespace scope.
foreach ($config("server") as $key => $value) {
    echo "$key = $value\n";
}
```

---

## Key Concepts

| Concept | Description |
|---------|-------------|
| **Namespace** | A top-level partition of the store (e.g. `server`, `db`). Accessed via `ns:key` syntax or `setImplicitNamespace()`. |
| **Segment** | A dot-delimited path within a namespace (e.g. `db.connections.mysql`). |
| **Implicit namespace** | The namespace assumed for bare keys. Defaults to `"/"`. |
| **Interpolation** | `@{key}` tokens within string values are resolved at read time. |
| **Lazy namespace** | A namespace registered via `registerNamespace()` is loaded only on first access. |
| **Snapshot / reset** | `snapshot()` captures state; `reset()` rolls back to that point. |
| **Freeze** | `freeze()` / `freezeNamespace()` makes the store or a partition read-only permanently. |
| **Constants** | `setConst()` locks a key so that `set()` / `mergeFlat()` cannot overwrite it. |

---

## Documentation

| File | Contents |
|------|----------|
| [Configuration API](docs/Configuration.md) | Complete method reference |
| [DSL Reference](docs/DSL.md) | Key syntax, wildcard queries, interpolation |
| [Loaders & Parsers](docs/Loaders.md) | `import()`, `importLayered()`, file formats, custom parsers |
| [Schema & Validation](docs/Schema.md) | `ConfigurationSchema`, assertion, Verix expressions |
| [Object Hydration](docs/Hydration.md) | `ObjectHydrator`, all PHP attributes |
| [Production Cache](docs/Cache.md) | `ConfigurationCache`, boot-sequence patterns |

---

## Licence

This project is licensed under the **Mozilla Public License 2.0 (MPL 2.0)**.

Wingman Cortex is part of the **Wingman Framework**, Copyright (c) 2023–2026 Angel Politis.

For the full licence text, please see the [LICENSE](LICENSE) file.
