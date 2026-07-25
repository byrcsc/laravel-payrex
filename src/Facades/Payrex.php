<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Facades;

use ByRcsc\LaravelPayrex\PayrexClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \ByRcsc\LaravelPayrex\Resources\PaymentIntents paymentIntents()
 * @method static \ByRcsc\LaravelPayrex\Resources\CheckoutSessions checkoutSessions()
 * @method static \ByRcsc\LaravelPayrex\Resources\SetupIntents setupIntents()
 * @method static \ByRcsc\LaravelPayrex\Resources\Customers customers()
 * @method static \ByRcsc\LaravelPayrex\Resources\CustomerSessions customerSessions()
 * @method static \ByRcsc\LaravelPayrex\Resources\Payments payments()
 * @method static \ByRcsc\LaravelPayrex\Resources\Refunds refunds()
 * @method static \ByRcsc\LaravelPayrex\Resources\Payouts payouts()
 * @method static \ByRcsc\LaravelPayrex\Resources\BillingStatements billingStatements()
 * @method static \ByRcsc\LaravelPayrex\Resources\BillingStatementLineItems billingStatementLineItems()
 * @method static \ByRcsc\LaravelPayrex\Resources\Webhooks webhooks()
 * @method static array<string, mixed> get(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> post(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> put(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> delete(string $uri, array<string, mixed> $params = [])
 * @method static string baseUrl()
 * @method static string|null publicKey()
 * @method static \ByRcsc\LaravelPayrex\Data\ApiResponseMetadata|null lastResponse()
 * @method static \ByRcsc\LaravelPayrex\Data\WebhookEvent parseEvent(string $payload, ?string $header)
 * @method static \Illuminate\Http\Client\PendingRequest pendingRequest()
 *
 * @see PayrexClient
 */
final class Payrex extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PayrexClient::class;
    }
}
