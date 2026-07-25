<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\RefundReason;
use ByRcsc\LaravelPayrex\Enums\RefundStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

final readonly class Refund
{
    /**
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public ?Currency $currency = null,
        public ?RefundStatus $status = null,
        public ?RefundReason $reason = null,
        public ?string $paymentId = null,
        public ?string $description = null,
        public ?string $remarks = null,
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
        return new self(
            id: Payload::string($data, 'id'),
            amount: Payload::int($data, 'amount'),
            currency: Payload::enum($data, 'currency', Currency::class),
            status: Payload::enum($data, 'status', RefundStatus::class),
            reason: Payload::enum($data, 'reason', RefundReason::class),
            paymentId: Payload::nullableString($data, 'payment_id'),
            description: Payload::nullableString($data, 'description'),
            remarks: Payload::nullableString($data, 'remarks'),
            livemode: Payload::nullableBool($data, 'livemode'),
            metadata: Payload::metadata($data),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
