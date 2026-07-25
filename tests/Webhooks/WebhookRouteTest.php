<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Events\PaymentIntentSucceeded;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookReceived;
use ByRcsc\LaravelPayrex\Events\RefundUpdated;
use ByRcsc\LaravelPayrex\Exceptions\InvalidConfigurationException;
use ByRcsc\LaravelPayrex\Http\Middleware\VerifyPayrexSignature;
use ByRcsc\LaravelPayrex\PayrexServiceProvider;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Posts a signed webhook body, the way PayRex would.
 *
 * @param  array<string, mixed>  $event
 */
function postWebhook(array $event, ?string $signature = null, ?int $timestamp = null): TestResponse
{
    $payload = json_encode($event, JSON_THROW_ON_ERROR);
    $secret = (string) config('payrex.webhook_secret');

    return test()->call(
        method: 'POST',
        uri: '/payrex/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYREX_SIGNATURE' => $signature ?? WebhookSignature::header($payload, $secret, $timestamp),
        ],
        content: $payload,
    );
}

/**
 * @return array<string, mixed>
 */
function succeededEvent(string $type = 'payment_intent.succeeded'): array
{
    return [
        'id' => 'evt_3QxSample000001',
        'resource' => 'event',
        'type' => $type,
        'livemode' => false,
        'pending_webhooks' => 1,
        'created_at' => 1753420800,
        'updated_at' => 1753420800,
        'data' => [
            'resource' => [
                'resource' => 'payment_intent',
                'id' => 'pi_3QxSample000001',
                'amount' => 10000,
                'currency' => 'PHP',
                'status' => 'succeeded',
            ],
            'previous_attributes' => null,
        ],
    ];
}

it('registers the webhook route', function () {
    $route = Route::getRoutes()->getByName('payrex.webhook');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('payrex/webhook')
        ->and($route?->methods())->toContain('POST');
});

it('accepts a correctly signed delivery', function () {
    Event::fake();

    postWebhook(succeededEvent())
        ->assertOk()
        ->assertJson(['received' => true, 'id' => 'evt_3QxSample000001']);
});

it('dispatches the generic event for every delivery', function () {
    Event::fake();

    postWebhook(succeededEvent());

    Event::assertDispatched(PayrexWebhookReceived::class, function (PayrexWebhookReceived $received) {
        return $received->type() === 'payment_intent.succeeded'
            && $received->event->id === 'evt_3QxSample000001';
    });
});

it('dispatches the typed event mapped to the type', function () {
    Event::fake();

    postWebhook(succeededEvent());

    Event::assertDispatched(PaymentIntentSucceeded::class, function (PaymentIntentSucceeded $event) {
        $intent = $event->event->paymentIntent();

        return $intent?->id === 'pi_3QxSample000001' && $intent->hasSucceeded();
    });
});

it('dispatches only the generic event for an unmapped type', function () {
    Event::fake();

    postWebhook(succeededEvent('payment_intent.some_future_type'))->assertOk();

    Event::assertDispatched(PayrexWebhookReceived::class);
    Event::assertNotDispatched(PaymentIntentSucceeded::class);
    Event::assertNotDispatched(RefundUpdated::class);
});

it('rejects a delivery with no signature header', function () {
    Event::fake();

    test()->call('POST', '/payrex/webhook', content: json_encode(succeededEvent()), server: [
        'CONTENT_TYPE' => 'application/json',
    ])->assertStatus(400);

    Event::assertNotDispatched(PayrexWebhookReceived::class);
    Event::assertNotDispatched(PaymentIntentSucceeded::class);
});

it('rejects a forged signature', function () {
    Event::fake();

    postWebhook(succeededEvent(), signature: 't=1753420800,te=deadbeef,li=')
        ->assertStatus(400);

    Event::assertNotDispatched(PayrexWebhookReceived::class);
    Event::assertNotDispatched(PaymentIntentSucceeded::class);
});

it('does not let the unverified body decide which signature slot is checked', function () {
    Event::fake();
    $event = succeededEvent();
    $event['livemode'] = true;

    // Signed into the test-mode slot while the body claims live mode. Only the
    // configured secret decides what verifies, so this is accepted - and a
    // live-mode secret still cannot be forged from a test-mode one.
    postWebhook($event)->assertOk();

    Event::assertDispatched(PayrexWebhookReceived::class);
});

it('accepts a signature carried in the live mode slot', function () {
    Event::fake();
    $payload = json_encode(succeededEvent(), JSON_THROW_ON_ERROR);
    $secret = (string) config('payrex.webhook_secret');

    postWebhook(
        succeededEvent(),
        signature: WebhookSignature::header($payload, $secret, livemode: true),
    )->assertOk();

    Event::assertDispatched(PayrexWebhookReceived::class);
});

it('rejects a delivery outside the freshness window', function () {
    Event::fake();

    postWebhook(succeededEvent(), timestamp: time() - 3600)->assertStatus(400);

    Event::assertNotDispatched(PayrexWebhookReceived::class);
    Event::assertNotDispatched(PaymentIntentSucceeded::class);
});

it('accepts a stale delivery when the freshness check is disabled', function () {
    config()->set('payrex.webhooks.tolerance', 0);
    Event::fake();

    postWebhook(succeededEvent(), timestamp: time() - 3600)->assertOk();

    Event::assertDispatched(PayrexWebhookReceived::class);
});

it('rejects a signed but malformed body', function () {
    Event::fake();

    $payload = '{"not":"an event"}';

    test()->call('POST', '/payrex/webhook', content: $payload, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYREX_SIGNATURE' => WebhookSignature::header($payload, (string) config('payrex.webhook_secret')),
    ])->assertStatus(400);

    Event::assertNotDispatched(PayrexWebhookReceived::class);
    Event::assertNotDispatched(PaymentIntentSucceeded::class);
});

it('honours a custom webhook path', function () {
    // Routes are registered at boot, so the config has to be in place before
    // the app is rebuilt.
    $this->bootConfig = ['payrex.webhooks.path' => 'hooks/payrex'];
    $this->refreshApplication();

    expect(Route::getRoutes()->getByName('payrex.webhook')?->uri())->toBe('hooks/payrex');
});

it('does not register the route when webhooks are disabled', function () {
    $this->bootConfig = ['payrex.webhooks.enabled' => false];
    $this->refreshApplication();

    expect(Route::getRoutes()->getByName('payrex.webhook'))->toBeNull();
});

it('applies extra middleware from config to the route', function () {
    $this->bootConfig = ['payrex.webhooks.middleware' => ['throttle:60,1']];
    $this->refreshApplication();

    expect(Route::getRoutes()->getByName('payrex.webhook')?->gatherMiddleware())
        ->toBe([VerifyPayrexSignature::class, 'throttle:60,1']);
});

it('rejects an invalid webhook event map while the package boots', function () {
    config()->set('payrex.webhooks.events', [
        'payment_intent.succeeded' => stdClass::class,
    ]);

    $provider = new PayrexServiceProvider(app());

    expect(fn () => $provider->packageBooted())
        ->toThrow(InvalidConfigurationException::class, 'must extend');
});
