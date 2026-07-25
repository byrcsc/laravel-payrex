<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\PaymentIntent;
use ByRcsc\LaravelPayrex\Enums\BillingStatementStatus;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Events\BillingStatementPaid;
use ByRcsc\LaravelPayrex\Events\PaymentIntentSucceeded;
use ByRcsc\LaravelPayrex\Events\PayoutCreated;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookReceived;
use ByRcsc\LaravelPayrex\Events\RefundCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('dispatches both the generic and the typed event', function () {
    Event::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'payment_intent.succeeded'])
        ->expectsOutputToContain('Dispatched a synthetic [payment_intent.succeeded] event')
        ->assertSuccessful();

    Event::assertDispatched(PayrexWebhookReceived::class);
    Event::assertDispatched(PaymentIntentSucceeded::class);
});

it('builds an event whose resource hydrates into the matching dto', function () {
    Event::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'payment_intent.succeeded'])->assertSuccessful();

    Event::assertDispatched(PaymentIntentSucceeded::class, function (PaymentIntentSucceeded $event) {
        $intent = $event->event->paymentIntent();

        expect($intent)->toBeInstanceOf(PaymentIntent::class)
            ->and($intent?->status)->toBe(PaymentIntentStatus::Succeeded)
            ->and($intent?->id)->toStartWith('payment_intent_test_')
            ->and($event->event->id)->toStartWith('evt_test_')
            ->and($event->event->livemode)->toBeFalse();

        return true;
    });
});

it('takes the status from the event suffix when the enum has that case', function () {
    Event::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'billing_statement.paid'])->assertSuccessful();

    Event::assertDispatched(BillingStatementPaid::class, function (BillingStatementPaid $event) {
        expect($event->event->resource()?->status)->toBe(BillingStatementStatus::Paid);

        return true;
    });
});

it('leaves the status off when the suffix names no enum case', function () {
    Event::fake();

    // `created` is not a PayoutStatus, so inventing one would be a lie.
    $this->artisan('payrex:webhook-test', ['type' => 'payout.created'])->assertSuccessful();

    Event::assertDispatched(PayoutCreated::class, function (PayoutCreated $event) {
        expect($event->event->resourceData())->not->toHaveKey('status');

        return true;
    });
});

it('never touches the network', function () {
    Event::fake();
    Http::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'payment_intent.succeeded'])->assertSuccessful();

    Http::assertNothingSent();
});

it('prompts for a type to search when none is given', function () {
    Event::fake();

    $this->artisan('payrex:webhook-test')
        ->expectsSearch(
            'Which event type should be dispatched?',
            search: 'refund',
            answers: ['refund.created', 'refund.updated'],
            answer: 'refund.created',
        )
        ->expectsOutputToContain('Dispatched a synthetic [refund.created] event')
        ->assertSuccessful();

    Event::assertDispatched(RefundCreated::class);
});

it('rejects a type that is not mapped, and lists the ones that are', function () {
    Event::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'payment_intent.invented'])
        ->expectsOutputToContain('is not mapped in config/payrex.php')
        ->expectsOutputToContain('payment_intent.succeeded')
        ->assertFailed();

    Event::assertNotDispatched(PayrexWebhookReceived::class);
});

it('fails when nothing is mapped at all', function () {
    config()->set('payrex.webhooks.events', []);
    Event::fake();

    $this->artisan('payrex:webhook-test', ['type' => 'payment_intent.succeeded'])
        ->expectsOutputToContain('No event types are mapped')
        ->assertFailed();

    Event::assertNotDispatched(PayrexWebhookReceived::class);
});

it('can dispatch every type the package maps out of the box', function () {
    Event::fake();

    foreach (array_keys((array) config('payrex.webhooks.events')) as $type) {
        $this->artisan('payrex:webhook-test', ['type' => $type])->assertSuccessful();
    }

    Event::assertDispatchedTimes(
        PayrexWebhookReceived::class,
        count((array) config('payrex.webhooks.events')),
    );
});
