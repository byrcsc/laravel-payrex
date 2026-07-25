<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

final readonly class Customer
{
    /**
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $email = null,
        public ?Currency $currency = null,
        public ?Billing $billing = null,
        public ?string $billingStatementPrefix = null,
        public ?string $nextBillingStatementSequenceNumber = null,
        public ?bool $livemode = null,
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
        $billing = Payload::object($data, 'billing');

        return new self(
            id: Payload::string($data, 'id'),
            name: Payload::nullableString($data, 'name'),
            email: Payload::nullableString($data, 'email'),
            currency: Payload::enum($data, 'currency', Currency::class),
            billing: $billing === null ? null : Billing::from($billing),
            billingStatementPrefix: Payload::nullableString($data, 'billing_statement_prefix'),
            nextBillingStatementSequenceNumber: Payload::nullableString($data, 'next_billing_statement_sequence_number'),
            livemode: Payload::nullableBool($data, 'livemode'),
            metadata: Payload::metadata($data),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
