<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Wording of the submit button on a hosted checkout page.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum SubmitType: string
{
    case Pay = 'pay';
    case Book = 'book';
    case Donate = 'donate';
    case Send = 'send';
}
