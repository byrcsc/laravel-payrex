<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\CheckoutSession;
use ByRcsc\LaravelPayrex\Data\PaymentIntent;
use ByRcsc\LaravelPayrex\Data\Refund;
use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Exceptions\InvalidPayloadException;

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function event_(string $type, array $data): array
{
    return [
        'id' => 'evt_1',
        'resource' => 'event',
        'type' => $type,
        'livemode' => false,
        'pending_webhooks' => 2,
        'created_at' => 1753420800,
        'updated_at' => 1753420900,
        'data' => [
            'resource' => $data,
            'previous_attributes' => ['status' => 'processing'],
        ],
    ];
}

describe('parsing', function () {
    it('maps the envelope fields', function () {
        $event = WebhookEvent::from(event_('payment_intent.succeeded', [
            'resource' => 'payment_intent',
            'id' => 'pi_1',
        ]));

        expect($event->id)->toBe('evt_1')
            ->and($event->type)->toBe('payment_intent.succeeded')
            ->and($event->resourceName)->toBe('event')
            ->and($event->livemode)->toBeFalse()
            ->and($event->pendingWebhooks)->toBe(2)
            ->and($event->previousAttributes)->toBe(['status' => 'processing'])
            ->and($event->createdAt?->timestamp)->toBe(1753420800)
            ->and($event->updatedAt?->timestamp)->toBe(1753420900)
            ->and($event->resourceType())->toBe('payment_intent');
    });

    it('parses a raw json body', function () {
        $json = json_encode(event_('refund.updated', ['resource' => 'refund', 'id' => 're_1']));

        expect(WebhookEvent::fromJson((string) $json)->type)->toBe('refund.updated');
    });

    it('rejects a body that is not json', function () {
        WebhookEvent::fromJson('not json at all');
    })->throws(InvalidPayloadException::class, 'not a JSON object');

    it('rejects a json array rather than an object', function () {
        WebhookEvent::fromJson('[1, 2, 3]');
    })->throws(InvalidPayloadException::class, 'not a JSON object');

    it('rejects a payload missing a required field', function (string $missing) {
        $payload = event_('payment_intent.succeeded', ['resource' => 'payment_intent']);
        unset($payload[$missing]);

        WebhookEvent::from($payload);
    })->with(['id', 'type', 'data'])
        ->throws(InvalidPayloadException::class);

    it('rejects a payload whose data is not an object', function () {
        WebhookEvent::from(['id' => 'evt_1', 'type' => 'x', 'data' => 'nope']);
    })->throws(InvalidPayloadException::class, 'is not an object');

    it('rejects a payload whose data resource is not an object', function () {
        WebhookEvent::from([
            'id' => 'evt_1',
            'type' => 'x',
            'data' => ['resource' => 'payment_intent'],
        ]);
    })->throws(InvalidPayloadException::class, 'data.resource');

    it('rejects an id or type that is not a non-empty string', function (string $field, mixed $value) {
        $payload = event_('payment_intent.succeeded', ['resource' => 'payment_intent']);
        $payload[$field] = $value;

        WebhookEvent::from($payload);
    })->with([
        ['id', ''],
        ['id', '   '],
        ['id', 123],
        ['id', false],
        ['id', []],
        ['type', ''],
        ['type', 123],
        ['type', true],
        ['type', []],
    ])->throws(InvalidPayloadException::class, 'must be a non-empty string');

    it('keeps the whole payload on the raw property', function () {
        $payload = event_('payment_intent.succeeded', ['resource' => 'payment_intent', 'id' => 'pi_1']);

        expect(WebhookEvent::from($payload)->raw)->toBe($payload);
    });
});

describe('resource decoding', function () {
    it('decodes the subject into its typed dto', function () {
        $event = WebhookEvent::from(event_('payment_intent.succeeded', [
            'resource' => 'payment_intent',
            'id' => 'pi_1',
            'amount' => 10000,
            'status' => 'succeeded',
        ]));

        $intent = $event->resource();

        expect($intent)->toBeInstanceOf(PaymentIntent::class)
            ->and($intent->status)->toBe(PaymentIntentStatus::Succeeded)
            ->and($event->paymentIntent()?->amount)->toBe(10000);
    });

    it('decodes each modelled resource type', function (string $resource, string $class) {
        $event = WebhookEvent::from(event_('some.event', ['resource' => $resource, 'id' => 'obj_1']));

        expect($event->resource())->toBeInstanceOf($class);
    })->with([
        ['payment_intent', PaymentIntent::class],
        ['refund', Refund::class],
        ['checkout_session', CheckoutSession::class],
    ]);

    it('returns null for a resource type it does not model', function () {
        $event = WebhookEvent::from(event_('dispute.created', ['resource' => 'dispute', 'id' => 'dp_1']));

        expect($event->resource())->toBeNull()
            ->and($event->resourceType())->toBe('dispute')
            ->and($event->resourceData()['id'])->toBe('dp_1');
    });

    it('returns null from a typed accessor when the subject is a different resource', function () {
        $event = WebhookEvent::from(event_('refund.updated', ['resource' => 'refund', 'id' => 're_1']));

        expect($event->paymentIntent())->toBeNull()
            ->and($event->checkoutSession())->toBeNull()
            ->and($event->refund())->toBeInstanceOf(Refund::class);
    });
});

describe('type matching', function () {
    it('matches an exact type', function () {
        $event = WebhookEvent::from(event_('payment_intent.succeeded', ['resource' => 'payment_intent']));

        expect($event->is('payment_intent.succeeded'))->toBeTrue()
            ->and($event->is('refund.updated'))->toBeFalse();
    });

    it('matches any of several types', function () {
        $event = WebhookEvent::from(event_('refund.updated', ['resource' => 'refund']));

        expect($event->is('payment_intent.succeeded', 'refund.updated'))->toBeTrue();
    });

    it('matches a trailing wildcard', function () {
        $event = WebhookEvent::from(event_('payment_intent.awaiting_capture', ['resource' => 'payment_intent']));

        expect($event->is('payment_intent.*'))->toBeTrue()
            ->and($event->is('refund.*'))->toBeFalse();
    });
});
