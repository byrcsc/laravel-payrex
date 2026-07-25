<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Lifecycle of a billing statement.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum BillingStatementStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
    case Uncollectible = 'uncollectible';
}
