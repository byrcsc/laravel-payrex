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

Need help using the package? Start a
[GitHub discussion](https://github.com/byrcsc/laravel-payrex/discussions).
Found a bug or an API response that is not modelled yet? Open an issue with a
minimal reproduction or sanitized response. Pull requests are welcome. For
PayRex account or API questions, contact PayRex directly.

| Requires | Versions |
|---|---|
| PHP | 8.3, 8.4 |
| Laravel | 12.x, 13.x |

## Installation

```bash
composer require byrcsc/laravel-payrex
php artisan vendor:publish --tag="payrex-config"
```

```dotenv
PAYREX_SECRET_KEY=sk_test_...
PAYREX_PUBLIC_KEY=pk_test_...      # only for PayRex Elements in the browser
PAYREX_WEBHOOK_SECRET=whsk_test_...
```

If you use `HasPayrexCustomer`, publish its migration and review the target
table before running it. The migration uses `users` by default.

```bash
php artisan vendor:publish --tag="payrex-migrations"
php artisan migrate
```

## Quick start

Use the facade, or inject `PayrexClient`. Both resolve to the same singleton.

```php
use ByRcsc\LaravelPayrex\Facades\Payrex;

$intent = Payrex::paymentIntents()->create(
    amount: 10_000,          // ₱100.00, smallest currency unit
    paymentMethods: ['card', 'gcash'],
    description: 'Mar 1 - Apr 1, 2026',
);

$intent->id;         // "pi_..."
$intent->status;     // PaymentIntentStatus::AwaitingPaymentMethod
$intent->clientSecret;
```

Hosted checkout, if you would rather not build a payment form:

```php
use ByRcsc\LaravelPayrex\Enums\Currency;

$session = Payrex::checkoutSessions()->create(
    currency: Currency::PHP,
    paymentMethods: ['card', 'gcash'],
    lineItems: [
        // Quantity is the seat count, amount the per-seat price.
        ['name' => 'Basic, 1 year', 'amount' => 100_000, 'quantity' => 5],
    ],
    description: 'Mar 1, 2026 - Mar 1, 2027',
    successUrl: route('billing.success'),
    cancelUrl: route('billing.cancel'),
);

return redirect()->away($session->url);
```

**Amounts are integers in the smallest unit.** `10_000` is ₱100.00. Never pass a
float. PayRex supports only `Currency::PHP` today.

**PayRex has no subscription resource,** so nothing here renews on its own. The
examples above are single payments: an annual plan bought upfront. The
straightforward way to bill a recurring plan is a billing statement per cycle.

PayRex also supports storing a payment method with a setup intent: the card goes
to PayRex and your app keeps only a `pm_...` token, never card details. Charging
that token unattended is a separate question. Confirm off-session support and
your recurring-charge consent obligations with PayRex before relying on it.

## Resources

Methods take PayRex's documented parameters as named, typed arguments, plus a
trailing `$options` array merged over them. Null arguments are omitted rather
than sent empty.

```php
Payrex::paymentIntents()->create(
    amount: 10_000,
    paymentMethods: ['card'],
    options: ['payment_method_options' => ['card' => ['capture_type' => 'manual']]],
);
```

| Resource | Methods |
|---|---|
| `paymentIntents()` | `create` `retrieve` `update` `cancel` `capture` `attach` |
| `checkoutSessions()` | `create` `retrieve` `list` `autoPaging` `paginate` `expire` |
| `setupIntents()` | `create` `retrieve` `cancel` |
| `customers()` | `create` `retrieve` `update` `delete` `list` `autoPaging` `paginate` `listPaymentMethods` `deletePaymentMethod` |
| `customerSessions()` | `create` `retrieve` |
| `payments()` | `retrieve` `update` |
| `refunds()` | `create` `update` |
| `payouts()` | `listTransactions` |
| `billingStatements()` | `create` `retrieve` `update` `delete` `list` `autoPaging` `paginate` `finalize` `send` `void` `markUncollectible` |
| `billingStatementLineItems()` | `create` `update` `delete` - PayRex has no retrieve route; read line items from the parent statement's `lineItems` |
| `webhooks()` | `create` `retrieve` `update` `delete` `list` `autoPaging` `paginate` `enable` `disable` |

PayRex can return a deleted customer as an HTTP `200` tombstone instead of a
`404`. Call `$customer->isDeleted()` after retrieving a customer that may have
been deleted outside your application.

## Webhooks

The package registers `POST /payrex/webhook`, verifies the signature, and
dispatches Laravel events. Point a PayRex endpoint there and put its signing
secret in `PAYREX_WEBHOOK_SECRET`.

Every verified delivery fires `PayrexWebhookReceived`, plus a type-specific
class when the type is mapped in `config('payrex.webhooks.events')`.

```php
use ByRcsc\LaravelPayrex\Events\PaymentIntentSucceeded;

class ActivateSeats implements ShouldQueue
{
    public function handle(PaymentIntentSucceeded $event): void
    {
        $intent = $event->event->paymentIntent();

        Subscription::where('payment_intent_id', $intent->id)->update(['seats_active' => true]);
    }
}
```

Queue your listeners. PayRex retries a failed or slow delivery for up to three
days with exponential backoff, so **deduplicate on the event ID**: a valid
delivery can arrive more than once, and the freshness window is not replay
protection.

## Errors

Every non-2xx response becomes a typed exception. Catch `PayrexException` for
anything, or a subclass to narrow.

```php
use ByRcsc\LaravelPayrex\Exceptions\InvalidRequestException;
use ByRcsc\LaravelPayrex\Exceptions\PayrexException;

try {
    Payrex::paymentIntents()->create(amount: 1, paymentMethods: ['card']);
} catch (InvalidRequestException $e) {
    $e->firstError()?->detail;        // "Amount must be at least ..."
    $e->errorsFor('amount');          // errors about one parameter
} catch (PayrexException $e) {
    report($e);
}
```

GET requests retry on connection errors, 429s, and 5xx when `PAYREX_RETRY_TIMES`
is above 1. **Mutations never retry.** PayRex documents no idempotency keys, so
replaying a POST after an ambiguous timeout could duplicate a payment or refund.

Payment intent amounts are range-checked locally (₱20 to ₱59,999,999.99) and
raise `InvalidArgumentException` before any request leaves. Every other amount
rule is server-side.

## Also included

- `autoPaging()` generators and `paginate()` returning a `CursorPaginator`.
- `HasPayrexCustomer`, an Eloquent trait making any model a PayRex customer,
  with a publishable migration.
- Artisan commands: `payrex:webhook-test`, `-create`, `-list`, `-update`,
  `-delete`, `-toggle`.
- `parseEvent()` for verifying webhooks on your own route.
- `Payrex::publicKey()` for Elements; the secret key has no accessor by design.
- `lastResponse()` for the status and headers of the most recent call.
- `Http::fake()` works throughout; override `PAYREX_BASE_URL` for a proxy or
  local stub.
- Every DTO keeps its untouched payload on `$raw`, and enums decode with
  `tryFrom()`, so a value PayRex adds later yields `null` instead of throwing.

## Security

Report vulnerabilities privately, see [SECURITY.md](SECURITY.md). A
vulnerability in *PayRex itself* goes to PayRex.

- **The secret key is server-side only.** Sent as HTTP Basic auth on every
  request; never let it reach a browser, bundle, or log. Only `publicKey()` is
  safe to hand out.
- **The webhook route intentionally has no CSRF token.** It sits outside the
  `web` group because a machine-to-machine callback carries no session. The
  signature check authenticates the request instead.
- **The webhook route is public until the signature check runs.** Throttle it on
  a busy host: `'middleware' => ['throttle:60,1']` in `config/payrex.php`.
- **`webhooks.tolerance` defaults to 300 seconds.** Keep your server clock
  synchronized with PayRex. Raising the tolerance accepts older signed
  deliveries; setting it to `0` disables the freshness check. In every case,
  **deduplicate on the event ID**.

## Testing

The test suite needs no external database, `.env` file, or PayRex account.

```bash
composer install
composer test
composer analyse
vendor/bin/pint --test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup details, design conventions,
and pull request guidance.

## Credits

- [Ryan Catapang](https://github.com/byrcsc)
- [All contributors](https://github.com/byrcsc/laravel-payrex/graphs/contributors)

API behavior is referenced against the official
[PayRex documentation](https://docs.payrex.com/) and
[`payrex/payrex-php`](https://github.com/payrexhq/payrex-php) SDK (MIT).

## License

MIT. See [LICENSE.md](LICENSE.md). Changelog in [CHANGELOG.md](CHANGELOG.md).
