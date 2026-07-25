<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

final readonly class PaymentMethod
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?PaymentMethodType $type = null,
        public array $details = [],
        public ?Billing $billingDetails = null,
        public bool $livemode = false,
        public array $metadata = [],
        public ?string $allowRedisplay = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $billingDetails = Payload::object($data, 'billing_details');
        $type = Payload::enum($data, 'type', PaymentMethodType::class);
        $details = Payload::object($data, 'details');

        if ($details === null && $type instanceof PaymentMethodType) {
            $details = Payload::object($data, $type->value);
        }

        return new self(
            id: Payload::string($data, 'id'),
            type: $type,
            details: $details ?? [],
            billingDetails: $billingDetails === null ? null : Billing::from($billingDetails),
            livemode: Payload::bool($data, 'livemode'),
            metadata: Payload::metadata($data),
            allowRedisplay: Payload::nullableString($data, 'allow_redisplay'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
