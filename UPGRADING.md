# Upgrading to 2.0

2.0 is a clean break. There are no aliases for the old names, and several
changes alter what gets written to your JSON columns — read the behaviour
section before you deploy.

## Requirements

| | 1.x | 2.0 |
|---|---|---|
| PHP | 8.0+ | **8.2+** |
| Laravel | 10, 11, 12 | **11, 12** |
| Nova | 4 | **5** |

Laravel 10 is EOL and has been dropped, and Nova 4 with it.

## Renames

| 1.x | 2.0 |
|---|---|
| `Gabrielesbaiz\NovaFieldJson\NovaFieldJson` | `Gabrielesbaiz\NovaFieldJson\Json` |
| `Gabrielesbaiz\NovaFieldJson\NovaFieldJsonServiceProvider` | `Gabrielesbaiz\NovaFieldJson\FieldServiceProvider` |
| `->ignoreCasting()` | `->storeAs(StorageFormat::Encoded)` |
| `->saveHistory()` | **removed** — this is now the default |

The provider is auto-discovered; remove any manual entry from
`config/app.php` or `bootstrap/providers.php`.

A starting point for the rename:

```bash
rg -l 'NovaFieldJson' app/ | xargs sed -i '' \
  -e 's/NovaFieldJson::make/Json::make/g' \
  -e 's/use Gabrielesbaiz\\NovaFieldJson\\NovaFieldJson;/use Gabrielesbaiz\\NovaFieldJson\\Json;/g'
```

Then grep for `saveHistory` and `ignoreCasting` by hand.

## Behaviour changes that can alter stored data

### 1. Merging is the default

In 1.x the first child of a group set the whole column to `null` before
writing, so two groups on one column meant whichever filled second wiped the
first — unless you remembered `->saveHistory()`.

In 2.0 a group only ever touches the dotted paths its own fields declare.
Sibling groups, and keys written outside Nova, survive.

If you relied on the wipe to garbage-collect keys from an older schema, say so
explicitly:

```php
Json::make('meta', [...])->replaces(),
```

A `->replaces()` group must be the only group on its column; sharing one now
throws rather than silently racing.

### 2. Nothing is deleted that was not written

A group never removes a path it did not write. Nova filters readonly,
computed, unauthorised and view-hidden fields out of the fill before any field
runs, and a field whose request key is absent writes nothing, so "not written"
cannot safely be read as "removed".

In practice this means 2.0 behaves like 1.x's `->saveHistory()` for everything
you do not touch — with the wipe bug fixed. A key whose field you later delete
from the group will stay in the column; use `->replaces()` if you want the
group to own the column outright.

### 3. Values may change type

1.x read each child's value straight off the request, which skipped every
field that overrides `fillAttributeFromRequest()`. 2.0 lets each field fill
itself. Concretely:

| Field | 1.x stored | 2.0 stores |
|---|---|---|
| `Boolean` | `"1"` / `"0"` | `true` / `false` |
| `Code::make()->json()` | the raw JSON string | the decoded array |
| `Currency` | the raw input | its own formatted value |
| `File` / `Image` | nothing — the upload was dropped | the stored path, uploaded after save |
| a child's `->fillUsing()` | its return value, misread as the value | whatever it writes to the model |

**Audit any code that reads these keys**, and consider a one-off migration that
normalises existing rows.

### 4. Uncast columns were corrupted in 1.x

If a group with two or more fields wrote to a column with no `array`/`json`
cast, everything after the first key landed next to a stringified blob:

```php
// what 1.x actually stored
[0 => '{"type":"percent"}', 'value' => 12]
```

2.0 writes correctly, but it does **not** repair existing rows. If you see
numeric-keyed JSON strings in production, write a data-fix migration.

### 5. Unsupported children now throw

`BelongsTo`, `MorphTo`, `HasMany`, and anything implementing `Unfillable`
(`Heading`, `Badge`, `Line`, …) inside a `Json` group now raise
`JsonFieldException` at boot. 1.x accepted them and silently stored nothing.

### 6. Property writes no longer spray

`$json->someProperty = $x` used to forward the write to every child, which
swallowed typos. It now throws. Use:

```php
Json::make('meta', [...])->each(fn (Field $field) => $field->someProperty = $x),
```

### 7. Method forwarding is an explicit allowlist

`->readonly()`, `->rules()`, `->help()`, `->dependsOn()`, `->canSee()` and
around forty others now work — in 1.x they threw an argument-less
`BadMethodCallException`. An unknown method still throws, but the message names
the column and suggests the nearest match.

Note that `->showWhen*()` never existed on Nova's `Field`; if you were calling
it, it was already throwing.

### 8. JSON encoding flags changed

Uncast columns are now encoded with
`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`:
`è` becomes `è`, and `\/` becomes `/`. Semantically identical, but
byte-comparison tests, checksums and `WHERE meta = '…'` queries will break.

### 9. Action fields no longer carry a junk `hasCast` key

1.x probed `$model->hasCast()`, and on Nova's `Fluent` that silently recorded
an attribute called `hasCast`. If any code defensively read or unset
`$fields->hasCast`, remove it.

### 10. The separator is fixed at `->`

Nova's `Field::resolveAttribute()` hard-codes
`data_get($resource, str_replace('->', '.', $attribute))`, so any other
separator renders every field blank. It was never configurable in a way that
worked; it is now documented as fixed.

## New in 2.0

- `JsonEditor` — four editor modes plus repeatable rows. See the README.
- `->defaults()`, `->pruneNulls()`, `->encrypted()`, `->jsonFlags()`,
  `->storeAs()`, `->replaces()`.
- `->each()` / `->apply()`, and `Json::macro()`.
- `Json::resolveForAction()`, so `->default()` works on action fields.
