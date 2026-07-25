<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  list<string>  $events
 * @return array<string, mixed>
 */
function endpointBody(
    string $id = 'wh_3QxSample000001',
    string $status = 'enabled',
    array $events = ['payment_intent.succeeded'],
    ?string $secret = null,
): array {
    return array_filter([
        'resource' => 'webhook',
        'id' => $id,
        'url' => 'https://shop.test/payrex/webhook',
        'status' => $status,
        'description' => 'Production endpoint',
        'events' => $events,
        'livemode' => false,
        'secret_key' => $secret,
    ], fn (mixed $value): bool => $value !== null);
}

describe('payrex:webhook-list', function () {
    it('tabulates the registered endpoints', function () {
        Http::fake(['*' => Http::response(['data' => [endpointBody()], 'has_more' => false])]);

        $this->artisan('payrex:webhook-list')
            // One substring per line: the table writes a row at a time, and each
            // expectation is matched against a single write.
            ->expectsOutputToContain('Status')
            ->expectsOutputToContain('wh_3QxSample000001 | https://shop.test/payrex/webhook | enabled')
            ->assertSuccessful();
    });

    it('says so when nothing is registered', function () {
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);

        $this->artisan('payrex:webhook-list')
            ->expectsOutputToContain('No webhook endpoints are registered')
            ->assertSuccessful();
    });

    it('passes a limit through to the api', function () {
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);

        $this->artisan('payrex:webhook-list', ['--limit' => 5])->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.payrexhq.test/webhooks?limit=5');
    });

    it('walks every page with --all', function () {
        Http::fake(['*' => Http::sequence()
            ->push(['data' => [endpointBody('wh_1')], 'has_more' => true])
            ->push(['data' => [endpointBody('wh_2')], 'has_more' => false]),
        ]);

        $this->artisan('payrex:webhook-list', ['--all' => true])
            ->expectsOutputToContain('wh_2')
            ->assertSuccessful();

        Http::assertSentCount(2);
    });

    it('fails cleanly when PayRex rejects the call', function () {
        Http::fake(['*' => Http::response(['errors' => [['detail' => 'Invalid API key.']]], 401)]);

        $this->artisan('payrex:webhook-list')
            ->expectsOutputToContain('Invalid API key.')
            ->assertFailed();
    });
});

describe('payrex:webhook-create', function () {
    it('registers an endpoint for the given event types', function () {
        Http::fake(['*' => Http::response(endpointBody())]);

        $this->artisan('payrex:webhook-create', [
            'url' => 'https://shop.test/payrex/webhook',
            '--event' => ['payment_intent.succeeded', 'refund.created'],
            '--description' => 'Production endpoint',
        ])->expectsOutputToContain('Webhook endpoint registered.')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.payrexhq.test/webhooks'
            && formBody($request->body()) === [
                'url' => 'https://shop.test/payrex/webhook',
                'events' => ['payment_intent.succeeded', 'refund.created'],
                'description' => 'Production endpoint',
            ]);
    });

    it('subscribes to every mapped type when none are named', function () {
        Http::fake(['*' => Http::response(endpointBody())]);

        $this->artisan('payrex:webhook-create', ['url' => 'https://shop.test/payrex/webhook'])
            ->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $events = formBody($request->body())['events'];

            expect($events)->toContain('payment_intent.succeeded')
                ->toContain('billing_statement.paid')
                ->and($events)->toBe(array_values(array_unique($events)));

            return true;
        });
    });

    it('surfaces the signing secret exactly once, with a warning', function () {
        Http::fake(['*' => Http::response(endpointBody(secret: 'whsk_test_returned_once'))]);

        $this->artisan('payrex:webhook-create', ['url' => 'https://shop.test/payrex/webhook'])
            ->expectsOutputToContain('whsk_test_returned_once')
            ->expectsOutputToContain('PAYREX_WEBHOOK_SECRET')
            ->assertSuccessful();
    });

    it('refuses to register an endpoint subscribed to nothing', function () {
        config()->set('payrex.webhooks.events', []);
        Http::fake();

        $this->artisan('payrex:webhook-create', ['url' => 'https://shop.test/payrex/webhook'])
            ->expectsOutputToContain('No event types to subscribe to')
            ->assertFailed();

        Http::assertNothingSent();
    });
});

describe('payrex:webhook-update', function () {
    it('sends only the options that were given', function () {
        Http::fake(['*' => Http::response(endpointBody())]);

        $this->artisan('payrex:webhook-update', [
            'id' => 'wh_3QxSample000001',
            '--url' => 'https://shop.test/hooks',
        ])->expectsOutputToContain('Webhook endpoint updated.')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.payrexhq.test/webhooks/wh_3QxSample000001'
            && formBody($request->body()) === ['url' => 'https://shop.test/hooks']);
    });

    it('replaces the subscribed event types', function () {
        Http::fake(['*' => Http::response(endpointBody())]);

        $this->artisan('payrex:webhook-update', [
            'id' => 'wh_3QxSample000001',
            '--event' => ['refund.created'],
        ])->assertSuccessful();

        Http::assertSent(fn (Request $request) => formBody($request->body()) === ['events' => ['refund.created']]);
    });

    it('refuses a call that would change nothing', function () {
        Http::fake();

        $this->artisan('payrex:webhook-update', ['id' => 'wh_3QxSample000001'])
            ->expectsOutputToContain('Nothing to update')
            ->assertFailed();

        Http::assertNothingSent();
    });
});

describe('payrex:webhook-delete', function () {
    it('deletes after confirmation', function () {
        Http::fake(['*' => Http::response(['id' => 'wh_3QxSample000001', 'deleted' => true])]);

        $this->artisan('payrex:webhook-delete', ['id' => 'wh_3QxSample000001'])
            ->expectsConfirmation('Delete the webhook endpoint [wh_3QxSample000001]?', 'yes')
            ->expectsOutputToContain('deleted')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api.payrexhq.test/webhooks/wh_3QxSample000001');
    });

    it('deletes nothing when the confirmation is declined', function () {
        Http::fake();

        $this->artisan('payrex:webhook-delete', ['id' => 'wh_3QxSample000001'])
            ->expectsConfirmation('Delete the webhook endpoint [wh_3QxSample000001]?', 'no')
            ->expectsOutputToContain('Nothing was deleted')
            ->assertFailed();

        Http::assertNothingSent();
    });

    it('skips the prompt with --force', function () {
        Http::fake(['*' => Http::response(['id' => 'wh_3QxSample000001', 'deleted' => true])]);

        $this->artisan('payrex:webhook-delete', ['id' => 'wh_3QxSample000001', '--force' => true])
            ->assertSuccessful();

        Http::assertSentCount(1);
    });
});

describe('payrex:webhook-toggle', function () {
    it('enables an endpoint', function () {
        Http::fake(['*' => Http::response(endpointBody(status: 'enabled'))]);

        $this->artisan('payrex:webhook-toggle', ['id' => 'wh_3QxSample000001', '--enable' => true])
            ->expectsOutputToContain('enabled')
            ->assertSuccessful();

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/webhooks/wh_3QxSample000001/enable'
        );
    });

    it('disables an endpoint', function () {
        Http::fake(['*' => Http::response(endpointBody(status: 'disabled'))]);

        $this->artisan('payrex:webhook-toggle', ['id' => 'wh_3QxSample000001', '--disable' => true])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/webhooks/wh_3QxSample000001/disable'
        );
    });

    it('refuses an ambiguous request', function (array $options) {
        Http::fake();

        $this->artisan('payrex:webhook-toggle', ['id' => 'wh_1', ...$options])
            ->expectsOutputToContain('exactly one of --enable or --disable')
            ->assertFailed();

        Http::assertNothingSent();
    })->with([
        'neither' => [[]],
        'both' => [['--enable' => true, '--disable' => true]],
    ]);
});
