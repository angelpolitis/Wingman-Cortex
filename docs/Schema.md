# Schema & Validation

`ConfigurationSchema` is a rule registry that maps configuration keys to type
expressions. Rules can be asserted (throws on first violation) or validated
(returns all violations as an array).

---

## Creating a schema

```php
use Wingman\Cortex\ConfigurationSchema;

$schema = new ConfigurationSchema();

$schema->set("db.host", "string");
$schema->set("db.port", "int<min=1, max=65535>");
$schema->set("db.name", "string");
$schema->setOptional("db.charset", "string");

// Nested sections.
$schema->section("server")
    ->set("host", "string")
    ->set("port", "int<min=1, max=65535>")
    ->set("timeout", "int<min=0>")
    ->endSection();
```

---

## Registering rules

### `set(key, expression, required = true) : static`

Registers a required rule. If `$required` is `false` the key is optional — a
missing key does not produce a violation; only a present-but-invalid value does.

### `setOptional(key, expression) : static`

Convenience alias for `set($key, $expression, false)`.

---

## Sections

Sections let you group related keys under a shared prefix without repeating it.

```php
$schema
    ->section("mail")
        ->set("host", "string")
        ->set("port", "int<min=1, max=65535>")
        ->section("auth")
            ->set("user",     "string")
            ->setOptional("password", "string")
        ->endSection()
    ->endSection();
```

`section()` pushes the name onto an internal stack; `endSection()` pops it. The
resolved key of any rule registered inside a section block is:

```
section1.section2...sectionN.key
```

---

## Asserting

### `assert(Configuration $config) : static`

Asserts all registered rules. Throws `ConfigurationSchemaException` on the
first violation. Use this in bootstrap code where you want to fail fast.

```php
$schema->assert($config);
```

### `assertSection(Configuration $config, string $section) : static`

Asserts only the rules whose prefix matches `$section`.

---

## Validating

### `validate(Configuration $config) : array`

Returns an array of all violations. Each entry is an associative array:

```php
[
    "key" => "db.port",
    "expression" => "int<min=1, max=65535>",
    "value" => "not-a-number",
    "message" => "...",
]
```

Returns an empty array when every rule passes.

### `validateSection(Configuration $config, string $section) : array`

Validates only the rules whose prefix matches `$section`.

---

## Validation expressions

Cortex uses **Verix** for expression evaluation when the package is present,
and falls back to `NativeValidator` otherwise.

### Native expressions (always available)

| Expression | Matches |
|------------|---------|
| `"string"` | Any string |
| `"int"` | Any integer |
| `"float"` | Any float or int |
| `"bool"` | A boolean value |
| `"array"` | Any array |
| `"null"` | `null` |
| `"?string"` | A string or `null` |
| `"string\|int"` | Union — a string or an integer |
| `"ClassName"` | An object that is an instance of `ClassName` |

### Verix expressions (requires `wingman/verix`)

Verix extends native types with constraints:

| Expression | Matches |
|------------|---------|
| `"int<min=1, max=65535>"` | Integer in range [1, 65535] |
| `"string<minLen=1>"` | Non-empty string |
| `"float<min=0.0>"` | Non-negative float |
| `"email"` | Valid e-mail address |
| `"url"` | Valid URL |
| `"uuid"` | UUID v1–v5 |
| `"ip"` | IPv4 or IPv6 address |
| `"array<string>"` | Array of strings |

Refer to the Verix documentation for the full expression grammar.

---

## Custom validator

The built-in validator can be replaced for an entire schema:

```php
use Wingman\Cortex\Interfaces\ValidatorInterface;

class MyValidator implements ValidatorInterface
{
    public function validate (mixed $value, string $expression) : bool { ... }
    public function describe (string $expression) : string { ... }
}

$schema->setValidator(new MyValidator());
```

---

## Deriving a schema from a class

When your configuration is bound to a hydrated class, the schema can be derived
automatically from its `#[Configurable]` annotations:

```php
$schema = ObjectHydrator::getSchemaFromClass(DatabaseSettings::class);
$schema->assert($config);
```

See [Hydration](Hydration.md) for the `#[SchemaClass]` attribute, which lets
you supply a hand-crafted base schema that the derived rules are overlaid on top
of.
