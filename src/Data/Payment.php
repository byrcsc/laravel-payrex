<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\PaymentStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A charge PayRex attempted against a payment method.
 *
 * All money is expressed in the currency's smallest unit - centavos for PHP,
 * so `amount: 10000` is ₱100.00.
 */
final readonly class Payment
{
    /**
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>|null  $pageSession
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public int $amountRefunded = 0,
        public ?int $fee = null,
        public ?int $netAmount = null,
        public ?int $consolidatedNetAmount = null,
        public ?string $consolidatedStatus = null,
        public ?Currency $currency = null,
        public ?PaymentStatus $status = null,
        public ?string $description = null,
        public ?string $paymentIntentId = null,
        public ?string $origin = null,
        public bool $refunded = false,
        public ?bool $livemode = null,
        public ?Billing $billing = null,
        public ?Customer $customer = null,
        public ?PaymentMethodSummary $paymentMethod = null,
        public ?array $pageSession = null,
        public array $metadata = [],
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $billing = Payload::object($data, 'billing');
        $customer = Payload::object($data, 'customer');
        $paymentMethod = Payload::object($data, 'payment_method');

        return new self(
            id: Payload::string($data, 'id'),
            amount: Payload::int($data, 'amount'),
            amountRefunded: Payload::int($data, 'amount_refunded'),
            fee: Payload::nullableInt($data, 'fee'),
            netAmount: Payload::nullableInt($data, 'net_amount'),
            consolidatedNetAmount: Payload::nullableInt($data, 'consolidated_net_amount'),
            consolidatedStatus: Payload::nullableString($data, 'consolidated_status'),
            currency: Payload::enum($data, 'currency', Currency::class),
            status: Payload::enum($data, 'status', PaymentStatus::class),
            description: Payload::nullableString($data, 'description'),
            paymentIntentId: Payload::nullableString($data, 'payment_intent_id'),
            origin: Payload::nullableString($data, 'origin'),
            refunded: Payload::bool($data, 'refunded'),
            livemode: Payload::nullableBool($data, 'livemode'),
            billing: $billing === null ? null : Billing::from($billing),
            customer: $customer === null ? null : Customer::from($customer),
            paymentMethod: $paymentMethod === null ? null : PaymentMethodSummary::from($paymentMethod),
            pageSession: Payload::object($data, 'page_session'),
            metadata: Payload::metadata($data),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }
}
