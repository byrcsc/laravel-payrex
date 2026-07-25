<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * When an authorised card payment is actually captured.
 *
 * Goes under `payment_method_options.card.capture_type` when creating a
 * payment intent or checkout session. `Manual` holds the funds until you call
 * `paymentIntents()->capture()`, which is what fires the
 * `payment_intent.awaiting_capture` webhook in the meantime.
 */
enum CaptureType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
