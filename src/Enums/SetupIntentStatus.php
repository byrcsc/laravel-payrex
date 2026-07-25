<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Lifecycle of a setup intent.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum SetupIntentStatus: string
{
    case AwaitingPaymentMethod = 'awaiting_payment_method';
    case AwaitingNextAction = 'awaiting_next_action';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';
}
