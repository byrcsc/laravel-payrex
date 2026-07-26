<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Events\BillingStatementCreated;
use ByRcsc\LaravelPayrex\Events\BillingStatementDeleted;
use ByRcsc\LaravelPayrex\Events\BillingStatementFinalized;
use ByRcsc\LaravelPayrex\Events\BillingStatementLineItemCreated;
use ByRcsc\LaravelPayrex\Events\BillingStatementLineItemDeleted;
use ByRcsc\LaravelPayrex\Events\BillingStatementLineItemUpdated;
use ByRcsc\LaravelPayrex\Events\BillingStatementMarkedUncollectible;
use ByRcsc\LaravelPayrex\Events\BillingStatementOverdue;
use ByRcsc\LaravelPayrex\Events\BillingStatementPaid;
use ByRcsc\LaravelPayrex\Events\BillingStatementSent;
use ByRcsc\LaravelPayrex\Events\BillingStatementUpdated;
use ByRcsc\LaravelPayrex\Events\BillingStatementVoided;
use ByRcsc\LaravelPayrex\Events\BillingStatementWillBeDue;
use ByRcsc\LaravelPayrex\Events\CheckoutSessionExpired;
use ByRcsc\LaravelPayrex\Events\PaymentIntentAwaitingCapture;
use ByRcsc\LaravelPayrex\Events\PaymentIntentSucceeded;
use ByRcsc\LaravelPayrex\Events\PayoutCreated;
use ByRcsc\LaravelPayrex\Events\PayoutDeposited;
use ByRcsc\LaravelPayrex\Events\RefundCreated;
use ByRcsc\LaravelPayrex\Events\RefundUpdated;
use ByRcsc\LaravelPayrex\Events\SetupIntentSucceeded;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;

return [

    /*
    |--------------------------------------------------------------------------
    | Secret API key
    |--------------------------------------------------------------------------
    |
    | Your PayRex secret API key. It is sent as the username of an HTTP Basic
    | auth header on every request. Never expose this key to the browser.
    |
    */

    'secret_key' => env('PAYREX_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Publishable API key
    |--------------------------------------------------------------------------
    |
    | Your PayRex publishable key. Unlike the secret key this one is safe for
    | the browser - it is what PayRex Elements needs in order to tokenise card
    | details on the client. Reach it with `Payrex::publicKey()`.
    |
    */

    'public_key' => env('PAYREX_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook signing secret
    |--------------------------------------------------------------------------
    |
    | The signing secret of the webhook endpoint you registered with PayRex.
    | It is used to verify that inbound webhook payloads really came from
    | PayRex. You can find it on the webhook resource as `secret_key`.
    |
    */

    'webhook_secret' => env('PAYREX_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API base URL
    |--------------------------------------------------------------------------
    |
    | The root of the PayRex API. Override this to point the package at a
    | proxy or local stub during testing.
    |
    */

    'base_url' => env('PAYREX_BASE_URL', 'https://api.payrexhq.com'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    |
    | Timeouts (in seconds) applied to every outbound request, plus how many
    | times a failed GET request is retried and how long to wait between tries
    | (in milliseconds). Mutations are never retried because PayRex does not
    | document idempotency keys. Set `retry.times` to 1 to disable retries.
    |
    */

    'timeout' => (int) env('PAYREX_TIMEOUT', 30),

    'connect_timeout' => (int) env('PAYREX_CONNECT_TIMEOUT', 10),

    'retry' => [
        'times' => (int) env('PAYREX_RETRY_TIMES', 1),
        'sleep' => (int) env('PAYREX_RETRY_SLEEP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | `enabled`   registers the package webhook route. Turn it off if you would
    |             rather wire the controller into your own routes file.
    | `path`      the URI PayRex should POST to.
    | `tolerance` how many seconds a delivery may lag behind now before it is
    |             rejected as stale. Set to 0 to skip the timestamp check
    |             entirely. Listeners must be idempotent either way.
    |
    |             Five minutes is safe only because PayRex re-signs each retry
    |             with a fresh timestamp, so a stale delivery is never a real
    |             retry. See `WebhookSignature::DEFAULT_TOLERANCE`. Raise it if
    |             your server clock can drift more than this ahead of PayRex's.
    | `header`    the request header carrying the signature.
    |
    */

    'webhooks' => [

        'enabled' => (bool) env('PAYREX_WEBHOOKS_ENABLED', true),

        'path' => env('PAYREX_WEBHOOK_PATH', 'payrex/webhook'),

        'tolerance' => (int) env('PAYREX_WEBHOOK_TOLERANCE', WebhookSignature::DEFAULT_TOLERANCE),

        'header' => env('PAYREX_WEBHOOK_HEADER', 'Payrex-Signature'),

        /*
         * Middleware applied to the webhook route. The signature verification
         * middleware is always prepended, so you only need to list extras.
         */
        'middleware' => [],

        /*
         * Maps a PayRex event type to the Laravel event dispatched for it.
         *
         * These defaults match PayRex's documented event types, but PayRex may
         * add more at any time. A type not listed here still fires the generic
         * `PayrexWebhookReceived` event, which is dispatched for every verified
         * payload regardless of type - add your own entries rather than
         * waiting on a package release.
         */
        'events' => [
            'billing_statement.created' => BillingStatementCreated::class,
            'billing_statement.updated' => BillingStatementUpdated::class,
            'billing_statement.deleted' => BillingStatementDeleted::class,
            'billing_statement.finalized' => BillingStatementFinalized::class,
            'billing_statement.sent' => BillingStatementSent::class,
            'billing_statement.marked_uncollectible' => BillingStatementMarkedUncollectible::class,
            'billing_statement.voided' => BillingStatementVoided::class,
            'billing_statement.paid' => BillingStatementPaid::class,
            'billing_statement.will_be_due' => BillingStatementWillBeDue::class,
            'billing_statement.overdue' => BillingStatementOverdue::class,
            'billing_statement_line_item.created' => BillingStatementLineItemCreated::class,
            'billing_statement_line_item.updated' => BillingStatementLineItemUpdated::class,
            'billing_statement_line_item.deleted' => BillingStatementLineItemDeleted::class,
            'checkout_session.expired' => CheckoutSessionExpired::class,
            'payment_intent.awaiting_capture' => PaymentIntentAwaitingCapture::class,
            'payment_intent.succeeded' => PaymentIntentSucceeded::class,
            'setup_intent.succeeded' => SetupIntentSucceeded::class,
            'payout.created' => PayoutCreated::class,
            'payout.deposited' => PayoutDeposited::class,
            'refund.created' => RefundCreated::class,
            'refund.updated' => RefundUpdated::class,
        ],
    ],

];
