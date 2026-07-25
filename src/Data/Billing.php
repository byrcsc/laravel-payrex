<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;

/**
 * The payer's contact and address details, as collected on a PayRex page.
 */
final readonly class Billing
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?BillingAddress $address = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $address = Payload::object($data, 'address');

        return new self(
            name: Payload::nullableString($data, 'name'),
            email: Payload::nullableString($data, 'email'),
            phone: Payload::nullableString($data, 'phone'),
            address: $address === null ? null : BillingAddress::from($address),
            raw: $data,
        );
    }
}
