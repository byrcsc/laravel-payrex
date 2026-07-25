<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\SetupIntent;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;

/**
 * Setup intents — saving a payment method for later without charging it.
 */
final class SetupIntents extends Resource
{
    private const URI = '/setup_intents';

    /**
     * @param  list<PaymentMethodType|string>  $paymentMethods
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $customerId,
        array $paymentMethods = [],
        ?string $description = null,
        ?array $metadata = null,
        array $options = [],
    ): SetupIntent {
        return SetupIntent::from($this->client->post(self::URI, $this->payload([
            'payment_methods' => $paymentMethods,
            'customer_id' => $customerId,
            'description' => $description,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): SetupIntent
    {
        return SetupIntent::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function cancel(string $id, array $options = []): SetupIntent
    {
        return SetupIntent::from($this->client->post($this->path(self::URI, $id, 'cancel'), $options));
    }
}
