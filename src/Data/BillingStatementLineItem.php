<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

final readonly class BillingStatementLineItem
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $unitPrice = 0,
        public int $quantity = 0,
        public ?string $description = null,
        public ?string $billingStatementId = null,
        public bool $livemode = false,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            unitPrice: Payload::int($data, 'unit_price'),
            quantity: Payload::int($data, 'quantity'),
            description: Payload::nullableString($data, 'description'),
            billingStatementId: Payload::nullableString($data, 'billing_statement_id'),
            livemode: Payload::bool($data, 'livemode'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    /**
     * Unit price multiplied by quantity, in the smallest currency unit.
     */
    public function total(): int
    {
        return $this->unitPrice * $this->quantity;
    }
}
