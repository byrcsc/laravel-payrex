<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Facades;

use ByRcsc\LaravelPayrex\Data\ApiResponseMetadata;
use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\PayrexClient;
use ByRcsc\LaravelPayrex\Resources\BillingStatementLineItems;
use ByRcsc\LaravelPayrex\Resources\BillingStatements;
use ByRcsc\LaravelPayrex\Resources\CheckoutSessions;
use ByRcsc\LaravelPayrex\Resources\Customers;
use ByRcsc\LaravelPayrex\Resources\CustomerSessions;
use ByRcsc\LaravelPayrex\Resources\PaymentIntents;
use ByRcsc\LaravelPayrex\Resources\Payments;
use ByRcsc\LaravelPayrex\Resources\Payouts;
use ByRcsc\LaravelPayrex\Resources\Refunds;
use ByRcsc\LaravelPayrex\Resources\SetupIntents;
use ByRcsc\LaravelPayrex\Resources\Webhooks;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PaymentIntents paymentIntents()
 * @method static CheckoutSessions checkoutSessions()
 * @method static SetupIntents setupIntents()
 * @method static Customers customers()
 * @method static CustomerSessions customerSessions()
 * @method static Payments payments()
 * @method static Refunds refunds()
 * @method static Payouts payouts()
 * @method static BillingStatements billingStatements()
 * @method static BillingStatementLineItems billingStatementLineItems()
 * @method static Webhooks webhooks()
 * @method static array<string, mixed> get(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> post(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> put(string $uri, array<string, mixed> $params = [])
 * @method static array<string, mixed> delete(string $uri, array<string, mixed> $params = [])
 * @method static string baseUrl()
 * @method static ?string publicKey()
 * @method static ?ApiResponseMetadata lastResponse()
 * @method static WebhookEvent parseEvent(string $payload, ?string $header)
 * @method static PendingRequest pendingRequest()
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
