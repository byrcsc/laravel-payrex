<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * What a line of a payout represents.
 *
 * The documented values are `payment`, `refund`, and `adjustment` - see the
 * [Payout Transaction resource](https://docs.payrex.com/docs/api/payout_transactions).
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum PayoutTransactionType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
