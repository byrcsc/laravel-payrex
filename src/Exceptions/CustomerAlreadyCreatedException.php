<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

use ByRcsc\LaravelPayrex\Concerns\HasPayrexCustomer;

/**
 * A model using {@see HasPayrexCustomer} was asked to create a PayRex customer
 * it already has. Creating a second one would orphan the first, along with
 * every payment method saved against it.
 */
final class CustomerAlreadyCreatedException extends PayrexException
{
    public static function for(object $model, string $customerId): self
    {
        return new self(sprintf(
            '%s is already the PayRex customer [%s].',
            $model::class,
            $customerId,
        ));
    }
}
