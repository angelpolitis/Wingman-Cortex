# Configuration API Reference

`Wingman\Cortex\Configuration` is the central class of the Cortex package. Every
operation — reading, writing, importing, exporting, and observing — is exposed
through its interface.

---

## Construction

```php
new Configuration(?string $name = null, ?CorvusEmitter $emitter = null)
```

- `$name` — registers the instance in the global `ConfigurationRegistry` under
  this name. Pass `null` for a short-lived anonymous instance.
- `$emitter` — optional pre-configured Corvus emitter. Useful when sharing a
  single event bus across several configurations.

Calling `new Configuration("app")` twice with the same name throws a
`ContainerOverwriteException`. Use `Configuration::find("app")` to retrieve an
already-registered instance.

---

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `DEFAULT_NAME` | `"main"` | Fallback name used by `ConfigurationRegistry` when `null` is passed. |
| `DEFAULT_NAMESPACE` | `"/"` | The built-in implicit namespace applied to bare keys. |

---

## Reading

### `get(key, default = null) : mixed`

Returns the value associated with `$key`, with all `@{...}` interpolation tokens
resolved. When the key is absent, `$default` is returned.

```php
$config->get("db.host");
$config->get("db.port", 3306);
$config->get("cache:ttl");           // explicit namespace "cache"
```

### `getRaw(key, default = null) : mixed`

Identical to `get()` but skips interpolation. Returns the stored value verbatim,
including any `@{...}` tokens.

### `has(key) : bool`

Returns `true` if the key exists in the store (including within lazy-loaded
namespaces, which are loaded on demand).

### `isSet(key) : bool`

Returns `true` if the key exists and its value is not `null`.

### `isConst(key) : bool`

Returns `true` if the key has been locked via `setConst()`.

### Property-chain syntax

`$config->property` calls `get("property")` and returns a `Scope` when the value
is an array, or the scalar value directly.

```php
$config->db->host    // equivalent to $config->get("db.host") after entering ns
$config->get("db")->host  // same route through Scope
```

### Typed accessors

| Method | Throws on wrong type |
|--------|---------------------|
| `getArray(key, default = null) : array` | `TypeMismatchException` |
| `getBool(key, default = null) : bool` | `TypeMismatchException` |
| `getFloat(key, default = null) : float` | `TypeMismatchException` |
| `getInt(key, default = null) : int` | `TypeMismatchException` |
| `getString(key, default = null) : string` | `TypeMismatchException` |

### Batch retrieval

Both methods accept keys in any form supported by the DSL, including full
namespace notation (`ns:key.sub`) and environment notation (`env::ns:key`).

```php
// Full DSL notation — mix namespaces freely.
$config->getMany(["db.host", "cache:ttl", "mail:smtp.port"]);

// Bare keys from the implicit namespace (no ns: prefix).
$config->getMany(["host", "port", "name"]);

// only() strips the namespace/environment prefix from output keys.
// ns:host and bare host both become just "host" in the result map.
$config->only(["db:host", "db:port", "db:name"]);
```

---

## Writing

### `set(key, value, force = false) : static`

Stores a value. Throws:
- `FrozenConfigurationException` if the configuration or the target namespace is
  frozen.
- `ConstantViolationException` if the key has been locked with `setConst()`.

The `$force` flag bypasses the constant check and is reserved for internal use
by the loader pipeline; callers should not rely on it.

### `setConst(key, value, expression = null) : static`

Stores a value and locks the key permanently. Subsequent `set()` or `mergeFlat()`
calls on the same key throw `ConstantViolationException`. When `$expression` is
given the value is validated against the Verix DSL before storing.

### `unset(key) : static`

Removes a key. Respects both the global freeze and per-namespace freeze. Throws
`ConstantViolationException` if the key is a constant.

---

## Merging

### `merge(array ...$maps) : static`

Deep-merges one or more nested arrays into the store. The active `MergeStrategy`
controls how array branches are combined:

| Strategy | Behaviour |
|----------|-----------|
| `MergeStrategy::REPLACE` | `array_replace_recursive` (default) |
| `MergeStrategy::APPEND` | Numeric sub-arrays are appended instead of replaced |
| `MergeStrategy::DEEP` | Recursive union, preserving both branches |

```php
$config->setMergeStrategy(MergeStrategy::DEEP);
$config->merge(["server" => ["host" => "prod.example.com"]]);
```

### `mergeFlat(array ...$maps) : static`

Merges a flat dot-notation key-value map. Each key is parsed (namespace, segment
path) and written individually via `set()`, which means constant guards and freeze
checks apply per key.

### `mergeWithStrategy(MergeStrategy $strategy, array ...$maps) : static`

Identical to `merge()` but uses `$strategy` for this call only, without altering
the globally active strategy. The previous strategy is restored even if an
exception is thrown.

```php
// Deep-merge this one array without changing the global default.
$config->mergeWithStrategy(MergeStrategy::DEEP, [
    "server" => ["pools" => ["primary", "replica"]],
]);
```

### `mergeFlatWithStrategy(MergeStrategy $strategy, array ...$maps) : static`

Flat-key equivalent of `mergeWithStrategy()`. Per-key freeze and constant guards
apply as in `mergeFlat()`. When both the existing and incoming value at a key are
arrays, `$strategy` is used to combine them instead of replacing the existing
value entirely.

```php
$config->mergeFlatWithStrategy(MergeStrategy::APPEND, [
    "queue.drivers" => ["redis"],   // appended to existing list
    "db.host" => "replica",   // scalar — strategy has no effect; incoming wins
]);
```

### `batch(callable $fn) : static`

Wraps a series of writes in a suspended-dispatch window. Individual `onChange`
observers and `cortex.changed` signals are suppressed during the callable; a
single `cortex.batchChanged` signal carrying the aggregated change list is
emitted at the end.

```php
$config->batch(function (Configuration $config) {
    $config->set("a", 1);
    $config->set("b", 2);
    $config->set("c", 3);
});
```

---

## Immutability

### `freeze() : static`

Permanently prevents all writes to any key or namespace. There is no `thaw()`
operation by design.

### `freezeNamespace(namespace) : static`

Prevents writes to a single namespace only. Other namespaces remain mutable.

### `isFrozen() : bool`

### `isNamespaceFrozen(namespace) : bool`

---

## Branching

### `branch(?string $name = null) : static`

Returns a deep clone with independently-owned buckets and a fresh
`ChangeDispatcher`. Observers from the original do not carry over. The clone's
`$resetSnapshot` is cleared — its baseline is the data state at branch time.

### `immutable(?string $name = null) : static`

Delegates to `branch()->freeze()`. Returns a permanently frozen copy.

```php
$locked = $config->immutable("app.locked");
```

---

## Namespaces

### `setImplicitNamespace(namespace) : static`

Changes the namespace that bare keys (i.e. keys with no explicit `ns:` prefix)
resolve to. Defaults to `"/"`.

### `getImplicitNamespace() : string`

### `isNamespaceLoaded(namespace) : bool`

Returns `true` if the namespace bucket exists in memory (i.e. it has been
accessed at least once).

### `isNamespaceRegistered(namespace) : bool`

Returns `true` if the namespace has a pending lazy-load registration that has not
yet been consumed.

### `getBucket(name) : Bucket`

Returns (or creates) the `Bucket` for the given namespace. Triggers lazy-load if
a source is registered. This is an internal/advanced method — prefer the high-level
API in normal application code.

---

## Snapshots

### `snapshot() : static`

Captures the current state of the store (buckets, constants, frozen flags) into
an internal restore point. Calling `snapshot()` again overwrites the previous
checkpoint.

### `reset() : static`

Restores the state to the most recent `snapshot()`. Any writes made after the
snapshot are discarded. Throws `RuntimeException` if no snapshot exists.

---

## Change Observation

### `onChange(pattern, callable) : int`

Registers an observer for keys matching `$pattern` (glob syntax). Returns a
numeric handle for later removal.

```php
$handle = $config->onChange("db.*", function (string $key, mixed $old, mixed $new, Configuration $config) {
    // Fires whenever a key under the "db" path changes.
});
```

### `offChange(int $handle) : static`

Removes the observer registered under the given handle.

### Corvus signals

Every successful `set()` / `mergeFlat()` emits a `"cortex.changed"` signal on
the Corvus bus. `batch()` suppresses individual signals and emits a single
`"cortex.batchChanged"` signal instead. `merge()` in permissive mode emits
`"cortex.constantMergeSkipped"` for each constant key that was silently dropped.

---

## Registry

| Method | Description |
|--------|-------------|
| `Configuration::find(?string $name) : ?static` | Retrieves a named instance. `null` resolves to `DEFAULT_NAME`. |
| `Configuration::exists(?string $name) : bool` | Returns `true` if a named instance is registered. |
| `Configuration::getAll() : array` | Returns all registered instances. |
| `Configuration::getAllNames() : array` | Returns all registered names. |
| `Configuration::resetAll() : void` | Deregisters every named instance. |
| `Configuration::fromIterable(iterable, ?string $name) : static` | Creates and populates an instance from a flat iterator. |

---

## Export

| Method | Description |
|--------|-------------|
| `toArray() : array` | Namespace-keyed nested export. |
| `toJson(int $flags) : string` | JSON serialisation of `toArray()`. |
| `export(bool $withEnvironment = false) : array` | Prefix-unwrapped flat export. |
| `exportFlat(bool $withNamespace, bool $withEnvironment) : array` | Dot-notation flat map. |
| `exportTo(string $path) : static` | Writes a `.php` or `.json` file (atomic). |
| `except(array $queries) : array` | Export with matched keys stripped (DSL queries). |
| `search(string $query) : static` | Returns a new configuration containing only matched keys. |
| `toSafeArray(string\|object $class, string $replacement = "***") : array` | Redacts `#[Sensitive]`-annotated keys. |

---

## Scope

Calling `$config("namespace")` or accessing an array-valued key via `$config->key`
returns a `Scope` object. `Scope` implements `IteratorAggregate`, `Countable`, and
`ArrayAccess`, and exposes the same `get()`, `getRaw()`, `has()`, and typed
accessors as `Configuration`, resolved relative to its path prefix and namespace.

```php
$scope = $config("db");
foreach ($scope as $key => $value) { ... }
count($scope);              // number of keys in the "db" namespace
$scope["host"];             // ArrayAccess read
$scope->connections->mysql; // deep property chain
(string) $scope->host;      // scalar value via __toString
```

---

## Object Hydration (proxy)

`Configuration::hydrate(target, source, map, strict)` is a static convenience
proxy for `ObjectHydrator::hydrate()`. See [Hydration](Hydration.md).

---

## Configuration Cache (proxy)

`restoreCache(array $data) : static` reconstitutes a `Configuration` from the
payload written by `ConfigurationCache::write()`. See [Cache](Cache.md).

---

## ArrayAccess

`Configuration` implements `ArrayAccess`:

```php
$config["db.host"] = "localhost";   // set
$host = $config["db.host"];          // get
isset($config["db.host"]);           // has
unset($config["db.host"]);           // unset
```

---

## Exceptions

| Exception | Thrown by |
|-----------|-----------|
| `AccessDeniedException` | Key belongs to a different environment |
| `CircularReferenceException` | Interpolation cycle detected |
| `ConstantViolationException` | Write attempt on a constant key |
| `ContainerOverwriteException` | `new Configuration($name)` when `$name` is already registered |
| `FrozenConfigurationException` | Write attempt on a frozen configuration or namespace |
| `InvalidKeyException` | Key fails normalisation rules |
| `InvalidQueryException` | Multi-value query passed to a single-value method |
| `InvalidSourceException` | Import source path does not exist or is invalid |
| `SchemaViolationException` | `setConst()` value fails the given `$expression` |
| `TypeMismatchException` | Typed accessor returns incompatible value |
