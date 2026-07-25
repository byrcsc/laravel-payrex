<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\PayrexClient;
use ByRcsc\LaravelPayrex\Support\Payload;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Generator;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Shared plumbing for the API resource classes.
 *
 * Each public method takes the parameters PayRex documents as named, typed
 * arguments, and every method also accepts an `$options` array that is merged
 * over them. That escape hatch means a field PayRex adds after this release
 * can still be sent without waiting for a package update.
 *
 * Null-valued parameters are omitted from the request entirely rather than
 * sent as an empty value.
 */
abstract class Resource
{
    public function __construct(
        protected readonly PayrexClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $named
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function payload(array $named, array $options = []): array
    {
        return array_merge($named, $options);
    }

    /**
     * Parameters for a list endpoint.
     *
     * PayRex uses `before` and `after` resource IDs for cursor pagination.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function listParams(
        ?int $limit,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): array {
        return $this->payload([
            'limit' => $limit,
            'before' => $before,
            'after' => $after,
        ], $options);
    }

    /**
     * Walks every page of a list endpoint, following `after` cursors until
     * PayRex stops reporting more results.
     *
     * The cursor comes off the raw payload rather than the hydrated DTO, so a
     * response whose shape this package does not model still paginates.
     *
     * @template TItem of object
     *
     * @param  callable(string|null): Listing<TItem>  $fetch  called with the `after` cursor for each page
     * @return Generator<int, TItem>
     */
    protected function walkPages(callable $fetch): Generator
    {
        $after = null;

        do {
            $page = $fetch($after);

            foreach ($page->data as $item) {
                yield $item;
            }

            $rows = Payload::objects($page->raw, 'data');
            $last = $rows === [] ? null : $rows[array_key_last($rows)];
            $after = $last === null ? null : Payload::nullableString($last, 'id');
        } while ($page->hasMore && $after !== null && $after !== '');
    }

    /**
     * Bridges a list endpoint onto Laravel's cursor paginator, so a PayRex
     * listing can drive `$paginator->links()` like any Eloquent query.
     *
     * @template TItem of object
     *
     * @param  callable(string|null, string|null, int): Listing<TItem>  $fetch  called with `before`, `after`, and the page size
     * @return PayrexCursorPaginator<TItem>
     */
    protected function cursorPaginate(
        callable $fetch,
        int $perPage,
        ?string $cursorName = null,
        ?string $path = null,
    ): PayrexCursorPaginator {
        $cursorName ??= 'cursor';
        $cursor = CursorPaginator::resolveCurrentCursor($cursorName);
        $cursor = $cursor instanceof Cursor ? $cursor : null;
        $id = $cursor === null ? null : Payload::nullableString($cursor->toArray(), 'id');

        $page = $fetch(
            $cursor?->pointsToPreviousItems() === true ? $id : null,
            $cursor?->pointsToNextItems() === true ? $id : null,
            $perPage,
        );

        $items = new Collection($page->data);

        /*
         * PayRex returns a `before` page in the same newest-first order as the
         * list itself, whereas Laravel's paginator expects a previous page to
         * arrive reversed and un-reverses it. Reversing here cancels that out.
         */
        if ($cursor?->pointsToPreviousItems() === true) {
            $items = $items->reverse()->values();
        }

        return new PayrexCursorPaginator($items, $perPage, $page->hasMore, $cursor, [
            'path' => $path ?? Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => ['id'],
        ]);
    }

    protected function path(string $uri, string ...$segments): string
    {
        $encodedSegments = array_map(function (string $segment): string {
            if (trim($segment) === '' || in_array($segment, ['.', '..'], true)) {
                throw new InvalidArgumentException('PayRex resource IDs must be non-empty path segments.');
            }

            return rawurlencode($segment);
        }, $segments);

        return rtrim($uri, '/').'/'.implode('/', $encodedSegments);
    }
}
