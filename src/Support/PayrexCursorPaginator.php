<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Support;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

/**
 * A cursor paginator that takes PayRex's word for whether another page exists.
 *
 * Laravel's own paginator infers that by asking the data source for one row
 * more than the page size and seeing whether it arrives. PayRex answers the
 * question outright with `has_more`, so this fetches exactly one page and uses
 * that flag instead — which also keeps the requested `limit` inside whatever
 * ceiling PayRex enforces on it.
 *
 * @template TValue of object
 *
 * @extends CursorPaginator<int, TValue>
 */
final class PayrexCursorPaginator extends CursorPaginator
{
    /**
     * @param  iterable<int, TValue>  $items
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        iterable $items,
        int $perPage,
        bool $hasMore,
        ?Cursor $cursor = null,
        array $options = [],
    ) {
        parent::__construct($items, $perPage, $cursor, $options);

        $this->hasMore = $hasMore;
    }
}
