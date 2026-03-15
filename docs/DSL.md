# DSL Reference

Cortex uses a small but expressive domain-specific language for both key
addressing and full-text queries. The same syntax applies everywhere: `get()`,
`set()`, `has()`, `unset()`, `search()`, `except()`, and interpolation tokens.

---

## Key anatomy

A fully-qualified Cortex key has the form:

```
[env::]  [namespace:]  segment[.segment...]
```

Every part except the final segment path is optional.

| Part | Delimiter | Example |
|------|-----------|---------|
| Environment | `::` | `production::` |
| Namespace | `:` | `db:` |
| Segment path | `.` | `connections.mysql.host` |

### Examples

| Key string | Environment | Namespace | Path |
|------------|-------------|-----------|------|
| `host` | _(implicit)_ | _(implicit)_ | `host` |
| `db:host` | _(implicit)_ | `db` | `host` |
| `db:connections.mysql` | _(implicit)_ | `db` | `connections.mysql` |
| `production::db:host` | `production` | `db` | `host` |

When no namespace is given the **implicit namespace** is used. It defaults to
`"/"` and can be changed per-instance with `setImplicitNamespace()`.

When no environment is given the **name of the current `Configuration` instance**
is assumed. Accessing a key whose environment does not match the current
configuration throws `AccessDeniedException`.

---

## Delimiters

All delimiters are configurable per `Configuration` instance:

| Role | Default | Setter |
|------|---------|--------|
| Segment (path) | `.` | `setPathDelimiter()` |
| Namespace | `:` | `setNamespaceDelimiter()` |

---

## Wildcard queries

The `*` token matches any single segment when used in a query passed to
`search()`, `except()`, or via `Scope`.

```php
// All keys directly under "db".
$config->search("db:*");

// Any key two levels deep under "db".
$config->search("db:*.host");

// All keys in every namespace.
$config->search("*:*");
```

---

## Group syntax

Multiple values at the same path prefix can be selected in one expression using
bracket groups:

```php
// Equivalent to fetching db:host and db:port in one pass.
$config->search("db:[host,port]");

// Nested groups.
$config->search("server:[timeout,limits.[min,max]]");
```

---

## `search()` vs `except()`

Both accept an array of query strings.

```php
// Returns a new Configuration containing only matched keys.
$subset = $config->search("db:*");

// Returns a plain array with matched keys removed.
$safe = $config->except(["db:password", "mail:*"]);
```

---

## String interpolation

Any string value stored in the configuration may reference other keys with
`@{...}` tokens. Tokens are resolved recursively at read time (via `get()`).
`getRaw()` returns values verbatim without resolution.

### Syntax

```
@{key}
@{namespace:key}
@{environment::namespace:key}
```

### Examples

```php
$config->set("base_url", "https://example.com");
$config->set("cdn_url",  "@{base_url}/cdn");

$config->get("cdn_url");   // "https://example.com/cdn"
```

Interpolation is recursive — a token can reference a key whose value itself
contains tokens. A `CircularReferenceException` is thrown if a cycle is detected.

### Post-interpolation expressions

After token substitution, the resulting string may contain safe arithmetic or
function calls that Cortex will evaluate:

```php
$config->set("timeout_ms", "@{timeout} * 1000");
$config->set("label",       "strtoupper('@{app.name}')");
```

Only a whitelist of PHP functions is permitted (no `eval()` of arbitrary code).
Any expression that cannot be evaluated safely is returned as-is.

### Opting out

Mark a key with `#[NoInterpolate]` during object hydration, or call `getRaw()`
to retrieve the raw token string without resolution. See
[Hydration](Hydration.md#nointerpolate).

---

## UnitEnum keys

Any `UnitEnum` case may be used wherever a key string is accepted:

```php
enum ConfigKey: string {
    case DbHost = "db:host";
    case DbPort = "db:port";
}

$config->get(ConfigKey::DbHost);
$config->set(ConfigKey::DbPort, 5432);
```

---

## `Variable` objects

`Registry::normaliseKeyWithCache()` parses any key string or `UnitEnum` into a
`Variable` value object containing the resolved namespace, environment, and
segment path. `Variable` objects may be passed directly to any method that accepts
a key:

```php
use Wingman\Cortex\Variable;

$var = new Variable("host", "db", "production");
$config->get($var);
```
