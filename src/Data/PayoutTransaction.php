<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\PayoutTransactionType;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * One line of a payout — the payment or refund that contributed to it.
 */
final readonly class PayoutTransaction
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public ?int $netAmount = null,
        public ?PayoutTransactionType $transactionType = null,
        public ?string $transactionId = null,
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
            transactionType: Payload::enum($data, 'transaction_type', PayoutTransactionType::class),
            transactionId: Payload::nullableString($data, 'transaction_id'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
