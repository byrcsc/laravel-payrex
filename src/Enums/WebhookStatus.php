<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Whether a webhook endpoint is receiving deliveries.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum WebhookStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
