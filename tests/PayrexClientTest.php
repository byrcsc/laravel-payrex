<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Exceptions\ApiConnectionException;
use ByRcsc\LaravelPayrex\Exceptions\ApiErrorException;
use ByRcsc\LaravelPayrex\Exceptions\AuthenticationException;
use ByRcsc\LaravelPayrex\Exceptions\InvalidConfigurationException;
use ByRcsc\LaravelPayrex\Exceptions\InvalidPayloadException;
use ByRcsc\LaravelPayrex\Exceptions\InvalidRequestException;
use ByRcsc\LaravelPayrex\Exceptions\PermissionException;
use ByRcsc\LaravelPayrex\Exceptions\RateLimitException;
use ByRcsc\LaravelPayrex\Exceptions\ResourceNotFoundException;
use ByRcsc\LaravelPayrex\Exceptions\RouteNotFoundException;
use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;
use ByRcsc\LaravelPayrex\Exceptions\UnexpectedResponseException;
use ByRcsc\LaravelPayrex\PayrexClient;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

describe('authentication and transport', function () {
    it('authenticates with the secret key as the basic auth username', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1'])]);

        payrex()->get('/payment_intents/pi_1');

        Http::assertSent(function (Request $request) {
            expect($request->header('Authorization')[0])
                ->toBe('Basic '.base64_encode('sk_test_51secret:'));

            return true;
        });
    });

    it('refuses to call PayRex at all when no secret key is configured', function (?string $key) {
        config()->set('payrex.secret_key', $key);
        app()->forgetInstance(PayrexClient::class);
        Http::fake();

        expect(fn () => payrex()->get('/customers'))
            ->toThrow(InvalidConfigurationException::class, 'Set PAYREX_SECRET_KEY');

        Http::assertNothingSent();
    })->with(['null' => null, 'empty' => '', 'blank' => '   ']);

    it('sends requests to the configured base url', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1'])]);

        payrex()->get('/payment_intents/pi_1');

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/payment_intents/pi_1'
        );
    });

    it('identifies itself with a package user agent', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->get('/payments/pay_1');

        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->header('User-Agent')[0],
            'byrcsc-laravel-payrex/'
        ));
    });

    it('posts a form encoded body rather than json', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->post('/payment_intents', ['amount' => 10000, 'currency' => 'PHP']);

        Http::assertSent(function (Request $request) {
            expect($request->header('Content-Type')[0])->toBe('application/x-www-form-urlencoded')
                ->and($request->body())->toBe('amount=10000&currency=PHP');

            return true;
        });
    });

    it('encodes lists with empty brackets so the api reads them as arrays', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->post('/payment_intents', [
            'amount' => 10000,
            'payment_methods' => ['card', 'gcash'],
        ]);

        Http::assertSent(function (Request $request) {
            // Numeric indices (payment_methods[0]) would be read as an object.
            expect($request->body())->not->toContain('%5B0%5D')
                ->and(urldecode($request->body()))
                ->toContain('payment_methods[]=card')
                ->toContain('payment_methods[]=gcash')
                ->and(formBody($request->body())['payment_methods'])
                ->toBe(['card', 'gcash']);

            return true;
        });
    });

    it('preserves string keys when encoding nested objects', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->post('/payment_intents', [
            'metadata' => ['order_id' => '42', 'note' => 'first order'],
        ]);

        Http::assertSent(function (Request $request) {
            expect(formBody($request->body())['metadata'])
                ->toBe(['order_id' => '42', 'note' => 'first order']);

            return true;
        });
    });

    it('omits null parameters entirely', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->post('/payment_intents', ['amount' => 10000, 'description' => null]);

        Http::assertSent(fn (Request $request) => $request->body() === 'amount=10000');
    });

    it('normalizes enums and booleans', function () {
        Http::fake(['*' => Http::response(['ok' => true])]);

        payrex()->post('/checkout_sessions', [
            'currency' => Currency::PHP,
            'livemode' => false,
        ]);

        Http::assertSent(fn (Request $request) => $request->body() === 'currency=PHP&livemode=false');
    });

    it('passes parameters as a query string on get requests', function () {
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);

        payrex()->get('/customers', ['limit' => 5, 'before' => 'cus_9']);

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers?limit=5&before=cus_9'
        );
    });

    it('returns an empty array when the api answers with no body', function () {
        Http::fake(['*' => Http::response('', 200)]);

        expect(payrex()->post('/billing_statements/bs_1/send'))->toBe([]);
    });

    it('rejects malformed json in a non-empty successful response', function () {
        Http::fake(['*' => Http::response('{"id":', 200)]);

        expect(fn () => payrex()->get('/payment_intents/pi_1'))
            ->toThrow(UnexpectedResponseException::class, 'malformed JSON');
    });

    it('rejects non-object json in a non-empty successful response', function (string $body) {
        Http::fake(['*' => Http::response($body, 200)]);

        expect(fn () => payrex()->get('/payment_intents/pi_1'))
            ->toThrow(UnexpectedResponseException::class, 'non-object JSON');
    })->with(['[]', '"value"', 'null', '42']);
});

describe('error mapping', function () {
    $errorBody = fn (string $code, string $detail, ?string $parameter = null) => [
        'errors' => [array_filter([
            'code' => $code,
            'detail' => $detail,
            'parameter' => $parameter,
            'meta' => ['type' => 'invalid_request_error'],
        ])],
    ];

    it('maps 400 to an invalid request exception carrying the parsed errors', function () use ($errorBody) {
        Http::fake(['*' => Http::response($errorBody('parameter_invalid', 'Amount must be at least 10000.', 'amount'), 400)]);

        expect(fn () => payrex()->post('/payment_intents', ['amount' => 1]))
            ->toThrow(InvalidRequestException::class);

        try {
            payrex()->post('/payment_intents', ['amount' => 1]);
        } catch (InvalidRequestException $e) {
            expect($e->statusCode)->toBe(400)
                ->and($e->errors())->toHaveCount(1)
                ->and($e->firstError()?->code)->toBe('parameter_invalid')
                ->and($e->firstError()?->parameter)->toBe('amount')
                ->and($e->firstError()?->type())->toBe('invalid_request_error')
                ->and($e->errorsFor('amount'))->toHaveCount(1)
                ->and($e->errorsFor('currency'))->toBeEmpty()
                ->and($e->hasErrorCode('parameter_invalid'))->toBeTrue()
                ->and($e->getMessage())->toContain('Amount must be at least 10000.');
        }
    });

    it('maps 401 to an authentication exception', function () use ($errorBody) {
        Http::fake(['*' => Http::response($errorBody('unauthorized', 'Invalid API key.'), 401)]);

        expect(fn () => payrex()->get('/payment_intents/pi_1'))
            ->toThrow(AuthenticationException::class);
    });

    it('maps 403 to a permission exception', function () use ($errorBody) {
        Http::fake(['*' => Http::response($errorBody('forbidden', 'Not permitted.'), 403)]);

        expect(fn () => payrex()->get('/payouts/po_1/transactions'))
            ->toThrow(PermissionException::class);
    });

    it('maps 404 with a body to a resource not found exception', function () use ($errorBody) {
        Http::fake(['*' => Http::response($errorBody('resource_not_found', 'No such payment intent.'), 404)]);

        expect(fn () => payrex()->get('/payment_intents/pi_missing'))
            ->toThrow(ResourceNotFoundException::class);
    });

    it('maps a 404 with a route_not_found code to a route not found exception', function () {
        Http::fake(['*' => Http::response(['errors' => [[
            'code' => 'route_not_found',
            'detail' => 'Request URL (GET: /billing_statement_line_items/bsli_1) does not exist. Please see https://docs.payrexhq.com/docs or contact us through chat support.',
        ]]], 404)]);

        expect(fn () => payrex()->get('/billing_statement_line_items/bsli_1'))
            ->toThrow(RouteNotFoundException::class);
    });

    it('keeps the route_not_found detail in the exception message', function () {
        Http::fake(['*' => Http::response(['errors' => [[
            'code' => 'route_not_found',
            'detail' => 'Request URL (GET: /nope) does not exist.',
        ]]], 404)]);

        expect(fn () => payrex()->get('/nope'))
            ->toThrow(RouteNotFoundException::class, 'Request URL (GET: /nope) does not exist.');
    });

    it('maps a 404 with a resource_not_found code to a resource not found exception', function () {
        Http::fake(['*' => Http::response(['errors' => [[
            'code' => 'resource_not_found',
            'detail' => 'Payment with id pay_1 does not exist. Please provide a valid id.',
        ]]], 404)]);

        expect(fn () => payrex()->get('/payments/pay_1'))
            ->toThrow(ResourceNotFoundException::class);
    });

    it('falls back to body presence for a 404 with no error code', function () {
        Http::fake(['*' => Http::response('', 404)]);

        expect(fn () => payrex()->get('/nope'))
            ->toThrow(RouteNotFoundException::class, 'Route GET https://api.payrexhq.test/nope not found.');
    });

    it('maps 404 with an unrecognized non-empty body to a resource not found exception', function () {
        Http::fake(['*' => Http::response(['message' => 'Missing.'], 404)]);

        expect(fn () => payrex()->get('/payment_intents/pi_missing'))
            ->toThrow(ResourceNotFoundException::class);
    });

    it('maps 422 to an invalid request exception', function () use ($errorBody) {
        Http::fake(['*' => Http::response($errorBody('unprocessable', 'Cannot capture.'), 422)]);

        expect(fn () => payrex()->post('/payment_intents/pi_1/capture'))
            ->toThrow(InvalidRequestException::class);
    });

    it('maps 429 to a rate limit exception', function () {
        Http::fake(['*' => Http::response('', 429)]);

        expect(fn () => payrex()->get('/customers'))
            ->toThrow(RateLimitException::class);
    });

    it('maps unexpected statuses to a generic api error exception', function () {
        Http::fake(['*' => Http::response('', 503)]);

        expect(fn () => payrex()->get('/customers'))
            ->toThrow(ApiErrorException::class);
    });

    it('wraps connection failures', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        expect(fn () => payrex()->get('/customers'))
            ->toThrow(ApiConnectionException::class, 'Could not reach the PayRex API');
    });

    it('keeps the raw error response for inspection', function () use ($errorBody) {
        $body = $errorBody('parameter_invalid', 'Bad.', 'amount');
        Http::fake(['*' => Http::response($body, 400)]);

        try {
            payrex()->post('/payment_intents');
        } catch (InvalidRequestException $e) {
            expect($e->response)->toBe($body);
        }
    });
});

describe('retries', function () {
    it('retries transient failures and succeeds', function () {
        config()->set('payrex.retry.times', 3);
        config()->set('payrex.retry.sleep', 0);
        app()->forgetInstance(PayrexClient::class);

        Http::fake([
            '*' => Http::sequence()
                ->push('', 503)
                ->push(['id' => 'pi_1'], 200),
        ]);

        expect(payrex()->get('/payment_intents/pi_1'))->toBe(['id' => 'pi_1']);
        Http::assertSentCount(2);
    });

    it('retries any server error in the 500 range', function () {
        config()->set('payrex.retry.times', 3);
        config()->set('payrex.retry.sleep', 0);
        app()->forgetInstance(PayrexClient::class);

        Http::fake([
            '*' => Http::sequence()
                ->push('', 501)
                ->push(['id' => 'pi_1'], 200),
        ]);

        expect(payrex()->get('/payment_intents/pi_1'))->toBe(['id' => 'pi_1']);
        Http::assertSentCount(2);
    });

    it('does not retry a rejected payload', function () {
        config()->set('payrex.retry.times', 3);
        config()->set('payrex.retry.sleep', 0);
        app()->forgetInstance(PayrexClient::class);

        Http::fake(['*' => Http::response(['errors' => []], 400)]);

        expect(fn () => payrex()->post('/payment_intents'))->toThrow(InvalidRequestException::class);
        Http::assertSentCount(1);
    });

    it('does not retry mutations because PayRex has no documented idempotency keys', function () {
        config()->set('payrex.retry.times', 3);
        config()->set('payrex.retry.sleep', 0);
        app()->forgetInstance(PayrexClient::class);

        Http::fake(['*' => Http::response('', 503)]);

        expect(fn () => payrex()->post('/refunds', ['amount' => 10_000]))
            ->toThrow(ApiErrorException::class);

        Http::assertSentCount(1);
    });
});

describe('container wiring', function () {
    it('resolves the client as a singleton', function () {
        expect(app(PayrexClient::class))->toBe(app(PayrexClient::class))
            ->and(app('payrex'))->toBe(app(PayrexClient::class));
    });

    it('resolves the same resource instance on repeat access', function () {
        expect(payrex()->paymentIntents())->toBe(payrex()->paymentIntents());
    });

    it('exposes the configured base url', function () {
        expect(payrex()->baseUrl())->toBe('https://api.payrexhq.test');
    });

    it('exposes the publishable key for the frontend', function () {
        expect(payrex()->publicKey())->toBe('pk_test_51publishable');
    });

    it('returns a null publishable key when none is configured', function () {
        config()->set('payrex.public_key', null);
        app()->forgetInstance(PayrexClient::class);

        expect(payrex()->publicKey())->toBeNull();
    });
});

describe('parseEvent', function () {
    $body = fn () => json_encode([
        'id' => 'evt_3QxSample000001',
        'resource' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['resource' => ['resource' => 'payment_intent', 'id' => 'pi_3QxSample000001']],
    ], JSON_THROW_ON_ERROR);

    it('verifies and decodes a signed body', function () use ($body) {
        $payload = $body();
        $header = WebhookSignature::header($payload, 'whsk_test_signing_secret');

        $event = payrex()->parseEvent($payload, $header);

        expect($event->id)->toBe('evt_3QxSample000001')
            ->and($event->type)->toBe('payment_intent.succeeded')
            ->and($event->paymentIntent()?->id)->toBe('pi_3QxSample000001');
    });

    it('rejects a body that was not signed with the configured secret', function () use ($body) {
        $payload = $body();

        expect(fn () => payrex()->parseEvent($payload, WebhookSignature::header($payload, 'wrong_secret')))
            ->toThrow(SignatureVerificationException::class, 'does not match the payload');
    });

    it('honours the configured tolerance', function () use ($body) {
        // Deliberately not 300: a value that differs from the shipped default
        // is the only way this proves the config is read at all.
        $this->bootConfig = ['payrex.webhooks.tolerance' => 60];
        $this->refreshApplication();

        $payload = $body();
        $header = WebhookSignature::header($payload, 'whsk_test_signing_secret', timestamp: time() - 120);

        expect(fn () => payrex()->parseEvent($payload, $header))
            ->toThrow(SignatureVerificationException::class, 'outside the 60 second tolerance');
    });

    it('rejects a signed body that is not a PayRex event', function () {
        $payload = '{"not":"an event"}';
        $header = WebhookSignature::header($payload, 'whsk_test_signing_secret');

        expect(fn () => payrex()->parseEvent($payload, $header))
            ->toThrow(InvalidPayloadException::class);
    });
});

describe('version', function () {
    it('keeps the client version in step with the changelog', function () {
        // The VERSION constant feeds the User-Agent and nothing connects it to
        // the git tag, so without this the first forgotten bump ships a client
        // that lies about which version it is.
        preg_match(
            '/^## \[(\d+\.\d+\.\d+)\]/m',
            (string) file_get_contents(__DIR__.'/../CHANGELOG.md'),
            $matches,
        );

        expect($matches[1] ?? null)->not->toBeNull()
            ->and(PayrexClient::VERSION)->toBe($matches[1]);
    });
});
