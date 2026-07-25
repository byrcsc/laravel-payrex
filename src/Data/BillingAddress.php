<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;

final readonly class BillingAddress
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            line1: Payload::nullableString($data, 'line1'),
            line2: Payload::nullableString($data, 'line2'),
            city: Payload::nullableString($data, 'city'),
            state: Payload::nullableString($data, 'state'),
            postalCode: Payload::nullableString($data, 'postal_code'),
            country: Payload::nullableString($data, 'country'),
            raw: $data,
        );
    }
}
