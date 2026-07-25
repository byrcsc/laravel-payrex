<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Enums;

/**
 * Which installment plans a payer may choose from.
 *
 * PayRex takes these as a **list** under `installment_types` (plural), inside
 * the installment payment method's entry in `payment_method_options`:
 *
 * ```
 * ['bdo_installment' => ['installment_types' => [InstallmentType::Zero]]]
 * ```
 *
 * `Zero` is zero-interest for the payer, with the cost borne by the merchant;
 * the `*_holiday` variants defer the first payment. See
 * [BDO Installment](https://docs.payrex.com/docs/guide/developer_handbook/payments/payment_methods/bdo_installment/receive_a_payment).
 */
enum InstallmentType: string
{
    case Regular = 'regular';
    case Zero = 'zero';
    case RegularHoliday = 'regular_holiday';
    case ZeroHoliday = 'zero_holiday';
}
