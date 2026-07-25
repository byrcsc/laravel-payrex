<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\BillingDetailsCollection;
use ByRcsc\LaravelPayrex\Enums\BillingStatementStatus;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * An itemised statement PayRex can send to a customer for payment.
 */
final readonly class BillingStatement
{
    /**
     * @param  list<BillingStatementLineItem>  $lineItems
     * @param  array<string, mixed>  $paymentSettings
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public ?Currency $currency = null,
        public ?BillingStatementStatus $status = null,
        public ?string $customerId = null,
        public ?string $description = null,
        public ?string $statementDescriptor = null,
        public ?string $billingStatementNumber = null,
        public ?string $billingStatementUrl = null,
        public ?string $billingStatementMerchantName = null,
        public ?BillingDetailsCollection $billingDetailsCollection = null,
        public ?string $setupFutureUsage = null,
        public array $lineItems = [],
        public array $paymentSettings = [],
        public ?PaymentIntent $paymentIntent = null,
        public ?Customer $customer = null,
        public bool $livemode = false,
        public array $metadata = [],
        public ?CarbonImmutable $dueAt = null,
        public ?CarbonImmutable $finalizedAt = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $paymentIntent = Payload::object($data, 'payment_intent');
        $customer = Payload::object($data, 'customer');

        return new self(
            id: Payload::string($data, 'id'),
            amount: Payload::int($data, 'amount'),
            currency: Payload::enum($data, 'currency', Currency::class),
            status: Payload::enum($data, 'status', BillingStatementStatus::class),
            customerId: Payload::nullableString($data, 'customer_id'),
            description: Payload::nullableString($data, 'description'),
            statementDescriptor: Payload::nullableString($data, 'statement_descriptor'),
            billingStatementNumber: Payload::nullableString($data, 'billing_statement_number'),
            billingStatementUrl: Payload::nullableString($data, 'url')
                ?? Payload::nullableString($data, 'billing_statement_url'),
            billingStatementMerchantName: Payload::nullableString($data, 'billing_statement_merchant_name'),
            billingDetailsCollection: Payload::enum($data, 'billing_details_collection', BillingDetailsCollection::class),
            setupFutureUsage: Payload::nullableString($data, 'setup_future_usage'),
            lineItems: array_map(
                BillingStatementLineItem::from(...),
                Payload::objects($data, 'line_items'),
            ),
            paymentSettings: Payload::object($data, 'payment_settings') ?? [],
            paymentIntent: $paymentIntent === null ? null : PaymentIntent::from($paymentIntent),
            customer: $customer === null ? null : Customer::from($customer),
            livemode: Payload::bool($data, 'livemode'),
            metadata: Payload::metadata($data),
            dueAt: Payload::dateTime($data, 'due_at'),
            finalizedAt: Payload::dateTime($data, 'finalized_at'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    /**
     * A draft statement can still be edited; finalizing it locks the contents.
     */
    public function isDraft(): bool
    {
        return $this->status === BillingStatementStatus::Draft;
    }
}
