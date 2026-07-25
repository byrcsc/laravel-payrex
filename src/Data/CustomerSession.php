<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A short-lived, client-side session that lets a customer manage their own
 * saved payment methods through a PayRex embedded component.
 */
final readonly class CustomerSession
{
    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $clientSecret = null,
        public ?Customer $customer = null,
        public array $components = [],
        public bool $expired = false,
        public bool $livemode = false,
        public ?CarbonImmutable $expiredAt = null,
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
            clientSecret: Payload::nullableString($data, 'client_secret'),
            customer: $customer === null ? null : Customer::from($customer),
            components: Payload::objects($data, 'components'),
            expired: Payload::bool($data, 'expired'),
            livemode: Payload::bool($data, 'livemode'),
            expiredAt: Payload::dateTime($data, 'expired_at'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
