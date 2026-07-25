<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Data\Deleted;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\PaymentMethod;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Generator;

/**
 * Customers and their saved payment methods.
 */
final class Customers extends Resource
{
    private const URI = '/customers';

    /**
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>|null  $billingDetails
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $name,
        string $email,
        Currency $currency = Currency::PHP,
        ?array $billingDetails = null,
        ?string $billingStatementPrefix = null,
        ?string $nextBillingStatementSequenceNumber = null,
        ?array $metadata = null,
        array $options = [],
    ): Customer {
        return Customer::from($this->client->post(self::URI, $this->payload([
            'name' => $name,
            'email' => $email,
            'currency' => $currency,
            'billing_details' => $billingDetails,
            'billing_statement_prefix' => $billingStatementPrefix,
            'next_billing_statement_sequence_number' => $nextBillingStatementSequenceNumber,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): Customer
    {
        return Customer::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>|null  $billingDetails
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $email = null,
        ?Currency $currency = null,
        ?array $billingDetails = null,
        ?string $billingStatementPrefix = null,
        ?string $nextBillingStatementSequenceNumber = null,
        ?array $metadata = null,
        array $options = [],
    ): Customer {
        return Customer::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'name' => $name,
            'email' => $email,
            'currency' => $currency,
            'billing_details' => $billingDetails,
            'billing_statement_prefix' => $billingStatementPrefix,
            'next_billing_statement_sequence_number' => $nextBillingStatementSequenceNumber,
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
     * @return Listing<Customer>
     */
    public function list(
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(self::URI, $this->listParams($limit, $before, $after, $options)),
            Customer::from(...),
        );
    }

    /**
     * Every customer, walked page by page.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, Customer>
     */
    public function autoPaging(int $limit = 100, array $options = []): Generator
    {
        return $this->walkPages(
            fn (?string $after) => $this->list(limit: $limit, after: $after, options: $options)
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return PayrexCursorPaginator<Customer>
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
     * @param  array<string, mixed>  $options
     * @return Listing<PaymentMethod>
     */
    public function listPaymentMethods(
        string $id,
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(
                $this->path(self::URI, $id, 'payment_methods'),
                $this->listParams($limit, $before, $after, $options),
            ),
            PaymentMethod::from(...),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function deletePaymentMethod(string $id, string $paymentMethodId, array $options = []): Deleted
    {
        return Deleted::from($this->client->delete(
            $this->path(self::URI, $id, 'payment_methods', $paymentMethodId),
            $options,
        ));
    }
}
