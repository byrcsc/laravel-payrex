<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * How much billing information a hosted page collects.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum BillingDetailsCollection: string
{
    case Auto = 'auto';
    case Always = 'always';
}
