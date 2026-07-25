<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\CheckoutSession;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Enums\BillingDetailsCollection;
use ByRcsc\LaravelPayrex\Enums\CaptureType;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\InstallmentType;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Enums\SubmitType;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Generator;

/**
 * Checkout sessions - PayRex-hosted payment pages.
 */
final class CheckoutSessions extends Resource
{
    private const URI = '/checkout_sessions';

    /**
     * Creates a hosted checkout page. Send the payer to the session's `url`.
     *
     * @param  list<array<string, mixed>>  $lineItems
     * @param  list<PaymentMethodType|string>  $paymentMethods
     * @param  array<string, mixed>|null  $paymentMethodOptions  keyed by {@see PaymentMethodType}; see {@see CaptureType} and {@see InstallmentType}
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $paymentMethods = [],
        Currency $currency = Currency::PHP,
        ?string $description = null,
        ?string $customerReferenceId = null,
        SubmitType|string|null $submitType = null,
        BillingDetailsCollection|string|null $billingDetailsCollection = null,
        ?int $expiresAt = null,
        ?array $paymentMethodOptions = null,
        ?array $metadata = null,
        array $options = [],
    ): CheckoutSession {
        return CheckoutSession::from($this->client->post(self::URI, $this->payload([
            'line_items' => $lineItems,
            'payment_methods' => $paymentMethods,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'currency' => $currency,
            'description' => $description,
            'customer_reference_id' => $customerReferenceId,
            'submit_type' => $submitType,
            'billing_details_collection' => $billingDetailsCollection,
            'expires_at' => $expiresAt,
            'payment_method_options' => $paymentMethodOptions,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): CheckoutSession
    {
        return CheckoutSession::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Listing<CheckoutSession>
     */
    public function list(
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(self::URI, $this->listParams($limit, $before, $after, $options)),
            CheckoutSession::from(...),
        );
    }

    /**
     * Every checkout session, walked page by page.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, CheckoutSession>
     */
    public function autoPaging(int $limit = 100, array $options = []): Generator
    {
        return $this->walkPages(
            fn (?string $after) => $this->list(limit: $limit, after: $after, options: $options)
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return PayrexCursorPaginator<CheckoutSession>
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
     * Closes a session early so it can no longer be paid.
     *
     * @param  array<string, mixed>  $options
     */
    public function expire(string $id, array $options = []): CheckoutSession
    {
        return CheckoutSession::from($this->client->post($this->path(self::URI, $id, 'expire'), $options));
    }
}
