<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Payment;

/**
 * Payments — the charges produced by payment intents.
 *
 * Payments are created by charging a payment intent, never directly, so this
 * resource is read-and-annotate only.
 */
final class Payments extends Resource
{
    private const URI = '/payments';

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): Payment
    {
        return Payment::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?string $description = null,
        ?array $metadata = null,
        array $options = [],
    ): Payment {
        return Payment::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'description' => $description,
            'metadata' => $metadata,
        ], $options)));
    }
}
