<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex;

use ByRcsc\LaravelPayrex\Data\ApiResponseMetadata;
use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\Exceptions\ApiConnectionException;
use ByRcsc\LaravelPayrex\Exceptions\InvalidConfigurationException;
use ByRcsc\LaravelPayrex\Exceptions\InvalidPayloadException;
use ByRcsc\LaravelPayrex\Exceptions\PayrexException;
use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;
use ByRcsc\LaravelPayrex\Exceptions\UnexpectedResponseException;
use ByRcsc\LaravelPayrex\Resources\BillingStatementLineItems;
use ByRcsc\LaravelPayrex\Resources\BillingStatements;
use ByRcsc\LaravelPayrex\Resources\CheckoutSessions;
use ByRcsc\LaravelPayrex\Resources\Customers;
use ByRcsc\LaravelPayrex\Resources\CustomerSessions;
use ByRcsc\LaravelPayrex\Resources\PaymentIntents;
use ByRcsc\LaravelPayrex\Resources\Payments;
use ByRcsc\LaravelPayrex\Resources\Payouts;
use ByRcsc\LaravelPayrex\Resources\Refunds;
use ByRcsc\LaravelPayrex\Resources\SetupIntents;
use ByRcsc\LaravelPayrex\Resources\Webhooks;
use ByRcsc\LaravelPayrex\Support\FormEncoder;
use ByRcsc\LaravelPayrex\Support\Payload;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use JsonException;
use stdClass;
use Throwable;

/**
 * Talks to the PayRex API.
 *
 * Requests are sent as `application/x-www-form-urlencoded` and responses come
 * back as JSON. Every non-2xx response is translated into a typed
 * {@see PayrexException} subclass rather than returned to the caller.
 */
final class PayrexClient
{
    public const VERSION = '0.1.0';

    /** @var array<class-string, object> */
    private array $resources = [];

    private ?ApiResponseMetadata $lastResponse = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $secretKey,
        private readonly ?string $publicKey = null,
        private readonly ?string $webhookSecret = null,
        private readonly string $baseUrl = 'https://api.payrexhq.com',
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly int $retryTimes = 1,
        private readonly int $retrySleep = 200,
        private readonly int $webhookTolerance = WebhookSignature::DEFAULT_TOLERANCE,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    public function paymentIntents(): PaymentIntents
    {
        return $this->resource(PaymentIntents::class);
    }

    public function checkoutSessions(): CheckoutSessions
    {
        return $this->resource(CheckoutSessions::class);
    }

    public function setupIntents(): SetupIntents
    {
        return $this->resource(SetupIntents::class);
    }

    public function customers(): Customers
    {
        return $this->resource(Customers::class);
    }

    public function customerSessions(): CustomerSessions
    {
        return $this->resource(CustomerSessions::class);
    }

    public function payments(): Payments
    {
        return $this->resource(Payments::class);
    }

    public function refunds(): Refunds
    {
        return $this->resource(Refunds::class);
    }

    public function payouts(): Payouts
    {
        return $this->resource(Payouts::class);
    }

    public function billingStatements(): BillingStatements
    {
        return $this->resource(BillingStatements::class);
    }

    public function billingStatementLineItems(): BillingStatementLineItems
    {
        return $this->resource(BillingStatementLineItems::class);
    }

    public function webhooks(): Webhooks
    {
        return $this->resource(Webhooks::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Verbs
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function get(string $uri, array $params = []): array
    {
        return $this->request('GET', $uri, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function post(string $uri, array $params = []): array
    {
        return $this->request('POST', $uri, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function put(string $uri, array $params = []): array
    {
        return $this->request('PUT', $uri, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function delete(string $uri, array $params = []): array
    {
        return $this->request('DELETE', $uri, $params);
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * The status and headers of the most recent PayRex response, including a
     * failed one - useful for quoting a request identifier to PayRex support
     * or reading rate-limit counters off a 429.
     *
     * Null before the first request, and null again if the request never got a
     * response at all. The client is a singleton, so this reflects whichever
     * call finished last in the current process.
     */
    public function lastResponse(): ?ApiResponseMetadata
    {
        return $this->lastResponse;
    }

    /**
     * The publishable key, for handing to PayRex Elements in the browser.
     *
     * This is the only PayRex credential that may leave the server. The secret
     * key is deliberately not exposed by any accessor.
     */
    public function publicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Verifies and decodes a raw webhook body.
     *
     * The package's own route already does this - reach for it when you are
     * writing your own route instead, so the signature check and the decode
     * stay in step with the configured secret and tolerance.
     *
     * @throws SignatureVerificationException when the body was not signed with
     *                                        the configured webhook secret
     * @throws InvalidPayloadException when the signed body is not a PayRex event
     */
    public function parseEvent(string $payload, ?string $header): WebhookEvent
    {
        WebhookSignature::verify(
            payload: $payload,
            header: $header,
            secret: (string) $this->webhookSecret,
            tolerance: $this->webhookTolerance,
        );

        return WebhookEvent::fromJson($payload);
    }

    /**
     * The configured HTTP client, exposed so callers can reach endpoints this
     * package does not model yet.
     *
     * @throws InvalidConfigurationException when no secret key is configured
     */
    public function pendingRequest(): PendingRequest
    {
        if ($this->secretKey === null || trim($this->secretKey) === '') {
            /*
             * Without this the request goes out with an empty Basic auth
             * username and comes back 401, which is correct but means an
             * unconfigured app makes a live call to PayRex to find out it is
             * misconfigured.
             */
            throw new InvalidConfigurationException(
                'No PayRex secret key is configured. Set PAYREX_SECRET_KEY to your API key.'
            );
        }

        return $this->http
            ->baseUrl($this->baseUrl())
            ->withBasicAuth((string) $this->secretKey, '')
            ->acceptJson()
            ->withUserAgent('byrcsc-laravel-payrex/'.self::VERSION)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $params = []): array
    {
        $uri = '/'.ltrim($uri, '/');
        $body = FormEncoder::encode($params);
        $request = $method === 'GET'
            ? $this->retryableRequest()
            : $this->pendingRequest();

        if (in_array($method, ['GET', 'DELETE'], true)) {
            $url = $body === '' ? $uri : "{$uri}?{$body}";
        } else {
            $url = $uri;
            $request = $request->withBody($body, 'application/x-www-form-urlencoded');
        }

        $this->lastResponse = null;

        try {
            $response = $request->send($method, $url);
        } catch (ConnectionException $e) {
            throw new ApiConnectionException(
                "Could not reach the PayRex API for {$method} {$this->baseUrl()}{$uri}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->lastResponse = ApiResponseMetadata::from($response->status(), $response->headers());

        if ($response->failed()) {
            throw PayrexException::fromResponse(
                status: $response->status(),
                body: $this->decodeErrorResponse($response),
                method: $method,
                url: $this->baseUrl().$uri,
                responseHasBody: trim($response->body()) !== '',
            );
        }

        return $this->decodeSuccessfulResponse($response, $method, $uri);
    }

    /**
     * PayRex answers some calls (such as sending a billing statement) with an
     * empty body. Every non-empty successful response must be a JSON object.
     *
     * @return array<string, mixed>
     */
    private function decodeSuccessfulResponse(Response $response, string $method, string $uri): array
    {
        $body = trim($response->body());

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $shape = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedResponseException(
                "PayRex returned malformed JSON ({$response->status()}) for {$method} {$this->baseUrl()}{$uri}.",
                statusCode: $response->status(),
                previous: $exception,
            );
        }

        if (! $shape instanceof stdClass || ! is_array($decoded)) {
            throw new UnexpectedResponseException(
                "PayRex returned a non-object JSON response ({$response->status()}) for {$method} {$this->baseUrl()}{$uri}.",
                statusCode: $response->status(),
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Error payloads are best-effort because their HTTP status remains the
     * primary classification signal.
     *
     * @return array<string, mixed>|null
     */
    private function decodeErrorResponse(Response $response): ?array
    {
        $decoded = json_decode($response->body(), true);

        return Payload::asObject($decoded);
    }

    /**
     * Only transient failures are worth a second attempt; a rejected payload
     * will be rejected again.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 429 || ($status >= 500 && $status <= 599);
        }

        return false;
    }

    /**
     * PayRex does not document an idempotency-key contract. Retrying a
     * mutation after a connection failure or 5xx could therefore create a
     * duplicate charge, refund, or other resource. Automatic retries are
     * deliberately limited to GET requests.
     */
    private function retryableRequest(): PendingRequest
    {
        return $this->pendingRequest()->retry(
            times: max(1, $this->retryTimes),
            sleepMilliseconds: $this->retrySleep,
            when: $this->shouldRetry(...),
            throw: false,
        );
    }

    /**
     * @template TResource of object
     *
     * @param  class-string<TResource>  $class
     * @return TResource
     */
    private function resource(string $class): object
    {
        /** @var TResource */
        return $this->resources[$class] ??= new $class($this);
    }
}
