<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\PaymentMethod;
use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;
use ByRcsc\LaravelPayrex\Enums\BillingStatementStatus;
use ByRcsc\LaravelPayrex\Enums\CheckoutSessionStatus;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\PayoutTransactionType;
use ByRcsc\LaravelPayrex\Enums\RefundReason;
use ByRcsc\LaravelPayrex\Enums\RefundStatus;
use ByRcsc\LaravelPayrex\Enums\SetupIntentStatus;
use ByRcsc\LaravelPayrex\Enums\SubmitType;
use ByRcsc\LaravelPayrex\Enums\WebhookStatus;
use ByRcsc\LaravelPayrex\Facades\Payrex;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stubs every endpoint with one payload.
 *
 * Laravel's `Http::fake()` pushes stubs rather than replacing them, and the
 * first match wins — so each test faking exactly once keeps things honest.
 *
 * @param  array<string, mixed>|string  $response
 */
function fakePayrex(array|string $response = ['id' => 'obj_1'], int $status = 200): void
{
    Http::fake(['*' => Http::response($response, $status)]);
}

/**
 * Asserts the request that was sent: verb, URL, and decoded form body.
 *
 * @param  array<string, mixed>  $body
 */
function assertCalled(string $method, string $uri, array $body = []): void
{
    Http::assertSent(function (Request $request) use ($method, $uri, $body) {
        expect($request->method())->toBe($method)
            ->and($request->url())->toBe("https://api.payrexhq.test{$uri}")
            ->and(formBody($request->body()))->toBe($body);

        return true;
    });
}

describe('checkout sessions', function () {
    it('creates a hosted session', function () {
        fakePayrex();

        Payrex::checkoutSessions()->create(
            lineItems: [['name' => 'Sticker pack', 'amount' => 10_000, 'quantity' => 1]],
            paymentMethods: ['card', 'gcash'],
            successUrl: 'https://shop.test/success',
            cancelUrl: 'https://shop.test/cancel',
            description: 'Sticker order',
            submitType: SubmitType::Pay,
        );

        // Asserted on the wire format rather than via parse_str, because line
        // items are a list of objects -- see FormEncoderTest for why PHP cannot
        // round-trip that encoding.
        Http::assertSent(function (Request $request) {
            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe('https://api.payrexhq.test/checkout_sessions')
                ->and(urldecode($request->body()))->toBe(
                    'line_items[][name]=Sticker pack'
                    .'&line_items[][amount]=10000'
                    .'&line_items[][quantity]=1'
                    .'&payment_methods[]=card'
                    .'&payment_methods[]=gcash'
                    .'&success_url=https://shop.test/success'
                    .'&cancel_url=https://shop.test/cancel'
                    .'&currency=PHP'
                    .'&description=Sticker order'
                    .'&submit_type=pay'
                );

            return true;
        });
    });

    it('retrieves a session', function () {
        fakePayrex();

        Payrex::checkoutSessions()->retrieve('cs_1');

        assertCalled('GET', '/checkout_sessions/cs_1');
    });

    it('lists sessions', function () {
        fakePayrex(['data' => [], 'has_more' => false]);

        Payrex::checkoutSessions()->list(limit: 10);

        assertCalled('GET', '/checkout_sessions?limit=10');
    });

    it('expires a session', function () {
        fakePayrex();

        Payrex::checkoutSessions()->expire('cs_1');

        assertCalled('POST', '/checkout_sessions/cs_1/expire');
    });

    it('decodes a session', function () {
        fakePayrex([
            'id' => 'cs_1',
            'url' => 'https://checkout.payrexhq.com/cs_1',
            'status' => 'completed',
            'amount' => 10000,
            'currency' => 'PHP',
            'submit_type' => 'pay',
            'billing_details_collection' => 'always',
            'payment_method_options' => ['card' => ['capture_type' => 'automatic']],
            'statement_descriptor' => 'BYRCSC',
            'line_items' => [['name' => 'Sticker pack', 'amount' => 10000]],
            'payment_methods' => ['card'],
        ]);

        $session = Payrex::checkoutSessions()->retrieve('cs_1');

        expect($session->url)->toBe('https://checkout.payrexhq.com/cs_1')
            ->and($session->status)->toBe(CheckoutSessionStatus::Completed)
            ->and($session->isCompleted())->toBeTrue()
            ->and($session->amount)->toBe(10000)
            ->and($session->currency)->toBe(Currency::PHP)
            ->and($session->submitType)->toBe(SubmitType::Pay)
            ->and($session->billingDetailsCollection?->value)->toBe('always')
            ->and($session->paymentMethodOptions)->toBe(['card' => ['capture_type' => 'automatic']])
            ->and($session->statementDescriptor)->toBe('BYRCSC')
            ->and($session->lineItems)->toBe([['name' => 'Sticker pack', 'amount' => 10000]]);
    });
});

describe('setup intents', function () {
    it('creates a setup intent', function () {
        fakePayrex();

        Payrex::setupIntents()->create(
            customerId: 'cus_1',
            paymentMethods: ['card'],
        );

        assertCalled('POST', '/setup_intents', [
            'payment_methods' => ['card'],
            'customer_id' => 'cus_1',
        ]);
    });

    it('retrieves and decodes a setup intent', function () {
        fakePayrex(['id' => 'seti_1', 'status' => 'succeeded']);

        expect(Payrex::setupIntents()->retrieve('seti_1')->status)
            ->toBe(SetupIntentStatus::Succeeded);

        assertCalled('GET', '/setup_intents/seti_1');
    });

    it('cancels a setup intent', function () {
        fakePayrex();

        Payrex::setupIntents()->cancel('seti_1');

        assertCalled('POST', '/setup_intents/seti_1/cancel');
    });
});

describe('resource paths and options', function () {
    it('encodes each resource id as one path segment', function () {
        fakePayrex();

        Payrex::payments()->retrieve('pay/1?expand=true');

        assertCalled('GET', '/payments/pay%2F1%3Fexpand%3Dtrue');
    });

    it('rejects empty and traversal-only resource ids', function (string $id) {
        Payrex::payments()->retrieve($id);
    })->with(['', ' ', '.', '..'])
        ->throws(InvalidArgumentException::class);

    it('passes options through retrieve delete and lifecycle methods', function (string $operation) {
        fakePayrex(['id' => 'obj_1', 'deleted' => true]);

        if ($operation === 'retrieve') {
            Payrex::customers()->retrieve('cus_1', ['expand' => 'payment_methods']);
            assertCalled('GET', '/customers/cus_1?expand=payment_methods');

            return;
        }

        if ($operation === 'delete') {
            Payrex::customers()->delete('cus_1', ['reason' => 'duplicate']);
            assertCalled('DELETE', '/customers/cus_1?reason=duplicate');

            return;
        }

        Payrex::paymentIntents()->cancel('pi_1', ['reason' => 'requested']);
        assertCalled('POST', '/payment_intents/pi_1/cancel', ['reason' => 'requested']);
    })->with(['retrieve', 'delete', 'lifecycle']);
});

describe('customers', function () {
    it('creates a customer', function () {
        fakePayrex();

        Payrex::customers()->create(name: 'Juan dela Cruz', email: 'juan@example.com');

        assertCalled('POST', '/customers', [
            'name' => 'Juan dela Cruz',
            'email' => 'juan@example.com',
            'currency' => 'PHP',
        ]);
    });

    it('updates a customer with a put', function () {
        fakePayrex();

        Payrex::customers()->update('cus_1', email: 'new@example.com');

        assertCalled('PUT', '/customers/cus_1', ['email' => 'new@example.com']);
    });

    it('sends and decodes documented customer billing fields', function () {
        fakePayrex([
            'id' => 'cus_1',
            'billing' => [
                'phone' => '+639171234567',
                'address' => ['country' => 'PH'],
            ],
            'next_billing_statement_sequence_number' => '0007',
        ]);

        $customer = Payrex::customers()->create(
            name: 'Juan dela Cruz',
            email: 'juan@example.com',
            billingDetails: [
                'phone' => '+639171234567',
                'address' => ['country' => 'PH'],
            ],
            nextBillingStatementSequenceNumber: '0007',
        );

        expect($customer->billing?->phone)->toBe('+639171234567')
            ->and($customer->billing?->address?->country)->toBe('PH')
            ->and($customer->nextBillingStatementSequenceNumber)->toBe('0007');

        assertCalled('POST', '/customers', [
            'name' => 'Juan dela Cruz',
            'email' => 'juan@example.com',
            'currency' => 'PHP',
            'billing_details' => [
                'phone' => '+639171234567',
                'address' => ['country' => 'PH'],
            ],
            'next_billing_statement_sequence_number' => '0007',
        ]);
    });

    it('deletes a customer', function () {
        fakePayrex(['id' => 'cus_1', 'deleted' => true]);

        $deleted = Payrex::customers()->delete('cus_1');

        expect($deleted->id)->toBe('cus_1')->and($deleted->deleted)->toBeTrue();
        assertCalled('DELETE', '/customers/cus_1');
    });

    it('lists customers into a typed listing', function () {
        fakePayrex([
            'data' => [
                ['id' => 'cus_1', 'email' => 'a@example.com'],
                ['id' => 'cus_2', 'email' => 'b@example.com'],
            ],
            'has_more' => true,
        ]);

        $customers = Payrex::customers()->list(limit: 2, after: 'cus_0');

        expect($customers)->toBeInstanceOf(Listing::class)
            ->and($customers)->toHaveCount(2)
            ->and($customers->hasMore)->toBeTrue()
            ->and($customers->isEmpty())->toBeFalse()
            ->and($customers->first())->toBeInstanceOf(Customer::class)
            ->and($customers->first()?->email)->toBe('a@example.com')
            ->and($customers->collect()->pluck('id')->all())->toBe(['cus_1', 'cus_2']);

        foreach ($customers as $customer) {
            expect($customer)->toBeInstanceOf(Customer::class);
        }

        assertCalled('GET', '/customers?limit=2&after=cus_0');
    });

    it('lists a customer payment methods', function () {
        fakePayrex(['data' => [['id' => 'pm_1', 'type' => 'card']], 'has_more' => false]);

        expect(Payrex::customers()->listPaymentMethods('cus_1')->first())
            ->toBeInstanceOf(PaymentMethod::class);

        assertCalled('GET', '/customers/cus_1/payment_methods');
    });

    it('deletes a customer payment method', function () {
        fakePayrex(['id' => 'pm_1', 'deleted' => true]);

        Payrex::customers()->deletePaymentMethod('cus_1', 'pm_1');

        assertCalled('DELETE', '/customers/cus_1/payment_methods/pm_1');
    });
});

describe('customer sessions', function () {
    it('creates a customer session', function () {
        fakePayrex();

        Payrex::customerSessions()->create('cus_1');

        assertCalled('POST', '/customer_sessions', ['customer_id' => 'cus_1']);
    });

    it('retrieves a customer session', function () {
        fakePayrex([
            'id' => 'cus_s_1',
            'expired' => false,
            'client_secret' => 'cus_s_secret',
            'components' => [
                ['component' => 'payment_element', 'feature' => 'payment_method_save', 'value' => 'enabled'],
            ],
        ]);

        $session = Payrex::customerSessions()->retrieve('cus_s_1');

        expect($session->expired)->toBeFalse()
            ->and($session->clientSecret)->toBe('cus_s_secret')
            ->and($session->components)->toHaveCount(1)
            ->and($session->components[0]['feature'])->toBe('payment_method_save');
    });
});

describe('payments', function () {
    it('retrieves and decodes the documented payment summary', function () {
        fakePayrex([
            'id' => 'pay_1',
            'origin' => 'api',
            'payment_method' => [
                'type' => 'card',
                'card' => ['first6' => '511111', 'last4' => '1111', 'brand' => 'visa'],
            ],
        ]);

        $payment = Payrex::payments()->retrieve('pay_1');

        expect($payment->origin)->toBe('api')
            ->and($payment->paymentMethod?->type?->value)->toBe('card')
            ->and($payment->paymentMethod?->details['last4'])->toBe('1111');
        assertCalled('GET', '/payments/pay_1');
    });

    it('updates a payment with a put', function () {
        fakePayrex();

        Payrex::payments()->update('pay_1', description: 'Order #9', metadata: ['ref' => '9']);

        assertCalled('PUT', '/payments/pay_1', [
            'description' => 'Order #9',
            'metadata' => ['ref' => '9'],
        ]);
    });
});

describe('refunds', function () {
    it('creates a refund', function () {
        fakePayrex();

        Payrex::refunds()->create(
            amount: 10_000,
            paymentId: 'pay_1',
            reason: RefundReason::RequestedByCustomer,
            remarks: 'Wrong size',
        );

        assertCalled('POST', '/refunds', [
            'amount' => '10000',
            'payment_id' => 'pay_1',
            'reason' => 'requested_by_customer',
            'currency' => 'PHP',
            'remarks' => 'Wrong size',
        ]);
    });

    it('updates and decodes a refund', function () {
        fakePayrex([
            'id' => 're_1',
            'amount' => 10000,
            'status' => 'succeeded',
            'reason' => 'fraudulent',
            'payment_id' => 'pay_1',
        ]);

        $refund = Payrex::refunds()->update('re_1', metadata: ['case' => 'chargeback']);

        expect($refund->status)->toBe(RefundStatus::Succeeded)
            ->and($refund->reason)->toBe(RefundReason::Fraudulent)
            ->and($refund->paymentId)->toBe('pay_1');

        assertCalled('PUT', '/refunds/re_1', ['metadata' => ['case' => 'chargeback']]);
    });
});

describe('payouts', function () {
    it('lists payout transactions', function () {
        fakePayrex([
            'data' => [['id' => 'po_txn_1', 'amount' => 10000, 'net_amount' => 9650, 'transaction_type' => 'payment']],
            'has_more' => false,
        ]);

        $transactions = Payrex::payouts()->listTransactions('po_1', limit: 50);

        expect($transactions->first()?->transactionType)->toBe(PayoutTransactionType::Payment)
            ->and($transactions->first()?->netAmount)->toBe(9650);

        assertCalled('GET', '/payouts/po_1/transactions?limit=50');
    });
});

describe('billing statements', function () {
    it('creates a draft statement', function () {
        fakePayrex();

        Payrex::billingStatements()->create(
            customerId: 'cus_1',
            description: 'July services',
            paymentSettings: ['payment_methods' => ['gcash', 'card']],
        );

        assertCalled('POST', '/billing_statements', [
            'customer_id' => 'cus_1',
            'currency' => 'PHP',
            'description' => 'July services',
            'payment_settings' => ['payment_methods' => ['gcash', 'card']],
        ]);
    });

    it('walks the lifecycle transitions', function (string $method, string $segment) {
        fakePayrex(['id' => 'bs_1', 'status' => 'open']);

        Payrex::billingStatements()->{$method}('bs_1');

        assertCalled('POST', "/billing_statements/bs_1/{$segment}");
    })->with([
        ['finalize', 'finalize'],
        ['void', 'void'],
        ['markUncollectible', 'mark_uncollectible'],
    ]);

    it('sends a statement even though the api returns no body', function () {
        fakePayrex('');

        expect(Payrex::billingStatements()->send('bs_1'))->toBeNull();

        assertCalled('POST', '/billing_statements/bs_1/send');
    });

    it('decodes a statement returned by the send endpoint', function () {
        fakePayrex(['id' => 'bs_1', 'status' => 'open', 'url' => 'https://payrex.test/bs_1']);

        $statement = Payrex::billingStatements()->send('bs_1');

        expect($statement?->id)->toBe('bs_1')
            ->and($statement?->billingStatementUrl)->toBe('https://payrex.test/bs_1');
    });

    it('decodes nested line items', function () {
        fakePayrex([
            'id' => 'bs_1',
            'amount' => 30000,
            'status' => 'draft',
            'line_items' => [
                ['id' => 'bsli_1', 'unit_price' => 10000, 'quantity' => 3, 'description' => 'Consulting'],
            ],
        ]);

        $statement = Payrex::billingStatements()->retrieve('bs_1');

        expect($statement->status)->toBe(BillingStatementStatus::Draft)
            ->and($statement->isDraft())->toBeTrue()
            ->and($statement->lineItems)->toHaveCount(1)
            ->and($statement->lineItems[0]->description)->toBe('Consulting')
            ->and($statement->lineItems[0]->total())->toBe(30000);
    });

    it('lists statements', function () {
        fakePayrex(['data' => [['id' => 'bs_1']], 'has_more' => false]);

        expect(Payrex::billingStatements()->list())->toHaveCount(1);
    });

    it('deletes a statement', function () {
        fakePayrex(['id' => 'bs_1', 'deleted' => true]);

        expect(Payrex::billingStatements()->delete('bs_1')->deleted)->toBeTrue();
        assertCalled('DELETE', '/billing_statements/bs_1');
    });
});

describe('billing statement line items', function () {
    it('creates a line item', function () {
        fakePayrex();

        Payrex::billingStatementLineItems()->create(
            billingStatementId: 'bs_1',
            unitPrice: 10_000,
            quantity: 3,
            description: 'Consulting',
        );

        assertCalled('POST', '/billing_statement_line_items', [
            'billing_statement_id' => 'bs_1',
            'unit_price' => '10000',
            'quantity' => '3',
            'description' => 'Consulting',
        ]);
    });

    it('updates a line item', function () {
        fakePayrex(['id' => 'bsli_1', 'unit_price' => 5000, 'quantity' => 1]);

        expect(Payrex::billingStatementLineItems()->update('bsli_1', unitPrice: 5_000)->total())
            ->toBe(5000);

        assertCalled('PUT', '/billing_statement_line_items/bsli_1', ['unit_price' => '5000']);
    });

    it('retrieves a line item', function () {
        fakePayrex();

        Payrex::billingStatementLineItems()->retrieve('bsli_1');

        assertCalled('GET', '/billing_statement_line_items/bsli_1');
    });

    it('deletes a line item', function () {
        fakePayrex(['id' => 'bsli_1', 'deleted' => true]);

        expect(Payrex::billingStatementLineItems()->delete('bsli_1')->deleted)->toBeTrue();
        assertCalled('DELETE', '/billing_statement_line_items/bsli_1');
    });
});

describe('webhook endpoints', function () {
    it('creates an endpoint', function () {
        fakePayrex();

        Payrex::webhooks()->create(
            url: 'https://shop.test/payrex/webhook',
            events: ['payment_intent.succeeded', 'refund.updated'],
            description: 'Primary',
        );

        assertCalled('POST', '/webhooks', [
            'url' => 'https://shop.test/payrex/webhook',
            'events' => ['payment_intent.succeeded', 'refund.updated'],
            'description' => 'Primary',
        ]);
    });

    it('decodes an endpoint and its signing secret', function () {
        fakePayrex([
            'id' => 'wh_1',
            'url' => 'https://shop.test/payrex/webhook',
            'status' => 'enabled',
            'secret_key' => 'whsk_test_abc',
            'events' => ['payment_intent.succeeded'],
        ]);

        $endpoint = Payrex::webhooks()->retrieve('wh_1');

        expect($endpoint)->toBeInstanceOf(WebhookEndpoint::class)
            ->and($endpoint->status)->toBe(WebhookStatus::Enabled)
            ->and($endpoint->isEnabled())->toBeTrue()
            ->and($endpoint->secretKey)->toBe('whsk_test_abc')
            ->and($endpoint->events)->toBe(['payment_intent.succeeded']);
    });

    it('toggles an endpoint', function (string $method) {
        fakePayrex();

        Payrex::webhooks()->{$method}('wh_1');

        assertCalled('POST', "/webhooks/wh_1/{$method}");
    })->with(['enable', 'disable']);

    it('updates an endpoint', function () {
        fakePayrex();

        Payrex::webhooks()->update('wh_1', events: ['refund.created']);

        assertCalled('PUT', '/webhooks/wh_1', ['events' => ['refund.created']]);
    });

    it('lists endpoints', function () {
        fakePayrex(['data' => [['id' => 'wh_1']], 'has_more' => false]);

        expect(Payrex::webhooks()->list())->toHaveCount(1);
    });

    it('deletes an endpoint', function () {
        fakePayrex(['id' => 'wh_1', 'deleted' => true]);

        expect(Payrex::webhooks()->delete('wh_1')->deleted)->toBeTrue();
        assertCalled('DELETE', '/webhooks/wh_1');
    });
});
