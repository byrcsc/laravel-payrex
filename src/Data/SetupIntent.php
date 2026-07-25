<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\SetupIntentStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * An intent to save a payment method for later, without charging it now.
 */
final readonly class SetupIntent
{
    /**
     * @param  list<string>  $paymentMethods
     * @param  array<string, mixed>|null  $nextAction
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?SetupIntentStatus $status = null,
        public ?string $clientSecret = null,
        public ?string $description = null,
        public ?string $returnUrl = null,
        public ?string $usage = null,
        public array $paymentMethods = [],
        public ?array $nextAction = null,
        public ?string $paymentMethodId = null,
        public ?Customer $customer = null,
        public bool $livemode = false,
        public array $metadata = [],
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $customer = Payload::object($data, 'customer');

        return new self(
            id: Payload::string($data, 'id'),
            status: Payload::enum($data, 'status', SetupIntentStatus::class),
            clientSecret: Payload::nullableString($data, 'client_secret'),
            description: Payload::nullableString($data, 'description'),
            returnUrl: Payload::nullableString($data, 'return_url'),
            usage: Payload::nullableString($data, 'usage'),
            paymentMethods: Payload::strings($data, 'payment_methods'),
            nextAction: Payload::object($data, 'next_action'),
            paymentMethodId: Payload::nullableString($data, 'payment_method_id'),
            customer: $customer === null ? null : Customer::from($customer),
            livemode: Payload::bool($data, 'livemode'),
            metadata: Payload::metadata($data),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    public function redirectUrl(): ?string
    {
        return is_string($this->nextAction['redirect_url'] ?? null)
            ? $this->nextAction['redirect_url']
            : null;
    }
}
