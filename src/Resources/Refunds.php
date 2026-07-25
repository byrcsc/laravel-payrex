<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Refund;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\RefundReason;

/**
 * Refunds against settled payments.
 */
final class Refunds extends Resource
{
    private const URI = '/refunds';

    /**
     * Refunds `$amount` of a payment. Pass the full payment amount for a total
     * refund; anything less is a partial refund.
     *
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        int $amount,
        string $paymentId,
        RefundReason $reason,
        Currency $currency = Currency::PHP,
        ?string $description = null,
        ?string $remarks = null,
        ?array $metadata = null,
        array $options = [],
    ): Refund {
        return Refund::from($this->client->post(self::URI, $this->payload([
            'amount' => $amount,
            'payment_id' => $paymentId,
            'reason' => $reason,
            'currency' => $currency,
            'description' => $description,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?array $metadata = null,
        array $options = [],
    ): Refund {
        return Refund::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'metadata' => $metadata,
        ], $options)));
    }
}
