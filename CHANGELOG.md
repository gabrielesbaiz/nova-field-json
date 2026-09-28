# Changelog

All notable changes to `nova-field-json` will be documented in this file.

## 2.0.1 - 2026-09-28

### Fixed

- **The editor never loaded.** The field registered its Nova asset as
  `gabrielesbaiz/nova-field-json`. Nova serves assets from
  `/nova-api/scripts/{script}`, whose route parameter does not match a slash,
  so the browser received the HTML 404 page and reported
  `Uncaught SyntaxError: Unexpected token '<'`. The asset is now named
  `gabrielesbaiz-nova-field-json`. `Json` groups were unaffected — they need
  no assets — so only `JsonEditor` was broken.

## 2.0.0 - 2026-09-27

A clean-break rewrite. See [UPGRADING.md](UPGRADING.md) before deploying —
several changes alter what gets written to your JSON columns.

### Added

- `JsonEditor` — a real Nova field with four editors (tree, key/value, raw,
  and repeatable rows), a read-only index badge and a collapsible detail viewer.
- Repeatable rows: `JsonEditor::make('tiers')->repeatable([...])->min(1)->max(10)->sortable()`.
- `->defaults()`, `->pruneNulls()`, `->encrypted()`, `->jsonFlags()`,
  `->storeAs()`, `->replaces()` on both `Json` and `JsonEditor`.
- `->each()` / `->apply()` escape hatch, and `Json` is now `Macroable`.
- Full method forwarding: `readonly`, `rules`, `help`, `dependsOn`, `canSee`
  and ~40 others now reach the child fields instead of throwing.
- `Json::resolveForAction()`, so `->default()` works on action fields.
- Documented dependent fields: `->dependsOn('column->key', ...)` on a child
  works like it does on any Nova field, and passing a `Field` instance instead
  of the rewritten attribute string does not.
- `@method` annotations for every forwarded method, so IDEs and PHPStan
  stop reporting `->onlyOnDetail()` and friends as undefined on a group.
- A Pest suite (93 tests), PHPStan level 6, Pint, and four CI workflows.

### Fixed

- **Uncast columns were corrupted.** A group of 2+ fields on a column with no
  `array`/`json` cast re-read its own encoded output between children and wrote
  back `[0 => '{"a":1}', 'b' => 2]`.
- **Actions only worked for the first model.** The "clear the old value" flag
  lived on the field instance and was never reset, so from the second model in
  an action's collection onward the column was never cleared.
- **Action fields were polluted.** The cast probe called `hasCast()` on Nova's
  `Fluent`, which records an attribute named `hasCast` and returns truthy
  rather than failing.
- **Child fields never ran their own fill logic.** `Boolean` stored `"1"`,
  `Code::json()` stored a string, `File` never uploaded, and a child's
  `->fillUsing()` was misread as a value retriever.
- Two groups on one column no longer erase each other — merging is the default,
  and a group never deletes a path it did not write (readonly, unauthorised and
  view-hidden fields are filtered out by Nova before fill, so treating "not
  written" as "removed" would lose data).
- `use Iluminate\Http\Request` (typo), and the unused dynamic `$field->wrapper`.
- Strict comparison when detecting null values.
- **A numeric child attribute type-errored.** Nova does not type
  `Field::$attribute`, so `Boolean::make('New', 1)` handed the group an
  `int` and `AttributePath::join()` rejected it.

### Changed

- Requires PHP 8.2+, Laravel 11+, Nova 5+.
- `NovaFieldJson` is now `Json`; `NovaFieldJsonServiceProvider` is now
  `FieldServiceProvider`.
- `->ignoreCasting()` is now `->storeAs(StorageFormat::Encoded)`.
- `->saveHistory()` is **removed**; its behaviour is the default.
- Encoding uses `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`.
- Writing a property on a group now throws instead of spraying it onto children.
- Relationship and unfillable fields inside a group now throw at boot.

## 1.0.0 - 2025-03-03

- Initial release, ported from [armincms/json](https://github.com/armincms/json).
