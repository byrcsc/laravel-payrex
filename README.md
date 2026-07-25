# Laravel PayRex

[![Latest Version on Packagist](https://img.shields.io/packagist/v/byrcsc/laravel-payrex.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-payrex)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-payrex/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/byrcsc/laravel-payrex/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub PHPStan Action Status](https://img.shields.io/github/actions/workflow/status/byrcsc/laravel-payrex/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/byrcsc/laravel-payrex/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/byrcsc/laravel-payrex.svg?style=flat-square)](https://packagist.org/packages/byrcsc/laravel-payrex)

A Laravel SDK for the [PayRex](https://payrexhq.com) payments API: typed
resources, readonly DTOs, and webhook handling that feels like the rest of your
app.

> [!IMPORTANT]
> **This is an unofficial package.** It is not built, endorsed, or supported by
> PayRex. For official support, contact PayRex directly. Bugs in *this package*
> belong in this repository's issue tracker.

## Installation

```bash
composer require byrcsc/laravel-payrex
```

Publish the config file:

```bash
php artisan vendor:publish --tag="payrex-config"
```

Then set your keys:

```dotenv
PAYREX_SECRET_KEY=sk_test_...
PAYREX_PUBLIC_KEY=pk_test_...
PAYREX_WEBHOOK_SECRET=whsk_test_...
```

`PAYREX_PUBLIC_KEY` is optional and only needed if you use PayRex Elements in
the browser. See [Frontend and Elements](#frontend-and-elements).

## Quick start

Use the facade, or inject `PayrexClient` — both resolve to the same singleton.

```php
use ByRcsc\LaravelPayrex\Facades\Payrex;

$intent = Payrex::paymentIntents()->create(
    amount: 10_000,          // ₱100.00 — always the smallest currency unit
    paymentMethods: ['card', 'gcash'],
    description: 'Order #1234',
);

$intent->id;         // "pi_..."
$intent->status;     // PaymentIntentStatus::AwaitingPaymentMethod
$intent->clientSecret;
```

```php
use ByRcsc\LaravelPayrex\PayrexClient;

class CheckoutController
{
    public function __construct(private readonly PayrexClient $payrex) {}

    public function store()
    {
        $session = $this->payrex->checkoutSessions()->create(/* ... */);

        return redirect()->away($session->url);
    }
}
```

## Amounts

Every amount is an integer in the currency's **smallest unit**. For PHP that is
centavos, so `10_000` is ₱100.00. Never pass a float.

PayRex currently supports only `PHP`, so currency is represented by
`Currency::PHP` and deliberately has no environment/config default. Currency
belongs to each API resource and request; a global config value could silently
mislabel money if PayRex adds multi-currency support later.

## Resources

Each resource method takes PayRex's documented parameters as named, typed
arguments, and every method also accepts a trailing `$options` array that is
merged over them:

```php
Payrex::paymentIntents()->create(
    amount: 10_000,
    paymentMethods: ['card'],
    options: ['payment_method_options' => ['card' => ['capture_type' => 'manual']]],
);
```

Null arguments are omitted from the request rather than sent as empty values.

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
| `billingStatementLineItems()` | `create` `retrieve` `update` `delete` |
| `webhooks()` | `create` `retrieve` `update` `delete` `list` `autoPaging` `paginate` `enable` `disable` |

Checkout Session listing, Customer Session retrieval, and Billing Statement Line
Item retrieval are implemented by the official SDK but are not listed in the API
reference — treat those three reads as SDK-derived. `payouts()` only exposes
`listTransactions` because PayRex does not document a public payout CRUD
endpoint.

### Hosted checkout

```php
$session = Payrex::checkoutSessions()->create(
    currency: Currency::PHP,
    paymentMethods: ['card', 'gcash'],
    lineItems: [
        ['name' => 'Sticker pack', 'amount' => 10_000, 'quantity' => 1],
    ],
    successUrl: route('checkout.success'),
    cancelUrl: route('checkout.cancel'),
);

return redirect()->away($session->url);
```

### Listing

List endpoints return a `Listing`, which is countable and iterable:

```php
$customers = Payrex::customers()->list(limit: 20, after: $lastCustomerId);

foreach ($customers as $customer) {
    $customer->email;
}

$customers->hasMore;
$customers->collect();   // an Illuminate Collection
```

### Walking every page

`autoPaging()` follows the `after` cursor for you and yields one resource at a
time. It is a generator, so nothing is fetched until you iterate, and only one
page is ever held in memory:

```php
foreach (Payrex::customers()->autoPaging() as $customer) {
    $customer->email;
}
```

Available on `customers()`, `checkoutSessions()`, `billingStatements()`, and
`webhooks()`. Pass `limit:` to change the page size (default 100).

### Paginating in a request

`paginate()` hands back a Laravel `CursorPaginator`, so a PayRex list renders
with `->links()` like any Eloquent query and reads its cursor off the current
request:

```php
$statements = Payrex::billingStatements()->paginate(perPage: 15);

$statements->items();          // BillingStatement[]
$statements->nextPageUrl();
$statements->hasMorePages();   // straight from PayRex's has_more
```

Unlike Eloquent's paginator it does not fetch an extra row to guess whether
another page exists — PayRex answers that with `has_more`, and that answer is
what the paginator reports.

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
    $e->hasErrorCode('parameter_invalid');
} catch (PayrexException $e) {
    report($e);
}
```

| Exception | When |
|---|---|
| `AuthenticationException` | 401 — key missing, malformed, or rejected |
| `PermissionException` | 403 — key not allowed to do this |
| `InvalidRequestException` | 400 / 422 — payload rejected |
| `ResourceNotFoundException` | 404 with a body — no such object |
| `RouteNotFoundException` | 404 empty — no such endpoint |
| `RateLimitException` | 429 |
| `ApiErrorException` | anything else, including 5xx |
| `ApiConnectionException` | the API could not be reached at all |
| `UnexpectedResponseException` | a successful non-empty response was not a JSON object |
| `InvalidConfigurationException` | no secret key is set, or the webhook event map contains an invalid class |
| `SignatureVerificationException` | an inbound webhook failed verification |
| `InvalidPayloadException` | an inbound webhook body was malformed |
| `CustomerNotCreatedException` | a `HasPayrexCustomer` model has no PayRex customer yet |
| `CustomerAlreadyCreatedException` | a `HasPayrexCustomer` model was asked to create a second one |

Connection errors, 429s, and 5xx responses on **GET requests** are retried when
`PAYREX_RETRY_TIMES` is above 1. Mutating requests are never retried
automatically: PayRex does not document idempotency keys, so replaying a POST
after an ambiguous timeout could create a duplicate payment intent or refund.

Payment intent amounts are checked locally against the range PayRex documents —
₱20 to ₱59,999,999.99, or `2_000` to `5_999_999_999` in centavos — and an
out-of-range value raises `InvalidArgumentException` before any request is made.
Every other amount rule is server-side and surfaces as an
`InvalidRequestException`.

### Response metadata

`lastResponse()` carries the status and headers of the most recent call,
including a failed one — enough to quote a request identifier to PayRex support
or read rate-limit counters off a 429:

```php
try {
    Payrex::paymentIntents()->create(amount: 10_000);
} catch (PayrexException $e) {
    logger()->error('PayRex rejected the intent', [
        'status'     => Payrex::lastResponse()?->status,
        'request_id' => Payrex::lastResponse()?->header('X-Request-Id'),
    ]);
}
```

Header lookup is case-insensitive, and returns `null` for a header PayRex did
not send. PayRex does not document its response headers, so nothing here
assumes a particular name.

## Frontend and Elements

`PAYREX_PUBLIC_KEY` is the publishable key. It is safe in the browser and is
what PayRex Elements needs to tokenise card details on the client:

```php
Payrex::publicKey();   // "pk_test_..."
```

The secret key is deliberately not exposed by any accessor — it is sent as HTTP
Basic auth on every server-side request and must never reach a client bundle.

A typical Elements flow is: create a payment intent or setup intent on the
server, hand the frontend `publicKey()` plus the intent's `clientSecret`, and
let Elements complete the payment. Use `customerSessions()->create()` when the
component needs to show a customer's saved payment methods.

## Webhooks

The package registers `POST /payrex/webhook` for you, verifies the signature,
and dispatches Laravel events. Point a PayRex webhook endpoint at that URL and
put its signing secret in `PAYREX_WEBHOOK_SECRET`.

Two events fire per delivery: `PayrexWebhookReceived` for every verified
payload, plus a type-specific class when the type is mapped in
`config('payrex.webhooks.events')`.

```php
use ByRcsc\LaravelPayrex\Events\PaymentIntentSucceeded;

class FulfillOrder implements ShouldQueue
{
    public function handle(PaymentIntentSucceeded $event): void
    {
        $intent = $event->event->paymentIntent();

        Order::where('payment_intent_id', $intent->id)->update(['paid' => true]);
    }
}
```

Handle everything in one place instead:

```php
use ByRcsc\LaravelPayrex\Events\PayrexWebhookReceived;

public function handle(PayrexWebhookReceived $received): void
{
    $event = $received->event;

    if ($event->is('payment_intent.*')) {
        // $event->type, $event->paymentIntent(), $event->resourceData()
    }
}
```

`$event->data` mirrors PayRex's envelope (`resource` and
`previous_attributes`); `$event->resourceData()` returns only the changed
resource object.

Do the real work in **queued** listeners — PayRex expects a prompt response and
will retry a slow one.

### Verifying on your own route

If you would rather route webhooks yourself, `parseEvent()` does the signature
check and the decode in one call, using the configured secret and tolerance:

```php
use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;

public function __invoke(Request $request)
{
    try {
        $event = Payrex::parseEvent(
            $request->getContent(),
            $request->header('Payrex-Signature'),
        );
    } catch (SignatureVerificationException) {
        abort(400);
    }

    // $event->type, $event->paymentIntent(), ...
}
```

Verify against the **raw** body, as above — anything that re-encodes the JSON
first will change the bytes the signature was computed over.

### Mapping more event types

The event map is config, so you never have to wait on a package release:

```php
'events' => [
    'payment_intent.succeeded' => PaymentIntentSucceeded::class,
    'billing_statement.paid'   => App\Events\StatementPaid::class,  // your own
],
```

Any class extending `PayrexWebhookEvent` works. Unmapped types still fire
`PayrexWebhookReceived`.

### Testing your listeners

Build a valid signature without a live delivery:

```php
use ByRcsc\LaravelPayrex\Support\WebhookSignature;

$payload = json_encode([
    'id' => 'evt_1',
    'resource' => 'event',
    'type' => 'payment_intent.succeeded',
    'livemode' => false,
    'data' => [
        'resource' => [
            'resource' => 'payment_intent',
            'id' => 'pi_1',
            'amount' => 10_000,
        ],
        'previous_attributes' => ['status' => 'processing'],
    ],
]);

$this->postJson('/payrex/webhook', json_decode($payload, true), [
    'Payrex-Signature' => WebhookSignature::header($payload, config('payrex.webhook_secret')),
])->assertOk();
```

### Configuration

| Key | Env | Default |
|---|---|---|
| `webhooks.enabled` | `PAYREX_WEBHOOKS_ENABLED` | `true` |
| `webhooks.path` | `PAYREX_WEBHOOK_PATH` | `payrex/webhook` |
| `webhooks.tolerance` | `PAYREX_WEBHOOK_TOLERANCE` | `300` seconds |
| `webhooks.header` | `PAYREX_WEBHOOK_HEADER` | `Payrex-Signature` |

Set `webhooks.enabled` to `false` to register the controller in your own routes
file instead. The `tolerance` window is a freshness check — set it to `0` to
skip the timestamp check. It is not complete replay protection: PayRex retries
and repeated valid deliveries can arrive within the window. Make listeners
idempotent, normally by recording and deduplicating the PayRex event ID.

Only *stale* deliveries are rejected. A timestamp in the future means PayRex's
clock is ahead of yours, which is a clock-sync problem rather than a replay, and
there is no way to ask PayRex to send it again.

## Artisan commands

```bash
php artisan payrex:webhook-test                    # pick an event type interactively
php artisan payrex:webhook-test payment_intent.succeeded
```

`payrex:webhook-test` builds a synthetic event and dispatches it straight to
your listeners. Nothing goes over the network and no signature is involved — it
proves the wiring, not that your handlers cope with real PayRex data. Only types
mapped in `config('payrex.webhooks.events')` are offered, because an unmapped
type has no listener to reach.

The rest wrap the `webhooks()` resource, for managing endpoints without leaving
the terminal:

```bash
php artisan payrex:webhook-list --all
php artisan payrex:webhook-create https://shop.test/payrex/webhook --event=payment_intent.succeeded
php artisan payrex:webhook-update wh_123 --url=https://shop.test/hooks
php artisan payrex:webhook-toggle wh_123 --disable
php artisan payrex:webhook-delete wh_123
```

`payrex:webhook-create` subscribes to every mapped event type when you pass no
`--event`, and prints the signing secret once — PayRex will not show it in full
again, so copy it into `PAYREX_WEBHOOK_SECRET` there and then.

## Eloquent models as PayRex customers

Add `HasPayrexCustomer` to whichever model represents a payer and publish the
migration that gives it somewhere to keep the customer ID:

```bash
php artisan vendor:publish --tag="payrex-migrations"
php artisan migrate
```

```php
use ByRcsc\LaravelPayrex\Concerns\HasPayrexCustomer;

class User extends Authenticatable
{
    use HasPayrexCustomer;
}
```

```php
$user->createOrGetPayrexCustomer();   // creates on first call, retrieves after
$user->payrexCustomerId();            // "cus_..." or null
$user->hasPayrexCustomerId();
$user->asPayrexCustomer();            // throws if there is none yet
$user->updatePayrexCustomer();        // push the current name and email
$user->payrexPaymentMethods();        // Listing<PaymentMethod>
$user->deleteAsPayrexCustomer();      // deletes, then clears the column
```

Creating a second customer for a model that already has one throws
`CustomerAlreadyCreatedException` rather than silently orphaning the first along
with every payment method saved against it.

Every naming decision is an overridable method, so a model that keeps these
elsewhere only has to say so:

```php
public function payrexCustomerIdColumn(): string { return 'payrex_id'; }
public function payrexCustomerName(): string { return $this->company_name; }
public function payrexCustomerEmail(): string { return $this->billing_email; }
```

## Testing against this package

The client uses Laravel's HTTP client, so `Http::fake()` works throughout:

```php
Http::fake([
    'api.payrexhq.com/payment_intents' => Http::response([
        'id' => 'pi_test', 'amount' => 10_000, 'status' => 'succeeded',
    ]),
]);
```

Point `PAYREX_BASE_URL` at a sandbox or local stub to run against something real.

## Unknown values

Every DTO keeps its full original payload on `$raw`, so nothing this package
failed to model is ever lost to you.

Enums decode with `tryFrom()`, so a value PayRex adds later yields `null` on the
typed property instead of throwing:

```php
$intent->status;           // null if PayRex sent something unrecognised
$intent->raw['status'];    // always the truth
```

A `null` status on a real response is a bug — please open an issue.

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). A
vulnerability in *PayRex itself* goes to PayRex, not here.

Four things a consumer of this package can get wrong:

- **The secret key is server-side only.** It is sent as HTTP Basic auth on every
  request. It must never reach the browser, a client bundle, or a log. Only
  `publicKey()` is safe to hand to the frontend.
- **The webhook route has no CSRF token, on purpose.** It sits outside the `web`
  middleware group because a machine-to-machine callback cannot carry a session
  token. The signature check is what replaces CSRF — please do not "fix" this.
- **The webhook route is public until the signature check runs.** On a
  high-traffic host, add throttling in `config/payrex.php`:
  `'middleware' => ['throttle:60,1']`.
- **`webhooks.tolerance = 0` removes the freshness window entirely.** A payload
  captured months ago will then still verify. Leave it at the default unless you
  have a specific reason, and keep listeners idempotent either way.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: `composer test`,
`composer analyse`, and `composer format` all have to stay green, and PHPStan
runs at level max with no baseline.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

- [Ryan Catapang](https://github.com/byrcsc)
- [All contributors](https://github.com/byrcsc/laravel-payrex/graphs/contributors)

API behavior is referenced against the official
[PayRex documentation](https://docs.payrex.com/) and
[`payrex/payrex-php`](https://github.com/payrexhq/payrex-php) SDK (MIT).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
