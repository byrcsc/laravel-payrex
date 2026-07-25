<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\BillingDetailsCollection;
use ByRcsc\LaravelPayrex\Enums\CheckoutSessionStatus;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\SubmitType;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A PayRex-hosted checkout page. Send the payer to {@see self::$url}.
 */
final readonly class CheckoutSession
{
    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @param  list<string>  $paymentMethods
     * @param  array<string, mixed>  $paymentMethodOptions
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?int $amount = null,
        public ?string $url = null,
        public ?CheckoutSessionStatus $status = null,
        public ?Currency $currency = null,
        public array $lineItems = [],
        public array $paymentMethods = [],
        public array $paymentMethodOptions = [],
        public ?string $clientSecret = null,
        public ?string $customerId = null,
        public ?string $customerReferenceId = null,
        public ?string $description = null,
        public ?string $statementDescriptor = null,
        public ?string $successUrl = null,
        public ?string $cancelUrl = null,
        public ?SubmitType $submitType = null,
        public ?BillingDetailsCollection $billingDetailsCollection = null,
        public ?PaymentIntent $paymentIntent = null,
        public ?bool $livemode = null,
        public array $metadata = [],
        public ?CarbonImmutable $expiresAt = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $paymentIntent = Payload::object($data, 'payment_intent');

        return new self(
            id: Payload::string($data, 'id'),
            amount: Payload::nullableInt($data, 'amount'),
            url: Payload::nullableString($data, 'url'),
            status: Payload::enum($data, 'status', CheckoutSessionStatus::class),
            currency: Payload::enum($data, 'currency', Currency::class),
            lineItems: Payload::objects($data, 'line_items'),
            paymentMethods: Payload::strings($data, 'payment_methods'),
            paymentMethodOptions: Payload::object($data, 'payment_method_options') ?? [],
            clientSecret: Payload::nullableString($data, 'client_secret'),
            customerId: Payload::nullableString($data, 'customer_id'),
            customerReferenceId: Payload::nullableString($data, 'customer_reference_id'),
            description: Payload::nullableString($data, 'description'),
            statementDescriptor: Payload::nullableString($data, 'statement_descriptor'),
            successUrl: Payload::nullableString($data, 'success_url'),
            cancelUrl: Payload::nullableString($data, 'cancel_url'),
            submitType: Payload::enum($data, 'submit_type', SubmitType::class),
            billingDetailsCollection: Payload::enum($data, 'billing_details_collection', BillingDetailsCollection::class),
            paymentIntent: $paymentIntent === null ? null : PaymentIntent::from($paymentIntent),
            livemode: Payload::nullableBool($data, 'livemode'),
            metadata: Payload::metadata($data),
            expiresAt: Payload::dateTime($data, 'expires_at'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === CheckoutSessionStatus::Completed;
    }
}
