<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;

/**
 * The acknowledgement PayRex returns when a resource has been deleted.
 */
final readonly class Deleted
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public bool $deleted = true,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            deleted: Payload::bool($data, 'deleted', true),
            raw: $data,
        );
    }
}
