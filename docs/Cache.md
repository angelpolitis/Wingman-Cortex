# Production Cache

`ConfigurationCache` serialises the fully-resolved state of a `Configuration`
instance to an opcode-cacheable PHP file. On subsequent requests the file is
`include`d directly — no parsing, no merging, no file I/O beyond a single
opcode-cache hit. Source-file staleness is detected automatically.

> **Why specify a path?** The cache file is a project-level artefact that lives
> wherever your application has a writable location (e.g. `storage/bootstrap/`,
> `var/cache/`). Cortex is a standalone package with no concept of your project
> structure, so the location must come from you. The path is the only thing you
> need to supply.

---

## One-liner boot

```php
use Wingman\Cortex\ConfigurationCache;

$config = ConfigurationCache::boot(
    __DIR__ . "/storage/config.cache.php",
    fn ($c) => $c->importLayered(__DIR__ . "/config", $_ENV["APP_ENV"] ?? null, ["snapshot" => true]),
    "app"
);
```

`boot()` handles the entire cache-or-load decision internally. The callable
receives the empty `Configuration` and is responsible only for loading sources;
cache inspection, loading, and writing are all automatic. The third argument is
the optional instance name for the global `ConfigurationRegistry`.

The returned `$config` is fully ready to use regardless of whether the fast or
slow path was taken.

---

## API

### `static boot(string $path, callable $populate, ?string $name = null) : Configuration`

One-shot factory described above. Equivalent to the manual boot sequence but
expressed in a single call. `$populate` is invoked only on a cache miss.

### `new ConfigurationCache(string $path)`

Creates a cache manager bound to the given file path. The file does not need to
exist yet. Use this constructor when you need finer-grained control than
`boot()` provides.

### `exists() : bool`

Returns `true` if the cache file is present and readable on disk.

### `isStale() : bool`

Returns `true` when the cache should be regenerated. Specifically:

- The cache file does not exist or is not readable.
- The cache payload has no `"sources"` entry (written by an older version).
- Any source file recorded in the cache has been deleted or modified since the
  cache was written.

```php
if ($cache->isStale()) {
    // rebuild …
}
```

### `load(Configuration $config) : static`

Reads the cache file and restores the serialised state into `$config` via
`restoreCache()`. Equivalent to replaying every `import()` call from scratch but
in a single opcode-cache read.

### `write(Configuration $config) : static`

Serialises `$config` to the cache file. The write is **atomic**:
1. A temporary file is created in the same directory via `tempnam()`.
2. The serialised PHP code is written to the temporary file with `LOCK_EX`.
3. The temporary file is renamed over the target path (`rename()` is atomic on
   POSIX).

No concurrent reader can encounter a partially-written file.

### `getPath() : string`

Returns the absolute path of the cache file.

---

## Cache payload format

The cache file is a self-contained PHP script that `return`s an array:

```php
<?php return [
    "generatedAt" => 1710500000,
    "name" => "app",
    "namespaceDelimiter" => ":",
    "segmentDelimiter" => ".",
    "prefix" => "",
    "constants" => ["/:app.name" => true],
    "sources" => [
        "/var/www/html/config/app.php" => 1710499000,
        "/var/www/html/config/db.php" => 1710498500,
    ],
    "buckets" => [
        "/" => ["app" => ["name" => "Wingman", "debug" => false]],
        "db" => ["host" => "localhost", "port" => 3306],
    ],
];
```

- `"generatedAt"` — Unix timestamp of cache creation.
- `"sources"` — map of absolute source paths to their `filemtime()` at cache
  write time. Used by `isStale()`.
- `"buckets"` — the raw data of every loaded namespace bucket.

---

## Considerations

### Long-running processes

In PHP-FPM each worker process has its own opcode cache; the cache file is
safe to include concurrently because `rename()` is atomic and PHP's opcode
cache invalidates the old entry after the rename.

In long-running runtimes (Octane, Swoole, ReactPHP, RoadRunner) the loaded
configuration stays resident in memory. You may want to call
`Configuration::resetAll()` and rebuild between requests if configuration
reloading is required.

### Constants in cache

Constants registered via `setConst()` or `import(..., ["const" => true])` are
included in the cache payload and re-applied when the cache is loaded, so
immutability guarantees carry over across the slow/fast path boundary.

### Snapshot after load

`boot()` does not call `snapshot()` automatically on the fast path. If you pass
`["snapshot" => true]` to your `importLayered()` call inside `$populate`, the
snapshot state is included in the cache payload and restored on load. If you use
`reset()` in your request lifecycle and need a hard baseline, call
`$config->snapshot()` explicitly right after `boot()` returns.
