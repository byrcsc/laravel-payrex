# Laravel PayRex

[![Latest Version on Packagist](https://img.shields.io/packagist/v/byrcsc/laravel-payrex.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-payrex)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-payrex/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/byrcsc/laravel-payrex/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-payrex/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/byrcsc/laravel-payrex/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/byrcsc/laravel-payrex.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-payrex)

An unofficial Laravel SDK for the [PayRex](https://payrexhq.com) payments API: typed
resources, readonly DTOs, and webhook handling that feels like the rest of your
app.

Built for Laravel projects accepting payments in the Philippines and shared
with the community, it brings PayRex's documented API into familiar Laravel
conventions.

**[Read the full documentation](https://docs.rcsc.dev/laravel-payrex/)** for
installation, resources, webhooks, pagination, testing, and security guidance.

| Laravel | Supported PHP versions |
|---|---|
| 12.x | 8.2, 8.3, 8.4, 8.5 |
| 13.x | 8.3, 8.4, 8.5 |

## Installation

Install the package and publish its configuration:

```bash
composer require byrcsc/laravel-payrex
php artisan vendor:publish --tag="payrex-config"
```

Add your PayRex test credentials:

```dotenv
PAYREX_SECRET_KEY=sk_test_...
PAYREX_PUBLIC_KEY=pk_test_...      # Only needed for PayRex Elements
PAYREX_WEBHOOK_SECRET=whsk_test_... # Only needed for webhooks
```

See the
[installation guide](https://docs.rcsc.dev/laravel-payrex/v1/installation) for
all configuration options and the optional customer migration.

## Quick start

Use the facade or inject `PayrexClient`. Both resolve to the same singleton.

```php
use ByRcsc\LaravelPayrex\Facades\Payrex;

$intent = Payrex::paymentIntents()->create(
    amount: 10_000,
    paymentMethods: ['card', 'gcash'],
    description: 'Order #RC-1042',
);

$intent->id;
$intent->status;
$intent->clientSecret;
```

Amounts are integers in the smallest currency unit: `10_000` is ₱100.00. Never
send a floating-point amount.

## What is included

- Typed resources for payments, checkout, customers, refunds, payouts, billing,
  and webhook endpoints.
- Readonly data objects that retain the untouched API payload on `$raw`.
- Verified webhooks dispatched as generic and type-specific Laravel events.
- Typed exceptions, response metadata, and conservative retries for `GET`
  requests.
- Automatic cursor iteration and Laravel `CursorPaginator` support.
- An Eloquent customer concern and Artisan commands for managing webhooks.
- `Http::fake()` support throughout the client.

## Important behavior

- Mutating requests are never retried. PayRex documents no idempotency keys, so
  replaying an ambiguous request could duplicate a payment or refund.
- Queue webhook listeners and deduplicate them using the PayRex event ID.
  Signature freshness checks do not prevent the same valid event from arriving
  more than once.
- PayRex does not expose a subscription resource. Recurring billing must be
  modelled explicitly, such as with a billing statement for each cycle.
- Keep `PAYREX_SECRET_KEY` on the server. Only the public key is intended for
  browser use with PayRex Elements.

## Documentation

- [Installation and setup](https://docs.rcsc.dev/laravel-payrex/v1/installation)
- [Quick start](https://docs.rcsc.dev/laravel-payrex/v1/quick-start)
- [Client and resources](https://docs.rcsc.dev/laravel-payrex/v1/client-and-resources)
- [Receiving webhooks](https://docs.rcsc.dev/laravel-payrex/v1/receiving-webhooks)
- [Testing](https://docs.rcsc.dev/laravel-payrex/v1/testing)
- [Security and operations](https://docs.rcsc.dev/laravel-payrex/v1/security-and-operations)

## Support and contributing

Ask usage questions in
[GitHub Discussions](https://github.com/byrcsc/laravel-payrex/discussions).
For reproducible package bugs, [open an issue](https://github.com/byrcsc/laravel-payrex/issues).
See [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

Report vulnerabilities privately by following [SECURITY.md](SECURITY.md).

## Credits

- [Ryan Catapang](https://github.com/byrcsc)
- [All contributors](https://github.com/byrcsc/laravel-payrex/graphs/contributors)

API behavior is referenced against the official
[PayRex documentation](https://docs.payrex.com/) and
[`payrex/payrex-php`](https://github.com/payrexhq/payrex-php) SDK.

## License

MIT. See [LICENSE.md](LICENSE.md). Changelog in [CHANGELOG.md](CHANGELOG.md).
