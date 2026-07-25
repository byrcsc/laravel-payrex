<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Payment methods PayRex can charge.
 *
 * Resolved with `tryFrom()` when decoding, so a value PayRex adds later
 * surfaces as `null` on the DTO rather than throwing. The literal string is
 * always available on the DTO's `$raw` payload.
 */
enum PaymentMethodType: string
{
    case Card = 'card';
    case Gcash = 'gcash';
    case Maya = 'maya';
    case Qrph = 'qrph';
    case BdoInstallment = 'bdo_installment';
    case Billease = 'billease';
}
