<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

use ByRcsc\LaravelPayrex\Concerns\HasPayrexCustomer;

/**
 * A model using {@see HasPayrexCustomer} was asked for its PayRex customer
 * before one had been created for it.
 */
final class CustomerNotCreatedException extends PayrexException
{
    public static function for(object $model): self
    {
        return new self(sprintf(
            '%s is not a PayRex customer yet. Call createAsPayrexCustomer() first, or use createOrGetPayrexCustomer().',
            $model::class,
        ));
    }
}
