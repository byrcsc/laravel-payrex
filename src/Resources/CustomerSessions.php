<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\CustomerSession;

/**
 * Customer sessions - short-lived client secrets for PayRex embedded
 * components, letting a customer manage their own saved payment methods.
 */
final class CustomerSessions extends Resource
{
    private const URI = '/customer_sessions';

    /**
     * @param  array<string, mixed>  $options
     */
    public function create(string $customerId, array $options = []): CustomerSession
    {
        return CustomerSession::from($this->client->post(self::URI, $this->payload([
            'customer_id' => $customerId,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): CustomerSession
    {
        return CustomerSession::from($this->client->get($this->path(self::URI, $id), $options));
    }
}
