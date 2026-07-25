<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\PaymentIntent;
use ByRcsc\LaravelPayrex\Enums\CaptureType;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\InstallmentType;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Enums\PaymentStatus;
use ByRcsc\LaravelPayrex\Facades\Payrex;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

describe('requests', function () {
    beforeEach(fn () => Http::fake(['*' => Http::response(payload('payment_intent'))]));

    it('creates a payment intent', function () {
        $intent = Payrex::paymentIntents()->create(
            amount: 10_000,
            paymentMethods: ['card', 'gcash'],
            description: 'Order #1234',
            statementDescriptor: 'ACME STORE',
            metadata: ['order_id' => '1234'],
        );

        expect($intent)->toBeInstanceOf(PaymentIntent::class);

        Http::assertSent(function (Request $request) {
            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe('https://api.payrexhq.test/payment_intents')
                ->and(formBody($request->body()))->toBe([
                    'amount' => '10000',
                    'currency' => 'PHP',
                    'payment_methods' => ['card', 'gcash'],
                    'description' => 'Order #1234',
                    'statement_descriptor' => 'ACME STORE',
                    'metadata' => ['order_id' => '1234'],
                ]);

            return true;
        });
    });

    it('accepts enums for currency and payment methods', function () {
        Payrex::paymentIntents()->create(
            amount: 10_000,
            paymentMethods: [PaymentMethodType::Card, PaymentMethodType::Gcash],
            currency: Currency::PHP,
        );

        Http::assertSent(fn (Request $request) => formBody($request->body()) === [
            'amount' => '10000',
            'currency' => 'PHP',
            'payment_methods' => ['card', 'gcash'],
        ]);
    });

    it('unwraps the option enums nested inside payment method options', function () {
        Payrex::paymentIntents()->create(
            amount: 10_000,
            paymentMethods: [PaymentMethodType::Card, PaymentMethodType::BdoInstallment],
            paymentMethodOptions: [
                'card' => ['capture_type' => CaptureType::Manual],
                'bdo_installment' => ['installment_types' => [InstallmentType::Zero, InstallmentType::ZeroHoliday]],
            ],
        );

        Http::assertSent(fn (Request $request) => formBody($request->body())['payment_method_options'] === [
            'card' => ['capture_type' => 'manual'],
            'bdo_installment' => ['installment_types' => ['zero', 'zero_holiday']],
        ]);
    });

    it('merges options over the named arguments', function () {
        Payrex::paymentIntents()->create(
            amount: 10_000,
            paymentMethods: ['card'],
            description: 'named',
            options: ['description' => 'from options', 'capture_type' => 'manual'],
        );

        Http::assertSent(function (Request $request) {
            $body = formBody($request->body());

            expect($body['description'])->toBe('from options')
                ->and($body['capture_type'])->toBe('manual');

            return true;
        });
    });

    it('retrieves a payment intent', function () {
        Payrex::paymentIntents()->retrieve('pi_3QxSample000001');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.payrexhq.test/payment_intents/pi_3QxSample000001');
    });

    it('updates a payment intent with a put', function () {
        Payrex::paymentIntents()->update('pi_3QxSample000001', amount: 20_000);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.payrexhq.test/payment_intents/pi_3QxSample000001'
            && $request->body() === 'amount=20000');
    });

    it('cancels a payment intent with an empty body', function () {
        Payrex::paymentIntents()->cancel('pi_3QxSample000001');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.payrexhq.test/payment_intents/pi_3QxSample000001/cancel'
            && $request->body() === '');
    });

    it('captures a payment intent', function () {
        Payrex::paymentIntents()->capture('pi_3QxSample000001', amount: 5_000);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.payrexhq.test/payment_intents/pi_3QxSample000001/capture'
            && $request->body() === 'amount=5000');
    });

    it('attaches a payment method', function () {
        Payrex::paymentIntents()->attach(
            'pi_3QxSample000001',
            paymentMethodId: 'pm_3QxSample000004',
        );

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.payrexhq.test/payment_intents/pi_3QxSample000001/attach'
            && formBody($request->body()) === [
                'payment_method_id' => 'pm_3QxSample000004',
            ]);
    });

    it('accepts the documented payment amount boundaries', function (int $amount) {
        Payrex::paymentIntents()->create(amount: $amount);

        Http::assertSent(fn (Request $request) => formBody($request->body())['amount'] === (string) $amount);
    })->with([2_000, 5_999_999_999]);

    it('rejects payment amounts outside the documented boundaries', function (int $amount) {
        Payrex::paymentIntents()->create(amount: $amount);
    })->with([1_999, 6_000_000_000])
        ->throws(InvalidArgumentException::class);

    it('checks the amount on update too', function () {
        Payrex::paymentIntents()->update('pi_1', amount: 1_999);
    })->throws(InvalidArgumentException::class);

    it('checks the amount on capture too', function () {
        Payrex::paymentIntents()->capture('pi_1', amount: 1_999);
    })->throws(InvalidArgumentException::class);

    it('leaves the amount alone on update when none is given', function () {
        Payrex::paymentIntents()->update('pi_1', description: 'Renamed');

        Http::assertSent(fn (Request $request) => formBody($request->body()) === ['description' => 'Renamed']);
    });
});

describe('decoding', function () {
    it('maps every field of an awaiting payment intent', function () {
        Http::fake(['*' => Http::response(payload('payment_intent'))]);

        $intent = Payrex::paymentIntents()->retrieve('pi_3QxSample000001');

        expect($intent->id)->toBe('pi_3QxSample000001')
            ->and($intent->amount)->toBe(10000)
            ->and($intent->amountReceived)->toBe(0)
            ->and($intent->amountCapturable)->toBe(0)
            ->and($intent->currency)->toBe(Currency::PHP)
            ->and($intent->status)->toBe(PaymentIntentStatus::AwaitingPaymentMethod)
            ->and($intent->clientSecret)->toBe('pi_3QxSample000001_secret_abc123')
            ->and($intent->description)->toBe('Order #1234')
            ->and($intent->statementDescriptor)->toBe('ACME STORE')
            ->and($intent->livemode)->toBeFalse()
            ->and($intent->paymentMethods)->toBe(['card', 'gcash'])
            ->and($intent->paymentMethodOptions)->toBe(['card' => ['capture_type' => 'automatic']])
            ->and($intent->nextAction)->toBeNull()
            ->and($intent->lastPaymentError)->toBeNull()
            ->and($intent->latestPayment)->toBeNull()
            ->and($intent->metadata)->toBe(['order_id' => '1234'])
            ->and($intent->createdAt?->timestamp)->toBe(1753420800)
            ->and($intent->hasSucceeded())->toBeFalse()
            ->and($intent->requiresAction())->toBeFalse();
    });

    it('decodes the nested payment of a succeeded intent', function () {
        Http::fake(['*' => Http::response(payload('payment_intent_succeeded'))]);

        $intent = Payrex::paymentIntents()->retrieve('pi_3QxSample000001');

        expect($intent->hasSucceeded())->toBeTrue()
            ->and($intent->amountReceived)->toBe(10000);

        $payment = $intent->latestPayment;

        expect($payment?->id)->toBe('pay_3QxSample000009')
            ->and($payment?->status)->toBe(PaymentStatus::Paid)
            ->and($payment?->fee)->toBe(350)
            ->and($payment?->netAmount)->toBe(9650)
            ->and($payment?->refunded)->toBeFalse()
            ->and($payment?->billing?->name)->toBe('Juan dela Cruz')
            ->and($payment?->billing?->address?->city)->toBe('Makati')
            ->and($payment?->billing?->address?->line2)->toBeNull()
            ->and($payment?->paymentMethod?->type)->toBe(PaymentMethodType::Card)
            ->and($payment?->paymentMethod?->details)->toBe(['brand' => 'visa', 'last4' => '4242']);
    });

    it('keeps the untouched payload on the raw property', function () {
        Http::fake(['*' => Http::response(payload('payment_intent'))]);

        expect(Payrex::paymentIntents()->retrieve('pi_1')->raw)->toBe(payload('payment_intent'));
    });

    it('yields a null status for a value the package does not know', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1', 'status' => 'quantum_superposition'])]);

        $intent = Payrex::paymentIntents()->retrieve('pi_1');

        expect($intent->status)->toBeNull()
            ->and($intent->raw['status'])->toBe('quantum_superposition');
    });

    it('survives a response missing every optional field', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1'])]);

        $intent = Payrex::paymentIntents()->retrieve('pi_1');

        expect($intent->id)->toBe('pi_1')
            ->and($intent->amount)->toBe(0)
            ->and($intent->status)->toBeNull()
            ->and($intent->paymentMethods)->toBe([])
            ->and($intent->metadata)->toBe([])
            ->and($intent->createdAt)->toBeNull();
    });

    it('exposes the redirect url from a next action', function () {
        Http::fake(['*' => Http::response([
            'id' => 'pi_1',
            'status' => 'awaiting_next_action',
            'next_action' => ['type' => 'redirect', 'redirect_url' => 'https://gcash.test/pay/abc'],
            'payment_method_id' => 'pm_1',
            'capture_before_at' => 1753420800,
            'customer' => ['id' => 'cus_1', 'name' => 'Juan'],
        ])]);

        $intent = Payrex::paymentIntents()->retrieve('pi_1');

        expect($intent->requiresAction())->toBeTrue()
            ->and($intent->redirectUrl())->toBe('https://gcash.test/pay/abc')
            ->and($intent->paymentMethodId)->toBe('pm_1')
            ->and($intent->captureBeforeAt?->timestamp)->toBe(1753420800)
            ->and($intent->customer?->id)->toBe('cus_1');
    });

    it('parses iso 8601 timestamps as readily as unix seconds', function () {
        Http::fake(['*' => Http::response(['id' => 'pi_1', 'created_at' => '2026-07-25T08:00:00Z'])]);

        expect(Payrex::paymentIntents()->retrieve('pi_1')->createdAt?->toDateString())
            ->toBe('2026-07-25');
    });
});
