<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\BillingStatementLineItem;
use ByRcsc\LaravelPayrex\Data\Deleted;

/**
 * Line items on a draft billing statement.
 *
 * Once the parent statement is finalized its line items are locked.
 */
final class BillingStatementLineItems extends Resource
{
    private const URI = '/billing_statement_line_items';

    /**
     * `$unitPrice` is in the currency's smallest unit, so `10000` is ₱100.00.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $billingStatementId,
        int $unitPrice,
        int $quantity = 1,
        ?string $description = null,
        array $options = [],
    ): BillingStatementLineItem {
        return BillingStatementLineItem::from($this->client->post(self::URI, $this->payload([
            'billing_statement_id' => $billingStatementId,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'description' => $description,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): BillingStatementLineItem
    {
        return BillingStatementLineItem::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?int $unitPrice = null,
        ?int $quantity = null,
        ?string $description = null,
        array $options = [],
    ): BillingStatementLineItem {
        return BillingStatementLineItem::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'description' => $description,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function delete(string $id, array $options = []): Deleted
    {
        return Deleted::from($this->client->delete($this->path(self::URI, $id), $options));
    }
}
