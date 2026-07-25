<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\ApiResponseMetadata;
use ByRcsc\LaravelPayrex\Exceptions\ApiConnectionException;
use ByRcsc\LaravelPayrex\Exceptions\RateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

describe('the metadata object', function () {
    it('matches header names without regard to case', function () {
        $metadata = ApiResponseMetadata::from(200, ['X-Request-Id' => ['req_123']]);

        expect($metadata->header('x-request-id'))->toBe('req_123')
            ->and($metadata->header('X-REQUEST-ID'))->toBe('req_123')
            ->and($metadata->hasHeader('X-Request-Id'))->toBeTrue();
    });

    it('returns null for a header the api did not send', function () {
        $metadata = ApiResponseMetadata::from(200, []);

        expect($metadata->header('X-Request-Id'))->toBeNull()
            ->and($metadata->headerValues('X-Request-Id'))->toBe([])
            ->and($metadata->hasHeader('X-Request-Id'))->toBeFalse();
    });

    it('keeps every value of a repeated header', function () {
        $metadata = ApiResponseMetadata::from(200, ['Set-Cookie' => ['a=1', 'b=2']]);

        expect($metadata->headerValues('set-cookie'))->toBe(['a=1', 'b=2'])
            ->and($metadata->header('set-cookie'))->toBe('a=1');
    });

    it('reports whether the status was a success', function () {
        expect(ApiResponseMetadata::from(200, [])->successful())->toBeTrue()
            ->and(ApiResponseMetadata::from(204, [])->successful())->toBeTrue()
            ->and(ApiResponseMetadata::from(429, [])->successful())->toBeFalse();
    });
});

describe('lastResponse', function () {
    it('is null before any request is made', function () {
        expect(payrex()->lastResponse())->toBeNull();
    });

    it('captures the status and headers of a successful call', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1'], 200, ['X-Request-Id' => 'req_abc'])]);

        payrex()->get('/payment_intents/pi_1');

        expect(payrex()->lastResponse()?->status)->toBe(200)
            ->and(payrex()->lastResponse()?->header('x-request-id'))->toBe('req_abc');
    });

    it('captures a failed response too, so the request id survives the exception', function () {
        Http::fake(['*' => Http::response('', 429, ['X-Request-Id' => 'req_throttled'])]);

        expect(fn () => payrex()->get('/customers'))->toThrow(RateLimitException::class);

        expect(payrex()->lastResponse()?->status)->toBe(429)
            ->and(payrex()->lastResponse()?->header('X-Request-Id'))->toBe('req_throttled');
    });

    it('clears back to null when the request never reached the api', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1'], 200)]);
        payrex()->get('/payment_intents/pi_1');

        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        expect(fn () => payrex()->get('/customers'))->toThrow(ApiConnectionException::class)
            ->and(payrex()->lastResponse())->toBeNull();
    });

    it('reflects the most recent call', function () {
        Http::fake(['*' => Http::sequence()
            ->push(['id' => 'pi_1'], 200)
            ->push(['id' => 'pi_2'], 201),
        ]);

        payrex()->get('/payment_intents/pi_1');
        payrex()->post('/payment_intents');

        expect(payrex()->lastResponse()?->status)->toBe(201);
    });
});
