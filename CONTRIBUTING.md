# Contributing

Thanks for taking the time.

## Getting set up

Nova is a paid package, so you need your own licence to work on this:

```bash
composer config http-basic.nova.laravel.com "you@example.com" "your-licence-key"
composer install
npm install
```

`auth.json` is gitignored — never commit it.

## The checks

```bash
composer test      # Pest
composer analyse   # PHPStan, level 6
composer format    # Pint
npm run test       # Vitest
npm run prod       # rebuild dist/
```

`dist/` is committed, because it is the only thing a consuming Nova app loads.
**Run `npm run prod` and commit the result whenever you touch `resources/`** —
CI fails the build otherwise.

## A note for outside contributors

CI cannot run on pull requests from forks: GitHub does not expose secrets to
them, and without the Nova credentials `composer install` fails. A maintainer
will run the suite locally on your branch. Please say in the PR which checks
you ran yourself.

## Conventions

- PHP follows Pint's `laravel` preset plus strict types; run `composer format`.
- Prefer a test that fails before your fix and passes after it.
- If you change how a value is stored, add a note to `UPGRADING.md`.
