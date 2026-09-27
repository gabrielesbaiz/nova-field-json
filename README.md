# NovaField Json

Structured data in a JSON column for Laravel Nova — compose ordinary Nova fields into one column, or hand the whole column to the user as a tree, key/value, raw or repeatable-row editor.

[![Latest version](https://img.shields.io/packagist/v/gabrielesbaiz/nova-field-json.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-field-json)
[![PHP](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/nova-field-json/php?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/packagist/dependency-v/gabrielesbaiz/nova-field-json/illuminate%2Fsupport?style=flat-square&label=laravel)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/gabrielesbaiz/nova-field-json.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/nova-field-json)
[![Stars](https://img.shields.io/github/stars/gabrielesbaiz/nova-field-json?style=flat-square&logo=github)](https://github.com/gabrielesbaiz/nova-field-json/stargazers)
[![Sponsor](https://img.shields.io/github/sponsors/gabrielesbaiz?style=flat-square&label=sponsor&logo=github)](https://github.com/sponsors/gabrielesbaiz)

### 📖 [Read the documentation →](https://gabrielesbaiz.github.io/nova-field-json/)

Both fields end to end, every storage option, the full method tables, seven
recipes, nine troubleshooting entries and the 2.0 upgrade path.

> [!CAUTION]
> **Upgrading from 1.x?** Read [UPGRADING.md](UPGRADING.md) first. `NovaFieldJson`
> is now `Json`, `saveHistory()` is gone, and three fixes change what gets
> written to your columns — `Boolean` now stores `true` instead of `"1"`,
> groups merge instead of wiping, and uncast columns stop being corrupted.
> None of it is shimmed.

> [!IMPORTANT]
> A ⭐ costs you nothing and helps other developers find this package.
> [Sponsoring](https://github.com/sponsors/gabrielesbaiz) keeps it compatible
> with every new Nova release.

## What it does

Nova already addresses into a JSON column: give the model an `array` cast and
`Text::make('Type', 'meta->type')` reads and writes it. If your shape is fixed
and small, use that — two fields, no package. This exists for the cases it does
not cover:

- **A group you can move, hide or make readonly as a unit** — `->hideFromIndex()` once instead of on nine fields, and no chance of the ninth being forgotten.
- **Keys the user creates.** Nova's `KeyValue` stores every value as a string and renders *nothing at all* for a value that is an object or an array. If your JSON nests, that field cannot show it.
- **Rows in a JSON column.** Nova's `Repeater` owns the whole column, so it cannot share one with other keys, and it has no minimum or maximum.
- **Merging, defaults, pruning and encryption**, on a column with or without a cast, instead of writing that by hand.
- **Two fields in one package.** `Json` composes fields you already use and ships no assets; `JsonEditor` is a real field with four editor modes and repeatable rows.

The trade: `Json` is not a Nova `Field` but a *composer* — it rewrites each
child's attribute, hijacks its fill callback and dissolves into the parent field
list, so Nova renders the children and a handful of APIs that demand a `Field`
will not accept a group. `JsonEditor` *is* a real field, and costs you an 83 KB
(gzipped) asset bundle in return.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Laravel Nova 5 — a paid package; you need your own licence

## Installation

```bash
composer require gabrielesbaiz/nova-field-json

php artisan vendor:publish --tag=nova-field-json-lang
```

The service provider is auto-discovered and registers the `JsonEditor` assets.
There is no config file and nothing to publish to get started; the translations
step is optional. `Json` ships no assets at all — if you only use the composer,
nothing is loaded into Nova's bundle.

**[Full installation guide →](https://gabrielesbaiz.github.io/nova-field-json/#/install)**

## Documentation

| | |
|---|---|
| [Documentation site](https://gabrielesbaiz.github.io/nova-field-json/) | Everything: install, compose, edit, store. |
| [Json](https://gabrielesbaiz.github.io/nova-field-json/#/json) | Composing, nesting, sharing a column, forwarding, dependent fields, actions. |
| [JsonEditor](https://gabrielesbaiz.github.io/nova-field-json/#/editor) | Tree, key/value, raw and repeatable modes, plus the keyboard map. |
| [Storage options](https://gabrielesbaiz.github.io/nova-field-json/#/storage) | Casting, encryption, encoding flags, defaults, pruning, validation. |
| [All methods](https://gabrielesbaiz.github.io/nova-field-json/#/api) | Both fields, the shared storage options and the three enums. |
| [Recipes](https://gabrielesbaiz.github.io/nova-field-json/#/recipes) | Seven worked patterns, from tabbed settings to bulk updates from an action. |
| [Troubleshooting](https://gabrielesbaiz.github.io/nova-field-json/#/troubleshooting) | The nine things that actually go wrong. |
| [UPGRADING.md](UPGRADING.md) | 2.0 is a clean break. Read it before you deploy. |
| [CHANGELOG.md](CHANGELOG.md) | What changed, and when. |

## Testing

```bash
composer test      # pest — 93 tests
composer analyse   # phpstan level 6
composer format    # pint
npm run test       # vitest — 78 tests
npm run prod       # rebuild dist/
```

The PHP suite covers each 1.x defect as a named regression: uncast column
corruption, the action-loop clearing flag, the `Fluent` cast probe, and child
fields being denied their own fill pipeline. The JS suite covers the two
contracts most likely to break silently on a Nova upgrade — the `fill()` payload
shape and the nested validation key.

CI runs the PHP matrix (PHP 8.2–8.4 × Laravel 11–12 × lowest/stable), PHPStan,
Pint, and an assets job that fails if `dist/` is out of date.

## Contributing

Thank you for considering contributing. The guide is in
[CONTRIBUTING.md](CONTRIBUTING.md). Nova is a paid package, so CI needs
`NOVA_USERNAME` and `NOVA_LICENSE_KEY` secrets and cannot run on pull requests
from forks — the guide explains what to run locally instead.

## Security vulnerabilities

Values are never interpolated into SQL, each child field runs its own fill
pipeline, malformed JSON is a validation error rather than a 500, and the editor
renders values as text with no `v-html` anywhere. What this package does **not**
do is authorise anything — the
[security page](https://gabrielesbaiz.github.io/nova-field-json/#/security) has
the full list of properties.

Please review [our security policy](../../security/policy) for reporting a
vulnerability. Please do not open a public issue.

## Credits

Written and maintained by [Gabriele Sbaiz](https://github.com/gabrielesbaiz),
with thanks to [everyone who has contributed](../../contributors).

## Support this package

I maintain this on evenings and weekends, alongside a full-time job writing
insurance software. Keeping it green across new Nova majors is the unglamorous
part, and it is what keeps this installable in your `composer.json` next year
too.

If it is useful to you:

- ⭐ **Star the repo.** Free, thirty seconds, and it is the first signal other developers look at.
- ❤️ **[Become a sponsor](https://github.com/sponsors/gabrielesbaiz).** From $5 a month. Company tiers get your logo right here in this README.
- 🐛 **Open a good issue.** A clear reproduction is worth more than you think.
- 🗣️ **Tell another Laravel developer.** Word of mouth is how packages survive.

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-gabrielesbaiz-ff69b4?style=for-the-badge&logo=github-sponsors)](https://github.com/sponsors/gabrielesbaiz)

## Disclaimer

This package is provided as is. It writes to your database columns, and 2.0
deliberately changes what some of those writes contain — reading
[UPGRADING.md](UPGRADING.md) and backing up affected columns before deploying is
the deploying application's responsibility, not this package's.

## License

MIT. See [LICENSE.md](LICENSE.md). The MIT licence's warranty disclaimer and
limitation of liability apply in full, alongside the section above.
