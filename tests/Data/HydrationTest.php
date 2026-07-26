<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\Billing;
use ByRcsc\LaravelPayrex\Data\BillingAddress;
use ByRcsc\LaravelPayrex\Data\BillingStatement;
use ByRcsc\LaravelPayrex\Data\BillingStatementLineItem;
use ByRcsc\LaravelPayrex\Data\CheckoutSession;
use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Data\CustomerSession;
use ByRcsc\LaravelPayrex\Data\Deleted;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\Payment;
use ByRcsc\LaravelPayrex\Data\PaymentIntent;
use ByRcsc\LaravelPayrex\Data\PaymentMethod;
use ByRcsc\LaravelPayrex\Data\PaymentMethodSummary;
use ByRcsc\LaravelPayrex\Data\Payout;
use ByRcsc\LaravelPayrex\Data\PayoutTransaction;
use ByRcsc\LaravelPayrex\Data\PayrexError;
use ByRcsc\LaravelPayrex\Data\Refund;
use ByRcsc\LaravelPayrex\Data\SetupIntent;
use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;
use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\Enums\BillingDetailsCollection;
use ByRcsc\LaravelPayrex\Enums\BillingStatementStatus;
use ByRcsc\LaravelPayrex\Enums\CheckoutSessionStatus;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Enums\PaymentStatus;
use ByRcsc\LaravelPayrex\Enums\PayoutStatus;
use ByRcsc\LaravelPayrex\Enums\PayoutTransactionType;
use ByRcsc\LaravelPayrex\Enums\RefundReason;
use ByRcsc\LaravelPayrex\Enums\RefundStatus;
use ByRcsc\LaravelPayrex\Enums\SetupIntentStatus;
use ByRcsc\LaravelPayrex\Enums\SubmitType;
use ByRcsc\LaravelPayrex\Enums\WebhookStatus;
use Illuminate\Support\Str;

/*
 * One fixture per DTO, hydrated and asserted field by field.
 *
 * The DTOs read every value through `Payload`, which degrades to a default
 * rather than throwing - so a renamed or retyped field does not blow up, it
 * quietly turns into null. These tests are what turns that silence into a
 * failure when a fixture is updated to match a changed API response.
 */

describe('BillingAddress', function () {
    it('maps every field', function () {
        $address = BillingAddress::from(payload('billing_address'));

        expect($address->line1)->toBe('12 Analytical Engine Street')
            ->and($address->line2)->toBe('Unit 4B')
            ->and($address->city)->toBe('Makati')
            ->and($address->state)->toBe('Metro Manila')
            ->and($address->postalCode)->toBe('1226')
            ->and($address->country)->toBe('PH')
            ->and($address->raw)->toBe(payload('billing_address'));
    });

    it('leaves absent fields null', function () {
        $address = BillingAddress::from([]);

        expect($address->line1)->toBeNull()
            ->and($address->country)->toBeNull();
    });
});

describe('Billing', function () {
    it('maps every field and nests the address', function () {
        $billing = Billing::from(payload('billing'));

        expect($billing->name)->toBe('Joe Dela Cruz')
            ->and($billing->email)->toBe('joe@example.test')
            ->and($billing->phone)->toBe('+639171234567')
            ->and($billing->address)->toBeInstanceOf(BillingAddress::class)
            ->and($billing->address?->city)->toBe('Makati');
    });

    it('leaves the address null when the payload carries none', function () {
        expect(Billing::from(['name' => 'Joe'])->address)->toBeNull();
    });
});

describe('Customer', function () {
    it('maps every field', function () {
        $customer = Customer::from(payload('customer'));

        expect($customer->id)->toBe('cus_3QxSample000001')
            ->and($customer->name)->toBe('Joe Dela Cruz')
            ->and($customer->email)->toBe('joe@example.test')
            ->and($customer->currency)->toBe(Currency::PHP)
            ->and($customer->billing?->address?->country)->toBe('PH')
            ->and($customer->billingStatementPrefix)->toBe('ACME')
            ->and($customer->nextBillingStatementSequenceNumber)->toBe('0007')
            ->and($customer->deleted)->toBeFalse()
            ->and($customer->isDeleted())->toBeFalse()
            ->and($customer->livemode)->toBeFalse()
            ->and($customer->metadata)->toBe(['user_id' => '42'])
            ->and($customer->createdAt?->getTimestamp())->toBe(1753420800)
            ->and($customer->updatedAt?->getTimestamp())->toBe(1753420860);
    });

    it('reads billing from the billing key only', function () {
        expect(Customer::from(['id' => 'cus_1', 'billing' => ['name' => 'Joe']])->billing?->name)
            ->toBe('Joe')
            ->and(Customer::from(['id' => 'cus_1', 'billing_details' => ['name' => 'Joe']])->billing)
            ->toBeNull();
    });

    it('distinguishes a deleted customer returned from retrieve', function () {
        $customer = Customer::from(payload('customer_deleted'));

        expect($customer->id)->toBe('cus_3QxSampleDeleted001')
            ->and($customer->deleted)->toBeTrue()
            ->and($customer->isDeleted())->toBeTrue()
            ->and($customer->name)->toBeNull()
            ->and($customer->raw)->toBe(payload('customer_deleted'));
    });
});

describe('PaymentMethod', function () {
    it('maps every field', function () {
        $method = PaymentMethod::from(payload('payment_method'));

        expect($method->id)->toBe('pm_3QxSample000004')
            ->and($method->type)->toBe(PaymentMethodType::Card)
            ->and($method->billingDetails?->name)->toBe('Joe Dela Cruz')
            ->and($method->allowRedisplay)->toBe('always')
            ->and($method->livemode)->toBeFalse()
            ->and($method->metadata)->toBe(['source' => 'checkout'])
            ->and($method->createdAt?->getTimestamp())->toBe(1753420800);
    });

    it('falls back to the type-named key when there is no details object', function () {
        $method = PaymentMethod::from(payload('payment_method'));

        expect($method->details)->toBe([
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
        ]);
    });

    it('leaves the type null for a method this package has not enumerated', function () {
        $method = PaymentMethod::from(['id' => 'pm_1', 'type' => 'some_future_wallet']);

        expect($method->type)->toBeNull()
            ->and($method->raw['type'])->toBe('some_future_wallet');
    });

    it('enumerates only currently documented payment methods', function () {
        expect(array_map(fn (PaymentMethodType $type): string => $type->value, PaymentMethodType::cases()))
            ->toBe(['card', 'gcash', 'maya', 'qrph', 'bdo_installment']);
    });
});

describe('PaymentMethodSummary', function () {
    it('maps every field', function () {
        $summary = PaymentMethodSummary::from(payload('payment_method_summary'));

        expect($summary->type)->toBe(PaymentMethodType::Gcash)
            ->and($summary->details)->toBe([
                'account_name' => 'Joe Dela Cruz',
                'account_number' => '09171234567',
            ]);
    });

    it('has no details when the payload carries none', function () {
        expect(PaymentMethodSummary::from(['type' => 'card'])->details)->toBe([]);
    });
});

describe('PaymentIntent', function () {
    it('maps every field', function () {
        $intent = PaymentIntent::from(payload('payment_intent'));

        expect($intent->id)->toBe('pi_3QxSample000001')
            ->and($intent->amount)->toBe(10000)
            ->and($intent->amountReceived)->toBe(0)
            ->and($intent->amountCapturable)->toBe(0)
            ->and($intent->currency)->toBe(Currency::PHP)
            ->and($intent->status)->toBe(PaymentIntentStatus::AwaitingPaymentMethod)
            ->and($intent->clientSecret)->toBe('pi_3QxSample000001_secret_abc123')
            ->and($intent->description)->toBe('Mar 1 - Apr 1, 2026')
            ->and($intent->statementDescriptor)->toBe('ACME STORE')
            ->and($intent->paymentMethods)->toBe(['card', 'gcash'])
            ->and($intent->paymentMethodOptions)->toBe(['card' => ['capture_type' => 'automatic']])
            ->and($intent->nextAction)->toBeNull()
            ->and($intent->latestPayment)->toBeNull()
            ->and($intent->metadata)->toBe(['order_id' => '1234'])
            ->and($intent->createdAt?->getTimestamp())->toBe(1753420800);
    });

    it('nests the latest payment and the customer', function () {
        $intent = PaymentIntent::from(payload('payment_intent_succeeded'));

        expect($intent->hasSucceeded())->toBeTrue()
            ->and($intent->latestPayment)->toBeInstanceOf(Payment::class);
    });

    it('reads a redirect url out of any of the shapes next_action takes', function (array $nextAction) {
        $intent = PaymentIntent::from(['id' => 'pi_1', 'next_action' => $nextAction]);

        expect($intent->redirectUrl())->toBe('https://pay.payrexhq.test/authorize');
    })->with([
        'redirect_url' => [['redirect_url' => 'https://pay.payrexhq.test/authorize']],
        'nested redirect' => [['redirect' => ['url' => 'https://pay.payrexhq.test/authorize']]],
        'bare url' => [['url' => 'https://pay.payrexhq.test/authorize']],
    ]);
});

describe('Payment', function () {
    it('maps every field', function () {
        $payment = Payment::from(payload('payment'));

        expect($payment->id)->toBe('pay_3QxSample000002')
            ->and($payment->amount)->toBe(10000)
            ->and($payment->amountRefunded)->toBe(2500)
            ->and($payment->fee)->toBe(350)
            ->and($payment->netAmount)->toBe(9650)
            ->and($payment->currency)->toBe(Currency::PHP)
            ->and($payment->status)->toBe(PaymentStatus::Paid)
            ->and($payment->description)->toBe('Mar 1 - Apr 1, 2026')
            ->and($payment->paymentIntentId)->toBe('pi_3QxSample000001')
            ->and($payment->origin)->toBe('checkout_session')
            ->and($payment->refunded)->toBeFalse()
            ->and($payment->billing?->name)->toBe('Joe Dela Cruz')
            ->and($payment->customer)->toBeInstanceOf(Customer::class)
            ->and($payment->customer?->id)->toBe('cus_3QxSample000001')
            ->and($payment->paymentMethod)->toBeInstanceOf(PaymentMethodSummary::class)
            ->and($payment->paymentMethod?->type)->toBe(PaymentMethodType::Card)
            ->and($payment->pageSession)->toHaveKey('id')
            ->and($payment->metadata)->toBe(['order_id' => '1234'])
            ->and($payment->updatedAt?->getTimestamp())->toBe(1753420860);
    });

    it('maps every field of a completed payment', function () {
        $payment = Payment::from(payload('payment_paid'));

        expect($payment->status)->toBe(PaymentStatus::Paid)
            ->and($payment->amount)->toBe(155000)
            ->and($payment->fee)->toBe(3565)
            ->and($payment->netAmount)->toBe(150252)
            ->and($payment->origin)->toBe('api')
            ->and($payment->refunded)->toBeFalse()
            ->and($payment->paymentMethod?->type)->toBe(PaymentMethodType::Gcash)
            ->and($payment->billing?->name)->toBe('Joe Dela Cruz');
    });

    it('reads the consolidated amount and status', function () {
        $payment = Payment::from(payload('payment_paid'));

        expect($payment->consolidatedNetAmount)->toBe(150252)
            ->and($payment->consolidatedStatus)->toBe('paid');
    });

    it('has exactly the paid and failed statuses', function () {
        expect(array_map(fn (PaymentStatus $case): string => $case->value, PaymentStatus::cases()))
            ->toBe(['paid', 'failed']);
    });

    it('leaves an unmodelled status null and keeps the literal on raw', function () {
        $payment = Payment::from([...payload('payment_paid'), 'status' => 'pending']);

        expect($payment->status)->toBeNull()
            ->and($payment->raw['status'])->toBe('pending');
    });
});

describe('Refund', function () {
    it('maps every field', function () {
        $refund = Refund::from(payload('refund'));

        expect($refund->id)->toBe('ref_3QxSample000003')
            ->and($refund->amount)->toBe(2500)
            ->and($refund->currency)->toBe(Currency::PHP)
            ->and($refund->status)->toBe(RefundStatus::Succeeded)
            ->and($refund->reason)->toBe(RefundReason::RequestedByCustomer)
            ->and($refund->paymentId)->toBe('pay_3QxSample000002')
            ->and($refund->description)->toBe('Partial refund for two seats')
            ->and($refund->remarks)->toBe('Removed two seats mid-cycle.')
            ->and($refund->metadata)->toBe(['ticket_id' => 'SUP-77'])
            ->and($refund->createdAt?->getTimestamp())->toBe(1753420800);
    });
});

describe('SetupIntent', function () {
    it('maps every field', function () {
        $intent = SetupIntent::from(payload('setup_intent'));

        expect($intent->id)->toBe('seti_3QxSample000005')
            ->and($intent->status)->toBe(SetupIntentStatus::AwaitingNextAction)
            ->and($intent->clientSecret)->toBe('seti_3QxSample000005_secret_xyz789')
            ->and($intent->description)->toBe('Save a card for renewals')
            ->and($intent->returnUrl)->toBe('https://shop.test/setup/complete')
            ->and($intent->usage)->toBe('off_session')
            ->and($intent->paymentMethods)->toBe(['card'])
            ->and($intent->paymentMethodId)->toBe('pm_3QxSample000004')
            ->and($intent->customer?->id)->toBe('cus_3QxSample000001')
            ->and($intent->metadata)->toBe(['user_id' => '42'])
            ->and($intent->redirectUrl())->toBe('https://pay.payrexhq.test/authorize/seti_3QxSample000005');
    });
});

describe('CheckoutSession', function () {
    it('maps every field', function () {
        $session = CheckoutSession::from(payload('checkout_session'));

        expect($session->id)->toBe('cs_3QxSample000006')
            ->and($session->amount)->toBeNull()
            ->and($session->customerId)->toBe('cus_3QxSample000001')
            ->and($session->url)->toBe('https://pay.payrexhq.test/cs_3QxSample000006')
            ->and($session->status)->toBe(CheckoutSessionStatus::Active)
            ->and($session->currency)->toBe(Currency::PHP)
            ->and($session->lineItems)->toHaveCount(2)
            ->and($session->lineItems[0]['name'])->toBe('Basic')
            ->and($session->paymentMethods)->toBe(['card', 'gcash'])
            ->and($session->paymentMethodOptions)->toBe(['card' => ['capture_type' => 'manual']])
            ->and($session->clientSecret)->toBe('cs_3QxSample000006_secret_def456')
            ->and($session->customerReferenceId)->toBe('user_42')
            ->and($session->statementDescriptor)->toBe('ACME STORE')
            ->and($session->successUrl)->toBe('https://shop.test/checkout/success')
            ->and($session->cancelUrl)->toBe('https://shop.test/checkout/cancel')
            ->and($session->submitType)->toBe(SubmitType::Pay)
            ->and($session->billingDetailsCollection)->toBe(BillingDetailsCollection::Always)
            ->and($session->paymentIntent)->toBeInstanceOf(PaymentIntent::class)
            ->and($session->paymentIntent?->id)->toBe('pi_3QxSample000001')
            ->and($session->metadata)->toBe(['order_id' => '1234'])
            ->and($session->expiresAt?->getTimestamp())->toBe(1753424400)
            ->and($session->isCompleted())->toBeFalse();
    });

    it('reads an amount only if one is ever sent', function () {
        expect(CheckoutSession::from(['id' => 'cs_1', 'amount' => 25000])->amount)->toBe(25000);
    });
});

describe('CustomerSession', function () {
    it('maps every field', function () {
        $session = CustomerSession::from(payload('customer_session'));

        expect($session->id)->toBe('cuss_3QxSample000007')
            ->and($session->clientSecret)->toBe('cuss_3QxSample000007_secret_ghi789')
            ->and($session->customer?->id)->toBe('cus_3QxSample000001')
            ->and($session->components)->toBe([['name' => 'payment_method_list', 'enabled' => true]])
            ->and($session->expired)->toBeFalse()
            ->and($session->expiredAt)->toBeNull()
            ->and($session->createdAt?->getTimestamp())->toBe(1753420800);
    });
});

describe('BillingStatementLineItem', function () {
    it('maps every field', function () {
        $item = BillingStatementLineItem::from(payload('billing_statement_line_item'));

        expect($item->id)->toBe('bsli_3QxSample000011')
            ->and($item->unitPrice)->toBe(20000)
            ->and($item->quantity)->toBe(3)
            ->and($item->description)->toBe('Basic')
            ->and($item->billingStatementId)->toBe('bs_3QxSample000010')
            ->and($item->total())->toBe(60000);
    });
});

describe('BillingStatement', function () {
    it('maps every field', function () {
        $statement = BillingStatement::from(payload('billing_statement'));

        expect($statement->id)->toBe('bs_3QxSample000010')
            ->and($statement->amount)->toBe(60000)
            ->and($statement->currency)->toBe(Currency::PHP)
            ->and($statement->status)->toBe(BillingStatementStatus::Open)
            ->and($statement->customerId)->toBe('cus_3QxSample000001')
            ->and($statement->description)->toBe('Mar 1 - Apr 1, 2026')
            ->and($statement->statementDescriptor)->toBe('ACME STORE')
            ->and($statement->billingStatementNumber)->toBe('ACME-0007')
            ->and($statement->billingStatementMerchantName)->toBe('Acme Trading')
            ->and($statement->billingDetailsCollection)->toBe(BillingDetailsCollection::Auto)
            ->and($statement->setupFutureUsage)->toBe('off_session')
            ->and($statement->paymentSettings)->toBe(['payment_methods' => ['card', 'gcash']])
            ->and($statement->paymentIntent?->id)->toBe('pi_3QxSample000001')
            ->and($statement->customer?->name)->toBe('Joe Dela Cruz')
            ->and($statement->metadata)->toBe(['period' => '2026-03'])
            ->and($statement->isDraft())->toBeFalse();
    });

    it('hydrates its line items into dtos', function () {
        $statement = BillingStatement::from(payload('billing_statement'));

        expect($statement->lineItems)->toHaveCount(1)
            ->and($statement->lineItems[0])->toBeInstanceOf(BillingStatementLineItem::class)
            ->and($statement->lineItems[0]->total())->toBe(60000);
    });

    it('reads the hosted url from billing_statement_url', function () {
        expect(BillingStatement::from(payload('billing_statement'))->billingStatementUrl)
            ->toBe('https://pay.payrexhq.test/bs_3QxSample000010')
            ->and(BillingStatement::from(['id' => 'bs_1', 'url' => 'https://nope.test'])->billingStatementUrl)
            ->toBeNull();
    });

    it('accepts an iso-8601 timestamp as readily as unix seconds', function () {
        $statement = BillingStatement::from(payload('billing_statement'));

        expect($statement->dueAt?->toDateString())->toBe('2026-04-15')
            ->and($statement->finalizedAt?->getTimestamp())->toBe(1753420860);
    });
});

describe('Payout', function () {
    it('maps every field', function () {
        $payout = Payout::from(payload('payout'));

        expect($payout->id)->toBe('po_3QxSample000012')
            ->and($payout->amount)->toBe(96500)
            ->and($payout->netAmount)->toBe(96500)
            ->and($payout->status)->toBe(PayoutStatus::InTransit)
            ->and($payout->destination)->toBe([
                'bank_name' => 'BDO Unibank',
                'account_number' => '****6789',
            ])
            ->and($payout->createdAt?->getTimestamp())->toBe(1753420800);
    });
});

describe('PayoutTransaction', function () {
    it('maps every field', function () {
        $transaction = PayoutTransaction::from(payload('payout_transaction'));

        expect($transaction->id)->toBe('po_txn_3QxSample000013')
            ->and($transaction->amount)->toBe(10000)
            ->and($transaction->netAmount)->toBe(9650)
            ->and($transaction->transactionType)->toBe(PayoutTransactionType::Payment)
            ->and($transaction->transactionId)->toBe('pay_3QxSample000002');
    });

    it('decodes every documented transaction type', function (string $value, PayoutTransactionType $expected) {
        expect(PayoutTransaction::from(['id' => 'po_txn_1', 'transaction_type' => $value])->transactionType)
            ->toBe($expected);
    })->with([
        ['payment', PayoutTransactionType::Payment],
        ['refund', PayoutTransactionType::Refund],
        ['adjustment', PayoutTransactionType::Adjustment],
    ]);

    it('leaves the type null for a transaction kind this package has not enumerated', function () {
        $transaction = PayoutTransaction::from(['id' => 'po_txn_1', 'transaction_type' => 'chargeback']);

        expect($transaction->transactionType)->toBeNull()
            ->and($transaction->raw['transaction_type'])->toBe('chargeback');
    });
});

describe('WebhookEndpoint', function () {
    it('maps every field', function () {
        $endpoint = WebhookEndpoint::from(payload('webhook_endpoint'));

        expect($endpoint->id)->toBe('wh_3QxSample000014')
            ->and($endpoint->url)->toBe('https://shop.test/payrex/webhook')
            ->and($endpoint->status)->toBe(WebhookStatus::Enabled)
            ->and($endpoint->description)->toBe('Production endpoint')
            ->and($endpoint->secretKey)->toBe('whsk_test_returned_once')
            ->and($endpoint->events)->toBe(['payment_intent.succeeded', 'refund.created'])
            ->and($endpoint->isEnabled())->toBeTrue();
    });
});

describe('WebhookEvent', function () {
    it('maps every field', function () {
        $event = WebhookEvent::from(payload('webhook_event'));

        expect($event->id)->toBe('evt_3QxSample000015')
            ->and($event->type)->toBe('payment_intent.succeeded')
            ->and($event->resourceName)->toBe('event')
            ->and($event->livemode)->toBeFalse()
            ->and($event->pendingWebhooks)->toBe(2)
            ->and($event->previousAttributes)->toBe(['status' => 'awaiting_payment_method'])
            ->and($event->resourceType())->toBe('payment_intent')
            ->and($event->createdAt?->getTimestamp())->toBe(1753420800);
    });

    it('hydrates its subject into the matching dto', function () {
        $event = WebhookEvent::from(payload('webhook_event'));

        expect($event->resource())->toBeInstanceOf(PaymentIntent::class)
            ->and($event->paymentIntent()?->status)->toBe(PaymentIntentStatus::Succeeded)
            ->and($event->refund())->toBeNull();
    });
});

describe('Deleted', function () {
    it('maps every field', function () {
        $deleted = Deleted::from(payload('deleted'));

        expect($deleted->id)->toBe('cus_3QxSample000001')
            ->and($deleted->deleted)->toBeTrue();
    });

    it('assumes deletion happened when the flag is absent', function () {
        expect(Deleted::from(['id' => 'cus_1'])->deleted)->toBeTrue();
    });
});

describe('PayrexError', function () {
    it('maps every field', function () {
        $error = PayrexError::from(payload('payrex_error'));

        expect($error->code)->toBe('parameter_invalid')
            ->and($error->detail)->toBe('Amount must be at least 10000.')
            ->and($error->parameter)->toBe('amount')
            ->and($error->type())->toBe('invalid_request_error')
            ->and((string) $error)->toBe('parameter_invalid (amount): Amount must be at least 10000.');
    });

    it('falls back to a generic code when PayRex sends none', function () {
        expect(PayrexError::from(['detail' => 'Something went wrong.'])->code)->toBe('unknown_error');
    });
});

describe('Listing', function () {
    it('maps every field and hydrates each row', function () {
        $listing = Listing::from(payload('listing'), Customer::from(...));

        expect($listing)->toHaveCount(2)
            ->and($listing->hasMore)->toBeTrue()
            ->and($listing->isEmpty())->toBeFalse()
            ->and($listing->first())->toBeInstanceOf(Customer::class)
            ->and($listing->first()?->id)->toBe('cus_3QxSample000001')
            ->and($listing->collect()->pluck('name')->all())->toBe(['Joe Dela Cruz', 'Grace Hopper'])
            ->and(iterator_to_array($listing))->toHaveCount(2)
            ->and($listing->raw)->toBe(payload('listing'));
    });

    it('is empty for a payload with no rows', function () {
        $listing = Listing::from(['data' => [], 'has_more' => false], Customer::from(...));

        expect($listing->isEmpty())->toBeTrue()
            ->and($listing->first())->toBeNull()
            ->and($listing->hasMore)->toBeFalse();
    });
});

describe('objects embedded in other objects', function () {
    $statement = [
        'id' => 'bs_3QxSample000010',
        'resource' => 'billing_statement',
        'amount' => 250000,
        'currency' => 'PHP',
        'customer_id' => 'cus_3QxSample000001',
        'status' => 'draft',
        'livemode' => false,
        'customer' => [
            'id' => 'cus_3QxSample000001',
            'name' => 'Joe Dela Cruz',
            'email' => 'joe@example.test',
        ],
        'created_at' => 1784988541,
        'updated_at' => 1784988541,
    ];

    it('maps the fields an embedded object does carry', function () use ($statement) {
        $customer = BillingStatement::from($statement)->customer;

        expect($customer)->toBeInstanceOf(Customer::class)
            ->and($customer->id)->toBe('cus_3QxSample000001')
            ->and($customer->name)->toBe('Joe Dela Cruz')
            ->and($customer->email)->toBe('joe@example.test');
    });

    it('leaves the fields it omits null rather than defaulting them', function () use ($statement) {
        $customer = BillingStatement::from($statement)->customer;

        expect($customer->livemode)->toBeNull()
            ->and($customer->createdAt)->toBeNull()
            ->and($customer->currency)->toBeNull();
    });

    it('still reads livemode when the payload carries it', function () {
        expect(Customer::from(['id' => 'cus_1', 'livemode' => true])->livemode)->toBeTrue()
            ->and(Customer::from(['id' => 'cus_1', 'livemode' => false])->livemode)->toBeFalse()
            ->and(Customer::from(['id' => 'cus_1'])->livemode)->toBeNull();
    });

    it('leaves amount null on an embedded payment intent', function () {
        $intent = PaymentIntent::from([
            'id' => 'pi_3QxSample000001',
            'livemode' => false,
            'client_secret' => 'pi_3QxSample000001_secret_abc123',
            'latest_payment' => null,
            'merchant_id' => 'merc_3QxSample000001',
            'status' => 'canceled',
            'created_at' => 1784989886,
        ]);

        expect($intent->amount)->toBeNull()
            ->and($intent->currency)->toBeNull()
            ->and($intent->merchantId)->toBe('merc_3QxSample000001');
    });

    it('still reads amount when the payload carries it', function () {
        expect(PaymentIntent::from(['id' => 'pi_1', 'amount' => 250000])->amount)->toBe(250000)
            ->and(PaymentIntent::from(['id' => 'pi_1', 'amount' => 0])->amount)->toBe(0);
    });

    it('decodes unix integer timestamps', function () use ($statement) {
        expect(BillingStatement::from($statement)->createdAt?->toIso8601String())
            ->toBe('2026-07-25T14:09:01+00:00');
    });
});

it('has a fixture for every dto that models a PayRex payload', function () {
    // ApiResponseMetadata is built from HTTP headers rather than a response
    // body, so it has no fixture - see tests/ResponseMetadataTest.php.
    $exempt = ['ApiResponseMetadata'];

    $classes = array_map(
        fn (string $path): string => basename($path, '.php'),
        (array) glob(__DIR__.'/../../src/Data/*.php'),
    );

    $missing = array_values(array_filter(
        array_diff($classes, $exempt),
        fn (string $class): bool => ! file_exists(
            __DIR__.'/../Fixtures/'.Str::snake($class).'.json'
        ),
    ));

    expect($missing)->toBe([]);
});
