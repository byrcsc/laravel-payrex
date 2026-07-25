<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ArrayIterator;
use ByRcsc\LaravelPayrex\Support\Payload;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * A page of results from a PayRex list endpoint.
 *
 * @template TItem of object
 *
 * @implements IteratorAggregate<int, TItem>
 */
final readonly class Listing implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $data = [],
        public bool $hasMore = false,
        public array $raw = [],
    ) {}

    /**
     * @template TMapped of object
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(array<string, mixed>): TMapped  $map
     * @return self<TMapped>
     */
    public static function from(array $payload, callable $map): self
    {
        return new self(
            data: array_map($map, Payload::objects($payload, 'data')),
            hasMore: Payload::bool($payload, 'has_more'),
            raw: $payload,
        );
    }

    /**
     * @return TItem|null
     */
    public function first(): ?object
    {
        return $this->data[0] ?? null;
    }

    /**
     * @return Collection<int, TItem>
     */
    public function collect(): Collection
    {
        return new Collection($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }
}
