# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

You can find and compare releases at the [GitHub release page](https://github.com/angelpolitis/Wingman-Cortex/releases).

---

## [1.0.0] — Unreleased

Initial release of Cortex, the hierarchical configuration management system for the Wingman framework.

### Added

**Core architecture**
- `Configuration` — central configuration class providing a hierarchical, namespace-scoped key store with multi-environment support. Supports freeze/unfreeze, constant keys, merge strategies, and reactive change dispatch.
- `Registry` — named-environment registry for managing a population of `Configuration` instances under a shared namespace.
- `ConfigurationRegistry` — static pool for retrieving and managing named `Registry` instances across the application.
- `Bucket` — internal per-namespace storage unit holding keyed values and tracking constant, sensitive, and deprecated metadata per key.
- `Scope` — value object encapsulating a namespace and environment pair for bounded reads and writes.
- `ChangeDispatcher` — owns the per-`Configuration` change-notification pipeline; emits `Signal::CHANGED` on single-key writes and `Signal::BATCH_CHANGED` on batch operations via the Corvus bridge.

**Query language (DSL)**
- `QueryParser` — parses Cortex query strings into structured token trees supporting dot notation, namespace scoping (`ns:key`), environment scoping (`@env:key`), wildcards (`*`, `**`), projections (`{a,b,c}`), parent references, and grouped multi-key selection.
- `QueryEngine` — executes parsed query tokens against a `Configuration` instance, resolving wildcards, projections, nested scopes, and cross-environment references.
- Full DSL support in all read methods: `get()`, `getMany()`, `only()`, `has()`, `delete()`.

**Key operations**
- `get()` / `set()` / `has()` / `delete()` — single-key CRUD with full DSL support.
- `getMany()` / `only()` — multi-key retrieval accepting dot notation, wildcard, and projection queries.
- `merge()` / `mergeFlat()` — merge one or more associative arrays into the active namespace.
- `mergeWithStrategy()` / `mergeFlatWithStrategy()` — merge with a temporarily overridden `MergeStrategy`, restoring the original on completion or exception.
- `freeze()` / `unfreeze()` — make a `Configuration` or individual namespace read-only.
- Constant key protection: keys marked constant raise `ConstantViolationException` on overwrite attempts; `Signal::CONSTANT_MERGE_SKIPPED` is emitted during batch merges when a constant key is skipped.

**String interpolation**
- `Interpolator` — resolves `${variable}` placeholders in string values against a registry of named `Variable` instances.
- `InterpolationContext` — context object passed through the recursive resolution pipeline, carrying depth tracking and visited-path deduplication.
- `Variable` — value object representing a named interpolation variable with a scalar or callable resolver.
- `CircularReferenceException` thrown when a variable definition references itself directly or transitively.
- `#[NoInterpolate]` attribute suppresses interpolation for a specific key.

**File loading**
- `Loader` — loads individual configuration files, resolves the appropriate parser by file extension, and applies `parserOptions`, transforms, and type coercion.
- `CanImport` trait — provides `import()`, `importLayered()`, and `loadDirectory()` methods with options: `parent`, `mapDirectoryStructure`, `parserOptions`, `flat`, `const`, and `snapshot` (save/restore the full configuration state on failure).
- `CanExport` trait — exports the active namespace to a plain array, JSON, YAML, TOML, INI, or XML string.
- `ParserInterface` — contract for all file-format parsers.
- `IniParser` — parses `.ini` files with section support.
- `EnvParser` — parses `.env` files, stripping quotes and inline comments.
- `YamlParser` — parses `.yaml` / `.yml` files via Symfony Yaml.
- `TomlParser` — parses `.toml` files.
- `XmlParser` — parses `.xml` files, mapping element hierarchies to nested arrays.

**Schema & validation**
- `ConfigurationSchema` — validates configuration keys against a PHP class model annotated with Cortex attributes.
- `ValidatorInterface` — common contract implemented by both `NativeValidator` and the Verix bridge.
- `NativeValidator` — built-in fallback validator used when Verix is not present.
- `#[SchemaClass]` — marks a PHP class as a Cortex schema root.
- `#[Configurable]` — declares a property as a configurable key with an optional default and required flag.
- `#[ConfigGroup]` — maps a property to a nested namespace within the schema.
- `#[ConfigSource]` — references an external file to be loaded as the value of a property.
- `#[Constant]` — marks a schema property as constant after the configuration is loaded.
- `#[Deprecated]` — marks a schema property as deprecated, emitting a notice on access.
- `#[Environment]` — restricts a schema property to specific environments.
- `#[Sensitive]` — marks a schema property as sensitive, masking its value in debug output.
- `#[Transform]` — attaches a callable or class-name transform to a schema property, applied after loading.

**Object hydration**
- `ObjectHydrator` — hydrates plain PHP objects from a `Configuration` or a plain array, mapping dot-path keys to public or constructor-injected properties.

**Caching**
- `ConfigurationCache` — filesystem-based serialised cache for `Configuration` instances with automatic invalidation by source file modification times.
- `ConfigurationCache::boot()` — static one-liner factory: builds and caches on first run, restores from cache on subsequent runs, with a `$populate` callable for initial construction.

**Merge strategies**
- `MergeStrategy` enum — three strategies: `REPLACE` (default, last-write wins), `APPEND` (scalar keys extend arrays), `DEEP` (recursive array merge); each exposes a public `apply(mixed $existing, mixed $incoming): mixed` method.

**Signals (Corvus integration)**
- `Signal` enum — string-backed enum centralising all Cortex signal identifiers: `CHANGED` (`cortex.changed`), `BATCH_CHANGED` (`cortex.batchChanged`), `CONSTANT_MERGE_SKIPPED` (`cortex.constantMergeSkipped`).

**Typed accessors**
- `HasTypedAccessors` trait — typed convenience wrappers: `getString()`, `getInt()`, `getFloat()`, `getBool()`, `getArray()`, and nullable variants for all types.

**Bridges**
- `Bridge/Corvus/Emitter` — double-inclusion-guarded bridge; extends real `Wingman\Corvus\Emitter` when Corvus is present, provides a no-op stub otherwise; stub `emit()` accepts `array|string|\BackedEnum` patterns.
- `Bridge/Verix/Validator` — double-inclusion-guarded bridge; delegates to the real Verix validator when Verix is present, falls back to `NativeValidator` otherwise.

**Exception hierarchy**
- `AccessDeniedException` — operation attempted on a key the current scope does not have access to.
- `CircularReferenceException` — variable interpolation entered a circular dependency.
- `ConfigurationSchemaException` — schema definition is invalid or inconsistent.
- `ConstantViolationException` — attempt to overwrite a key marked as constant.
- `ContainerOverwriteException` — attempt to register a name that is already bound in the registry.
- `FrozenConfigurationException` — write operation attempted on a frozen `Configuration` or namespace.
- `InvalidKeyException` — key string is empty, malformed, or uses reserved syntax.
- `InvalidQueryException` — query string could not be parsed or resolved.
- `InvalidSourceException` — file path passed to a loader does not exist or is not readable.
- `MissingDependencyException` — a required optional dependency (Corvus, Verix) is not available.
- `ReadOnlyException` — write attempted on a scope opened in read-only mode.
- `SchemaViolationException` — configuration value fails schema validation.
- `TypeMismatchException` — value type is incompatible with the declared or inferred key type.
- `UndefinedVariableException` — interpolation references a variable that has not been registered.

**Autoloader**
- Manifest-driven PSR-4 autoloader (`autoload.php`) reads `manifest.json` and skips optional dependencies (Corvus, Verix) when they are absent.
- `manifest.json` declaring package metadata, PHP version constraint, and optional Corvus and Verix dependencies.

**Documentation**
- `README.md` — overview, quick-start, key concepts, and links to detailed docs.
- `docs/Cache.md` — `ConfigurationCache` lifecycle, cache invalidation, and `boot()` one-liner pattern.
- `docs/Configuration.md` — full `Configuration` API reference including query DSL, merge strategies, signals, freeze mechanics, and typed accessors.
- `docs/DSL.md` — complete Cortex query language specification: syntax, precedence, wildcard semantics, projections, and examples.
- `docs/Hydration.md` — `ObjectHydrator` usage with mapping rules and edge cases.
- `docs/Loaders.md` — `import()`, `importLayered()`, and `loadDirectory()` option reference with parser format notes.
- `docs/Schema.md` — PHP attribute-based schema declaration, `ConfigurationSchema` validation lifecycle, and attribute reference.
