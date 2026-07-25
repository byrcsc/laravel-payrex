<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Concerns;

use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Data\Deleted;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\PaymentMethod;
use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Exceptions\CustomerAlreadyCreatedException;
use ByRcsc\LaravelPayrex\Exceptions\CustomerNotCreatedException;
use ByRcsc\LaravelPayrex\PayrexClient;
use Illuminate\Database\Eloquent\Model;

/**
 * Ties an Eloquent model to a PayRex customer.
 *
 * Add the trait to whichever model represents a payer — usually `User` — and
 * publish the migration that gives it a `payrex_customer_id` column:
 *
 * ```
 * php artisan vendor:publish --tag=payrex-migrations
 * ```
 *
 * Every naming decision is a method rather than a property, so a model whose
 * column, name, or email lives somewhere else can override just that one.
 *
 * @mixin Model
 */
trait HasPayrexCustomer
{
    /**
     * The column holding the PayRex customer ID.
     */
    public function payrexCustomerIdColumn(): string
    {
        return 'payrex_customer_id';
    }

    /**
     * This model's PayRex customer ID, or null if it has none yet.
     */
    public function payrexCustomerId(): ?string
    {
        $id = $this->getAttribute($this->payrexCustomerIdColumn());

        return is_string($id) && trim($id) !== '' ? $id : null;
    }

    public function hasPayrexCustomerId(): bool
    {
        return $this->payrexCustomerId() !== null;
    }

    /**
     * The name PayRex should file this customer under.
     */
    public function payrexCustomerName(): string
    {
        $name = $this->getAttribute('name');

        return is_string($name) ? $name : '';
    }

    /**
     * The email PayRex should send billing statements to.
     */
    public function payrexCustomerEmail(): string
    {
        $email = $this->getAttribute('email');

        return is_string($email) ? $email : '';
    }

    /**
     * The currency new customers are created in.
     */
    public function payrexCustomerCurrency(): Currency
    {
        return Currency::PHP;
    }

    /**
     * Registers this model with PayRex and stores the resulting ID.
     *
     * @param  array<string, mixed>  $options  merged over the derived parameters
     *
     * @throws CustomerAlreadyCreatedException
     */
    public function createAsPayrexCustomer(array $options = []): Customer
    {
        if (($existing = $this->payrexCustomerId()) !== null) {
            throw CustomerAlreadyCreatedException::for($this, $existing);
        }

        $customer = $this->payrex()->customers()->create(
            name: $this->payrexCustomerName(),
            email: $this->payrexCustomerEmail(),
            currency: $this->payrexCustomerCurrency(),
            options: $options,
        );

        $this->forceFill([$this->payrexCustomerIdColumn() => $customer->id])->save();

        return $customer;
    }

    /**
     * The PayRex customer behind this model.
     *
     * @throws CustomerNotCreatedException when the model has no customer yet
     */
    public function asPayrexCustomer(): Customer
    {
        return $this->payrex()->customers()->retrieve($this->requirePayrexCustomerId());
    }

    /**
     * The PayRex customer behind this model, created on first use.
     *
     * @param  array<string, mixed>  $options  passed on only when creating
     */
    public function createOrGetPayrexCustomer(array $options = []): Customer
    {
        return $this->hasPayrexCustomerId()
            ? $this->asPayrexCustomer()
            : $this->createAsPayrexCustomer($options);
    }

    /**
     * Pushes this model's current name and email to PayRex.
     *
     * @param  array<string, mixed>  $options  merged over the derived parameters
     *
     * @throws CustomerNotCreatedException
     */
    public function updatePayrexCustomer(array $options = []): Customer
    {
        return $this->payrex()->customers()->update(
            $this->requirePayrexCustomerId(),
            name: $this->payrexCustomerName(),
            email: $this->payrexCustomerEmail(),
            options: $options,
        );
    }

    /**
     * Deletes the PayRex customer and clears the stored ID, leaving this model
     * free to be registered again.
     *
     * @throws CustomerNotCreatedException
     */
    public function deleteAsPayrexCustomer(): Deleted
    {
        $deleted = $this->payrex()->customers()->delete($this->requirePayrexCustomerId());

        $this->forceFill([$this->payrexCustomerIdColumn() => null])->save();

        return $deleted;
    }

    /**
     * The payment methods saved against this customer.
     *
     * @param  array<string, mixed>  $options
     * @return Listing<PaymentMethod>
     *
     * @throws CustomerNotCreatedException
     */
    public function payrexPaymentMethods(?int $limit = null, array $options = []): Listing
    {
        return $this->payrex()->customers()->listPaymentMethods(
            $this->requirePayrexCustomerId(),
            limit: $limit,
            options: $options,
        );
    }

    /**
     * @throws CustomerNotCreatedException
     */
    protected function requirePayrexCustomerId(): string
    {
        return $this->payrexCustomerId() ?? throw CustomerNotCreatedException::for($this);
    }

    protected function payrex(): PayrexClient
    {
        return app(PayrexClient::class);
    }
}
