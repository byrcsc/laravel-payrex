<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\BillingStatement;
use ByRcsc\LaravelPayrex\Data\Deleted;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Enums\BillingDetailsCollection;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Generator;

/**
 * Billing statements — itemised bills PayRex can send to a customer.
 *
 * The lifecycle is: create a draft, add line items with
 * {@see BillingStatementLineItems}, `finalize()` to lock it, then `send()` it.
 * A finalized statement can be `void()`ed or written off with
 * `markUncollectible()`.
 */
final class BillingStatements extends Resource
{
    private const URI = '/billing_statements';

    /**
     * @param  array<string, mixed>|null  $paymentSettings
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $customerId,
        Currency $currency = Currency::PHP,
        ?string $description = null,
        BillingDetailsCollection|string|null $billingDetailsCollection = null,
        ?array $paymentSettings = null,
        ?array $metadata = null,
        array $options = [],
    ): BillingStatement {
        return BillingStatement::from($this->client->post(self::URI, $this->payload([
            'customer_id' => $customerId,
            'currency' => $currency,
            'description' => $description,
            'billing_details_collection' => $billingDetailsCollection,
            'payment_settings' => $paymentSettings,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): BillingStatement
    {
        return BillingStatement::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * Only a draft statement can be updated.
     *
     * @param  array<string, mixed>|null  $paymentSettings
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?string $customerId = null,
        ?string $description = null,
        BillingDetailsCollection|string|null $billingDetailsCollection = null,
        ?int $dueAt = null,
        ?array $paymentSettings = null,
        ?array $metadata = null,
        array $options = [],
    ): BillingStatement {
        return BillingStatement::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'customer_id' => $customerId,
            'description' => $description,
            'billing_details_collection' => $billingDetailsCollection,
            'due_at' => $dueAt,
            'payment_settings' => $paymentSettings,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function delete(string $id, array $options = []): Deleted
    {
        return Deleted::from($this->client->delete($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Listing<BillingStatement>
     */
    public function list(
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(self::URI, $this->listParams($limit, $before, $after, $options)),
            BillingStatement::from(...),
        );
    }

    /**
     * Every billing statement, walked page by page.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, BillingStatement>
     */
    public function autoPaging(int $limit = 100, array $options = []): Generator
    {
        return $this->walkPages(
            fn (?string $after) => $this->list(limit: $limit, after: $after, options: $options)
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return PayrexCursorPaginator<BillingStatement>
     */
    public function paginate(
        int $perPage = 10,
        ?string $cursorName = null,
        ?string $path = null,
        array $options = [],
    ): PayrexCursorPaginator {
        return $this->cursorPaginate(
            fn (?string $before, ?string $after, int $limit) => $this->list(
                limit: $limit,
                before: $before,
                after: $after,
                options: $options,
            ),
            perPage: $perPage,
            cursorName: $cursorName,
            path: $path,
        );
    }

    /**
     * Locks the statement's contents and makes it payable.
     *
     * @param  array<string, mixed>  $options
     */
    public function finalize(string $id, array $options = []): BillingStatement
    {
        return BillingStatement::from($this->client->post($this->path(self::URI, $id, 'finalize'), $options));
    }

    /**
     * Emails the statement to the customer. The API reference documents a
     * resource response, while the official SDK permits an empty response.
     *
     * @param  array<string, mixed>  $options
     */
    public function send(string $id, array $options = []): ?BillingStatement
    {
        $response = $this->client->post($this->path(self::URI, $id, 'send'), $options);

        return $response === [] ? null : BillingStatement::from($response);
    }

    /**
     * Cancels a finalized statement that should never have been issued.
     *
     * @param  array<string, mixed>  $options
     */
    public function void(string $id, array $options = []): BillingStatement
    {
        return BillingStatement::from($this->client->post($this->path(self::URI, $id, 'void'), $options));
    }

    /**
     * Writes off a statement the customer is not going to pay.
     *
     * @param  array<string, mixed>  $options
     */
    public function markUncollectible(string $id, array $options = []): BillingStatement
    {
        return BillingStatement::from($this->client->post(
            $this->path(self::URI, $id, 'mark_uncollectible'),
            $options,
        ));
    }
}
