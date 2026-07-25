<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * An intent to collect a specific amount from a payer.
 *
 * All money is expressed in the currency's smallest unit — centavos for PHP,
 * so `amount: 10000` is ₱100.00.
 */
final readonly class PaymentIntent
{
    /**
     * @param  list<string>  $paymentMethods
     * @param  array<string, mixed>  $paymentMethodOptions
     * @param  array<string, mixed>|null  $nextAction
     * @param  array<string, mixed>|null  $lastPaymentError
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public int $amount = 0,
        public ?int $amountReceived = null,
        public ?int $amountCapturable = null,
        public ?Currency $currency = null,
        public ?PaymentIntentStatus $status = null,
        public ?string $clientSecret = null,
        public ?string $description = null,
        public ?string $returnUrl = null,
        public ?string $statementDescriptor = null,
        public bool $livemode = false,
        public array $paymentMethods = [],
        public array $paymentMethodOptions = [],
        public ?array $nextAction = null,
        public ?array $lastPaymentError = null,
        public ?Payment $latestPayment = null,
        public ?string $paymentMethodId = null,
        public ?Customer $customer = null,
        public array $metadata = [],
        public ?CarbonImmutable $captureBeforeAt = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $latestPayment = Payload::object($data, 'latest_payment');
        $customer = Payload::object($data, 'customer');

        return new self(
            id: Payload::string($data, 'id'),
            amount: Payload::int($data, 'amount'),
            amountReceived: Payload::nullableInt($data, 'amount_received'),
            amountCapturable: Payload::nullableInt($data, 'amount_capturable'),
            currency: Payload::enum($data, 'currency', Currency::class),
            status: Payload::enum($data, 'status', PaymentIntentStatus::class),
            clientSecret: Payload::nullableString($data, 'client_secret'),
            description: Payload::nullableString($data, 'description'),
            returnUrl: Payload::nullableString($data, 'return_url'),
            statementDescriptor: Payload::nullableString($data, 'statement_descriptor'),
            livemode: Payload::bool($data, 'livemode'),
            paymentMethods: Payload::strings($data, 'payment_methods'),
            paymentMethodOptions: Payload::object($data, 'payment_method_options') ?? [],
            nextAction: Payload::object($data, 'next_action'),
            lastPaymentError: Payload::object($data, 'last_payment_error'),
            latestPayment: $latestPayment === null ? null : Payment::from($latestPayment),
            paymentMethodId: Payload::nullableString($data, 'payment_method_id'),
            customer: $customer === null ? null : Customer::from($customer),
            metadata: Payload::metadata($data),
            captureBeforeAt: Payload::dateTime($data, 'capture_before_at'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    public function hasSucceeded(): bool
    {
        return $this->status === PaymentIntentStatus::Succeeded;
    }

    /**
     * Whether the payer still has to be sent somewhere — a bank redirect or an
     * e-wallet approval screen — before this intent can settle.
     */
    public function requiresAction(): bool
    {
        return $this->status === PaymentIntentStatus::AwaitingNextAction;
    }

    /**
     * The URL the payer should be redirected to, when `next_action` carries one.
     */
    public function redirectUrl(): ?string
    {
        if (is_string($this->nextAction['redirect_url'] ?? null)) {
            return $this->nextAction['redirect_url'];
        }

        $redirect = $this->nextAction['redirect'] ?? null;

        if (is_array($redirect) && is_string($redirect['url'] ?? null)) {
            return $redirect['url'];
        }

        return is_string($this->nextAction['url'] ?? null) ? $this->nextAction['url'] : null;
    }
}
