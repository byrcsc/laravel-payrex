<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\PayoutTransaction;

/**
 * Payouts — transfers of settled funds to the merchant's bank account.
 *
 * Only the transactions endpoint is exposed, because that is the only payout
 * endpoint the official PayRex SDK implements. Reach anything else through
 * `PayrexClient::get()` until it is confirmed to exist.
 */
final class Payouts extends Resource
{
    private const URI = '/payouts';

    /**
     * The individual payments and refunds that make up a payout.
     *
     * @param  array<string, mixed>  $options
     * @return Listing<PayoutTransaction>
     */
    public function listTransactions(
        string $id,
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(
                $this->path(self::URI, $id, 'transactions'),
                $this->listParams($limit, $before, $after, $options),
            ),
            PayoutTransaction::from(...),
        );
    }
}
