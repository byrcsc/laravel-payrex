<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Why a payment was refunded.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum RefundReason: string
{
    case Fraudulent = 'fraudulent';
    case RequestedByCustomer = 'requested_by_customer';
    case ProductOutOfStock = 'product_out_of_stock';
    case ServiceNotProvided = 'service_not_provided';
    case ProductWasDamaged = 'product_was_damaged';
    case ServiceMisaligned = 'service_misaligned';
    case WrongProductReceived = 'wrong_product_received';
    case Others = 'others';
}
