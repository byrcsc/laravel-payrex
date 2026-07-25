<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Exceptions\InvalidPayloadException;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A verified webhook event delivered by PayRex.
 *
 * PayRex wraps the changed object in `data.resource` and any old values in
 * `data.previous_attributes`. The `data` property preserves that documented
 * envelope; {@see self::resourceData()} exposes the changed object.
 */
final readonly class WebhookEvent
{
    /**
     * Maps the resource object's discriminator to the DTO that models it.
     */
    private const RESOURCES = [
        'payment_intent' => PaymentIntent::class,
        'payment' => Payment::class,
        'payment_method' => PaymentMethod::class,
        'refund' => Refund::class,
        'checkout_session' => CheckoutSession::class,
        'setup_intent' => SetupIntent::class,
        'customer' => Customer::class,
        'billing_statement' => BillingStatement::class,
        'billing_statement_line_item' => BillingStatementLineItem::class,
        'payout' => Payout::class,
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $previousAttributes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $resourceName = 'event',
        public array $data = [],
        public ?bool $livemode = null,
        public int $pendingWebhooks = 0,
        public ?array $previousAttributes = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidPayloadException
     */
    public static function from(array $payload): self
    {
        foreach (['id', 'type', 'data'] as $required) {
            if (! array_key_exists($required, $payload)) {
                throw new InvalidPayloadException(
                    "The webhook payload is missing the required `{$required}` field."
                );
            }
        }

        $id = $payload['id'];

        if (! is_string($id) || trim($id) === '') {
            throw new InvalidPayloadException(
                'The webhook payload `id` field must be a non-empty string.'
            );
        }

        $type = $payload['type'];

        if (! is_string($type) || trim($type) === '') {
            throw new InvalidPayloadException(
                'The webhook payload `type` field must be a non-empty string.'
            );
        }

        $eventData = Payload::object($payload, 'data');

        if ($eventData === null) {
            throw new InvalidPayloadException('The webhook payload `data` field is not an object.');
        }

        $resource = Payload::object($eventData, 'resource');

        if ($resource === null) {
            throw new InvalidPayloadException(
                'The webhook payload `data.resource` field is not an object.'
            );
        }

        return new self(
            id: $id,
            type: $type,
            resourceName: Payload::string($payload, 'resource', 'event'),
            data: $eventData,
            livemode: Payload::nullableBool($payload, 'livemode'),
            pendingWebhooks: Payload::int($payload, 'pending_webhooks'),
            previousAttributes: Payload::object($eventData, 'previous_attributes'),
            createdAt: Payload::dateTime($payload, 'created_at'),
            updatedAt: Payload::dateTime($payload, 'updated_at'),
            raw: $payload,
        );
    }

    /**
     * Decodes a raw JSON webhook body. Signature verification happens in the
     * middleware; this only parses.
     *
     * @throws InvalidPayloadException
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidPayloadException('The webhook payload is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return self::from($decoded);
    }

    /**
     * The kind of object this event is about, e.g. `payment_intent`.
     */
    public function resourceType(): ?string
    {
        return Payload::nullableString($this->resourceData(), 'resource');
    }

    /**
     * The raw object under the documented `data.resource` envelope.
     *
     * @return array<string, mixed>
     */
    public function resourceData(): array
    {
        return Payload::object($this->data, 'resource') ?? [];
    }

    /**
     * The event's subject as a typed DTO, or null when it names a resource this
     * package does not model yet. The untyped payload is always on `$data`.
     */
    public function resource(): PaymentIntent|Payment|PaymentMethod|Refund|CheckoutSession|SetupIntent|Customer|BillingStatement|BillingStatementLineItem|Payout|null
    {
        $class = self::RESOURCES[$this->resourceType()] ?? null;

        return $class === null ? null : $class::from($this->resourceData());
    }

    public function paymentIntent(): ?PaymentIntent
    {
        $resource = $this->resource();

        return $resource instanceof PaymentIntent ? $resource : null;
    }

    public function payment(): ?Payment
    {
        $resource = $this->resource();

        return $resource instanceof Payment ? $resource : null;
    }

    public function refund(): ?Refund
    {
        $resource = $this->resource();

        return $resource instanceof Refund ? $resource : null;
    }

    public function checkoutSession(): ?CheckoutSession
    {
        $resource = $this->resource();

        return $resource instanceof CheckoutSession ? $resource : null;
    }

    /**
     * Whether this event's type matches one of the given patterns. Supports a
     * trailing `*` wildcard, e.g. `payment_intent.*`.
     */
    public function is(string ...$patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($this->type, substr($pattern, 0, -1))) {
                    return true;
                }
            } elseif ($this->type === $pattern) {
                return true;
            }
        }

        return false;
    }
}
