<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Lifecycle of a payout to the merchant's bank.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Successful = 'successful';
    case Failed = 'failed';
}
