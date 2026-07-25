<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Support\Payload;

/**
 * A single entry from the `errors` array of a PayRex error response.
 */
final readonly class PayrexError
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $code,
        public string $detail,
        public ?string $parameter = null,
        public array $meta = [],
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            code: Payload::string($data, 'code', 'unknown_error'),
            detail: Payload::string($data, 'detail'),
            parameter: Payload::nullableString($data, 'parameter'),
            meta: Payload::object($data, 'meta') ?? [],
            raw: $data,
        );
    }

    /**
     * The error category PayRex assigns, e.g. `invalid_request_error`.
     */
    public function type(): ?string
    {
        $type = $this->meta['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    public function __toString(): string
    {
        return $this->parameter === null
            ? "{$this->code}: {$this->detail}"
            : "{$this->code} ({$this->parameter}): {$this->detail}";
    }
}
