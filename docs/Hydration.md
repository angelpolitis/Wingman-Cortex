# Object Hydration

`ObjectHydrator` reads `#[Configurable]`-annotated properties from a class and
populates them from a `Configuration` instance (or a flat array of key-value
pairs). It handles type coercion, schema validation, value transformation,
deprecation warnings, and snapshot-based rollback.

---

## Basic usage

```php
use Wingman\Cortex\ObjectHydrator;

class MailSettings
{
    #[Configurable("mail.host", schema: "string")]
    protected string $host;

    #[Configurable("mail.port", schema: "int<min=1, max=65535>", default: 587)]
    protected int $port;
}

$config = new Configuration();
$config->import(__DIR__ . "/config/mail.php");

ObjectHydrator::hydrate(MailSettings::class, $config);
```

When `$target` is a class name a new instance is created and returned (wrapped in
the anonymous `Configuration` used internally). When it is an existing object that
object is mutated in place.

### Map mode

Instead of reading `#[Configurable]` attributes, the caller may supply an
explicit `$map` of `propertyName => configKey` pairs:

```php
ObjectHydrator::hydrate($object, $config, [
    "host" => "mail.server.host",
    "port" => "mail.server.port",
]);
```

### Strict mode

When `$strict = true` any property whose resolved key is absent from the
configuration (and has no declared default) throws an
`UndefinedVariableException`. In non-strict mode (the default) the property is
left at its declared PHP default or uninitialised.

---

## `Configuration::hydrate()` proxy

```php
// Equivalent to ObjectHydrator::hydrate() — convenience alias on Configuration.
$config->hydrate(MailSettings::class);
```

---

## Capture and restore

`ObjectHydrator::capture()` records the current values of all
`#[Configurable]` properties into a named slot inside the configuration:

```php
ObjectHydrator::capture($object, "before-update", $config);
// … mutate $config …
ObjectHydrator::restore($object, "before-update", $config);   // rolls back
```

The `Configuration` proxy methods `captureObject()` and `restoreObject()` forward
to the static class.

---

## Deriving a schema

```php
$schema = ObjectHydrator::getSchemaFromClass(MailSettings::class);
// Returns a ConfigurationSchema pre-populated with every #[Configurable] rule.
$schema->assert($config);
```

---

## Sensitive-key redaction

```php
$keys = ObjectHydrator::getSensitiveKeys(DatabaseSettings::class);
// Returns the config-key strings of all #[Sensitive]-annotated properties.

$safe = $config->toSafeArray(DatabaseSettings::class);
// Returns toArray() with all sensitive values replaced by "***".
```

---

## PHP Attributes

All Cortex attributes live in the `Wingman\Cortex\Attributes` namespace.

---

### `#[Configurable]`

**Target:** Property

The core annotation. Declares that the property is backed by a configuration key.

```php
#[Configurable(
    key: "db.port",
    description: "The database port.",
    schema: "int<min=1, max=65535>",
    default: 3306,
)]
protected int $port;
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | `string` | Yes | The configuration key (may be bare, namespaced, or fully qualified). |
| `description` | `string\|null` | No | Human-readable description. Surfaced by `getSchemaFromClass()`. |
| `schema` | `string\|null` | No | Verix DSL expression validated during hydration. |
| `default` | `mixed` | No | Value used when the key is absent. Omit entirely (do not pass `null`) to mark the property as required. |

---

### `#[ConfigGroup]`

**Target:** Class

Prepends a shared prefix to every `#[Configurable]` key in the class and all
its children.

```php
#[ConfigGroup("mail")]
class MailSettings
{
    #[Configurable("host")]     // resolved as "mail.host"
    protected string $host;

    #[Configurable("port")]     // resolved as "mail.port"
    protected int $port;
}
```

---

### `#[ConfigSource]`

**Target:** Class (repeatable)

Declares one or more configuration files to be automatically imported before
hydration begins (attribute mode only).

```php
#[ConfigSource(__DIR__ . "/config/mail.php")]
#[ConfigSource(__DIR__ . "/config/mail.local.php")]
class MailSettings { ... }
```

---

### `#[Constant]`

**Target:** Property

Locks the hydrated key with `setConst()` immediately after the value is written,
preventing subsequent programmatic changes.

```php
#[Configurable("app.env", schema: "string")]
#[Constant]
protected string $env;
```

---

### `#[Deprecated]`

**Target:** Property

Emits `E_USER_DEPRECATED` during hydration. Hydration still completes normally.

```php
#[Configurable("db.server", schema: "string")]
#[Deprecated(since: "2.0", replacement: "db.host")]
protected string $server;
```

| Parameter | Description |
|-----------|-------------|
| `since` | Version string when the key was deprecated. |
| `replacement` | The replacement key callers should use. |

---

### `#[Environment]`

**Target:** Class or Property

Targets a specific named `Configuration` instance from the registry instead of
the one passed to `hydrate()`. A class-level annotation sets the default for all
properties; a property-level annotation overrides it for that property only.
Falls back to the `$config` argument if the named instance is not registered.

```php
#[Environment("production")]
class ProductionSettings { ... }
```

---

### `#[NoInterpolate]`

**Target:** Property

Calls `getRaw()` instead of `get()` for the annotated property, preserving
`@{...}` tokens as literal strings.

```php
#[Configurable("mail.subject_template", schema: "string")]
#[NoInterpolate]
protected string $subjectTemplate;
// Value: "Hello @{user.name}" — tokens are not expanded.
```

---

### `#[SchemaClass]`

**Target:** Class

Points `ObjectHydrator::getSchemaFromClass()` to a concrete `ConfigurationSchema`
subclass that is used as the base schema. Property-derived rules are overlaid on
top, enabling cross-field constraints and shared schema reuse.

```php
#[SchemaClass(MyBaseSchema::class)]
class DatabaseSettings { ... }
```

---

### `#[Sensitive]`

**Target:** Property

Marks the configuration key as sensitive. `toSafeArray()` and
`getSensitiveKeys()` use this to redact values in serialised output.

```php
#[Configurable("db.password", schema: "string")]
#[Sensitive]
protected string $password;
```

---

### `#[Transform]`

**Target:** Property (repeatable)

Applies a post-coercion transformation to the resolved value before it is
assigned. Multiple `#[Transform]` attributes are applied in declaration order.

```php
#[Configurable("app.name", schema: "string")]
#[Transform("strtolower")]
#[Transform("trim")]
protected string $appName;

// With a static method:
#[Configurable("app.dsn", schema: "string")]
#[Transform("Dsn::from")]
protected Dsn $dsn;
```

`$transformer` must be a callable-compatible string accepted by `is_callable()`.
