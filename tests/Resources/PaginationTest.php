<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Facades\Payrex;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Illuminate\Http\Client\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Http;

/**
 * One page of customers, `has_more` and all.
 *
 * @param  list<string>  $ids
 * @return array<string, mixed>
 */
function customerPage(array $ids, bool $hasMore): array
{
    return [
        'data' => array_map(fn (string $id) => [
            'resource' => 'customer',
            'id' => $id,
            'name' => "Customer {$id}",
        ], $ids),
        'has_more' => $hasMore,
    ];
}

/**
 * Points the paginator at a cursor the way an inbound request would.
 */
function useCursor(?Cursor $cursor): void
{
    CursorPaginator::currentCursorResolver(fn () => $cursor);
}

afterEach(function () {
    CursorPaginator::currentCursorResolver(fn () => null);
});

describe('autoPaging', function () {
    it('walks every page and yields each item once', function () {
        Http::fake(['*' => Http::sequence()
            ->push(customerPage(['cus_1', 'cus_2'], hasMore: true))
            ->push(customerPage(['cus_3', 'cus_4'], hasMore: true))
            ->push(customerPage(['cus_5'], hasMore: false)),
        ]);

        $ids = [];

        foreach (Payrex::customers()->autoPaging(limit: 2) as $customer) {
            expect($customer)->toBeInstanceOf(Customer::class);
            $ids[] = $customer->id;
        }

        expect($ids)->toBe(['cus_1', 'cus_2', 'cus_3', 'cus_4', 'cus_5']);
        Http::assertSentCount(3);
    });

    it('follows the after cursor from the last item of each page', function () {
        Http::fake(['*' => Http::sequence()
            ->push(customerPage(['cus_1', 'cus_2'], hasMore: true))
            ->push(customerPage(['cus_3'], hasMore: false)),
        ]);

        iterator_to_array(Payrex::customers()->autoPaging(limit: 2), preserve_keys: false);

        $urls = [];
        Http::assertSent(function (Request $request) use (&$urls) {
            $urls[] = $request->url();

            return true;
        });

        expect($urls[0])->toBe('https://api.payrexhq.test/customers?limit=2')
            ->and($urls[1])->toBe('https://api.payrexhq.test/customers?limit=2&after=cus_2');
    });

    it('stops after one page when the api reports no more results', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1'], hasMore: false))]);

        expect(iterator_to_array(Payrex::customers()->autoPaging(), preserve_keys: false))->toHaveCount(1);
        Http::assertSentCount(1);
    });

    it('stops rather than looping when has_more is set but the page is empty', function () {
        Http::fake(['*' => Http::response(customerPage([], hasMore: true))]);

        expect(iterator_to_array(Payrex::customers()->autoPaging(), preserve_keys: false))->toBeEmpty();
        Http::assertSentCount(1);
    });

    it('is lazy — nothing is fetched until the generator is iterated', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1'], hasMore: false))]);

        Payrex::customers()->autoPaging();

        Http::assertNothingSent();
    });

    it('is available on every list-capable resource', function (string $resource) {
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);

        expect(iterator_to_array(Payrex::{$resource}()->autoPaging(), preserve_keys: false))->toBeEmpty();
    })->with(['customers', 'checkoutSessions', 'billingStatements', 'webhooks']);
});

describe('paginate', function () {
    it('requests the first page with no cursor', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1', 'cus_2'], hasMore: true))]);

        $paginator = Payrex::customers()->paginate(perPage: 2);

        expect($paginator)->toBeInstanceOf(PayrexCursorPaginator::class)
            ->and($paginator->items())->toHaveCount(2)
            ->and($paginator->onFirstPage())->toBeTrue()
            ->and($paginator->hasMorePages())->toBeTrue();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers?limit=2');
    });

    it('takes has_more from the api rather than from an extra row', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1', 'cus_2'], hasMore: false))]);

        $paginator = Payrex::customers()->paginate(perPage: 2);

        // A row-counting paginator would need a third row to know this.
        expect($paginator->items())->toHaveCount(2)
            ->and($paginator->hasMorePages())->toBeFalse()
            ->and($paginator->nextPageUrl())->toBeNull();
    });

    it('translates a next cursor into an after parameter', function () {
        useCursor(new Cursor(['id' => 'cus_2'], pointsToNextItems: true));
        Http::fake(['*' => Http::response(customerPage(['cus_3', 'cus_4'], hasMore: true))]);

        $paginator = Payrex::customers()->paginate(perPage: 2);

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers?limit=2&after=cus_2'
        );

        expect(array_map(fn (Customer $c) => $c->id, $paginator->items()))->toBe(['cus_3', 'cus_4']);
    });

    it('translates a previous cursor into a before parameter', function () {
        useCursor(new Cursor(['id' => 'cus_3'], pointsToNextItems: false));
        Http::fake(['*' => Http::response(customerPage(['cus_1', 'cus_2'], hasMore: false))]);

        Payrex::customers()->paginate(perPage: 2);

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers?limit=2&before=cus_3'
        );
    });

    it('keeps a previous page in the order the api returned it', function () {
        // PayRex answers a `before` query in the same newest-first order as the
        // list, while Laravel's paginator un-reverses a previous page.
        useCursor(new Cursor(['id' => 'cus_3'], pointsToNextItems: false));
        Http::fake(['*' => Http::response(customerPage(['cus_1', 'cus_2'], hasMore: false))]);

        $paginator = Payrex::customers()->paginate(perPage: 2);

        expect(array_map(fn (Customer $c) => $c->id, $paginator->items()))->toBe(['cus_1', 'cus_2'])
            ->and($paginator->onFirstPage())->toBeTrue()
            ->and($paginator->previousPageUrl())->toBeNull();
    });

    it('builds cursors from the id of the first and last item on the page', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1', 'cus_2'], hasMore: true))]);

        $cursor = Payrex::customers()->paginate(perPage: 2)->nextCursor();

        expect($cursor?->parameter('id'))->toBe('cus_2')
            ->and($cursor?->pointsToNextItems())->toBeTrue();
    });

    it('honours a custom cursor name in the page urls', function () {
        Http::fake(['*' => Http::response(customerPage(['cus_1'], hasMore: true))]);

        $paginator = Payrex::customers()->paginate(perPage: 1, cursorName: 'page', path: '/customers');

        expect($paginator->nextPageUrl())->toStartWith('/customers?page=');
    });

    it('is available on every list-capable resource', function (string $resource) {
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);

        expect(Payrex::{$resource}()->paginate()->items())->toBeEmpty();
    })->with(['customers', 'checkoutSessions', 'billingStatements', 'webhooks']);
});
