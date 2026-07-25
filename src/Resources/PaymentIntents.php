<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\PaymentIntent;
use ByRcsc\LaravelPayrex\Enums\CaptureType;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\InstallmentType;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Exceptions\InvalidRequestException;
use InvalidArgumentException;

/**
 * Payment intents — https://api.payrexhq.com/payment_intents
 */
final class PaymentIntents extends Resource
{
    private const URI = '/payment_intents';

    /**
     * PayRex documents this range unconditionally on the Payment Intent
     * resource: "The minimum amount is ₱ 20 (2000 in cents) and the maximum
     * amount is ₱ 59,999,999.99 (5999999999 in cents)."
     *
     * https://docs.payrex.com/docs/api/payment_intents
     *
     * It is mirrored here so an obviously invalid amount fails before a round
     * trip. Checkout session and billing statement totals are derived from
     * line items and stay server-validated.
     */
    private const MINIMUM_AMOUNT = 2_000;

    private const MAXIMUM_AMOUNT = 5_999_999_999;

    /**
     * Creates an intent to collect `$amount` from a payer.
     *
     * Amounts are in the currency's smallest unit, so `10000` is ₱100.00, and
     * must fall within PayRex's documented ₱20–₱59,999,999.99 range. An amount
     * the API rejects for any other reason comes back as an
     * {@see InvalidRequestException}.
     *
     * `$paymentMethodOptions` is keyed by {@see PaymentMethodType}, e.g.
     * `['card' => ['capture_type' => CaptureType::Manual]]` or
     * `['bdo_installment' => ['installment_types' => [InstallmentType::Zero]]]`
     * — note that `installment_types` is a list. Enum values are unwrapped on
     * the way out, so plain strings work equally well.
     *
     * @param  list<PaymentMethodType|string>  $paymentMethods
     * @param  array<string, mixed>|null  $paymentMethodOptions  see {@see CaptureType} and {@see InstallmentType}
     * @param  array<string, string>|null  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        int $amount,
        array $paymentMethods = [],
        Currency $currency = Currency::PHP,
        ?string $description = null,
        ?string $customerId = null,
        ?array $paymentMethodOptions = null,
        ?string $statementDescriptor = null,
        ?array $metadata = null,
        array $options = [],
    ): PaymentIntent {
        $this->assertValidAmount($amount);

        return PaymentIntent::from($this->client->post(self::URI, $this->payload([
            'amount' => $amount,
            'currency' => $currency,
            'payment_methods' => $paymentMethods,
            'description' => $description,
            'customer_id' => $customerId,
            'payment_method_options' => $paymentMethodOptions,
            'statement_descriptor' => $statementDescriptor,
            'metadata' => $metadata,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): PaymentIntent
    {
        return PaymentIntent::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?int $amount = null,
        ?string $description = null,
        ?string $customerId = null,
        array $options = [],
    ): PaymentIntent {
        if ($amount !== null) {
            $this->assertValidAmount($amount);
        }

        return PaymentIntent::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'amount' => $amount,
            'description' => $description,
            'customer_id' => $customerId,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function cancel(string $id, array $options = []): PaymentIntent
    {
        return PaymentIntent::from($this->client->post($this->path(self::URI, $id, 'cancel'), $options));
    }

    /**
     * Captures an authorised intent. Pass `$amount` to capture less than the
     * amount originally authorised.
     *
     * @param  array<string, mixed>  $options
     */
    public function capture(string $id, int $amount, array $options = []): PaymentIntent
    {
        $this->assertValidAmount($amount);

        return PaymentIntent::from($this->client->post($this->path(self::URI, $id, 'capture'), $this->payload([
            'amount' => $amount,
        ], $options)));
    }

    /**
     * Attaches a payment method and starts the charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function attach(
        string $id,
        string $paymentMethodId,
        array $options = [],
    ): PaymentIntent {
        return PaymentIntent::from($this->client->post($this->path(self::URI, $id, 'attach'), $this->payload([
            'payment_method_id' => $paymentMethodId,
        ], $options)));
    }

    private function assertValidAmount(int $amount): void
    {
        if ($amount < self::MINIMUM_AMOUNT || $amount > self::MAXIMUM_AMOUNT) {
            throw new InvalidArgumentException(
                'PayRex payment intent amounts must be between 2,000 and 5,999,999,999 in the currency\'s smallest unit.'
            );
        }
    }
}
