# Contributing

Thanks for helping. This is an unofficial PayRex SDK maintained by one person,
so a clear, small pull request is worth far more than a large one.

## Getting set up

```bash
git clone https://github.com/byrcsc/laravel-payrex
cd laravel-payrex
composer install
```

No database, no `.env`, no PayRex account is needed. The suite runs entirely
against `Http::fake()` and an in-memory SQLite database that Testbench sets up
for you.

## The three checks

All three must be green before a pull request can be merged. CI runs them too,
but running them locally is faster than waiting.

```bash
composer test      # Pest
composer analyse   # PHPStan, level max
composer format    # Pint, applies fixes
```

Two things worth knowing:

- **PHPStan runs at level max with no baseline, and that is not negotiable.**
  If an error is genuinely a false positive, say so in the pull request and we
  will work out the right fix. Do not add `@phpstan-ignore`, a baseline
  entry, or a cast to silence it.
- **Pint is the only style authority.** Run `composer format` before pushing.
  Do not hand-format around it or argue with its choices in review.

## What a good change looks like

**Adding a resource method.** Take PayRex's documented parameters as named,
typed arguments, keep the trailing `$options` array, return a readonly DTO, and
add a test that asserts the exact request that goes over the wire: method, URL,
and decoded form body.

**Adding or changing a DTO.** Every field is read through `Payload`, which
degrades to a default rather than throwing. That means a wrong field name fails
silently, so add a fixture under `tests/Fixtures/` and assert every property in
`tests/Data/HydrationTest.php`. There is a test that fails if a class in
`src/Data/` has no fixture at all.

**Adding an enum case.** Enums decode with `tryFrom()`, so an unknown value
becomes `null` rather than an exception. Only add a case you have seen
documented or returned by the API - a guessed case is worse than none, because
`null` is at least honest.

**Fixing behaviour.** Please include a test that fails before the change.

## The PayRex docs are the authority

Every claim this package makes about the PayRex API comes from the official
docs at <https://docs.payrex.com> or, where the docs are silent, from the
official [`payrex/payrex-php`](https://github.com/payrexhq/payrex-php) SDK.
Nothing in here is guessed.

Adding an enum case, event type, or validation rule means citing the doc page
that supports it in the pull request. Without a citation, it does not go in.

## Things this package deliberately does not do

If your change touches one of these, explain why in the pull request; these are
decisions rather than oversights:

- **No automatic retries on mutating requests.** PayRex documents no idempotency
  key, so replaying a POST after a timeout could create a duplicate charge.
- **No client-side validation beyond what PayRex documents outright.** The
  payment intent amount range is checked locally because the docs state it
  unconditionally. Everything else (line-item totals, refund ceilings, account
  eligibility) is server-validated, and the API's 400 is the source of truth.
- **No config default for currency.** Currency belongs to each request. A global
  default could silently mislabel money if PayRex adds more currencies.
- **No invented event types or enum cases.** Everything shipped in the default
  config maps to a type PayRex documents.

## Commits and branches

Branch off `main` as `feat/…`, `fix/…`, `docs/…`, or `chore/…`, and write
[Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`,
`docs:`, `chore:`). Pull requests are squash-merged, so the pull request title
becomes the commit message, so make it the one-line changelog entry you would want
to read.

Do not edit `CHANGELOG.md` in a pull request. Released sections are written from
GitHub Release notes at tag time.

## Security

Do not open a public issue for a vulnerability. See [SECURITY.md](SECURITY.md).
