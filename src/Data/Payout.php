<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\PayoutStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A transfer of settled funds from PayRex to the merchant's bank account.
 */
final readonly class Payout
{
    /**
     * @param  array<string, mixed>|null  $destination
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public ?int $netAmount = null,
        public ?PayoutStatus $status = null,
        public ?array $destination = null,
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
            amount: Payload::int($data, 'amount'),
            netAmount: Payload::nullableInt($data, 'net_amount'),
            status: Payload::enum($data, 'status', PayoutStatus::class),
            destination: Payload::object($data, 'destination'),
            livemode: Payload::bool($data, 'livemode'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
