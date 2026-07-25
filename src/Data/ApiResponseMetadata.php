<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

/**
 * The status and headers of a PayRex response, kept aside from its body.
 *
 * PayRex does not document the headers it sets, so nothing here names one.
 * Read whichever header you need by name - a request identifier to quote to
 * PayRex support, or rate-limit counters - and treat a null as "not sent".
 */
final readonly class ApiResponseMetadata
{
    /**
     * @param  array<string, list<string>>  $headers  as delivered, in the API's own casing
     */
    public function __construct(
        public int $status,
        public array $headers = [],
    ) {}

    /**
     * Normalizes whatever the HTTP client hands back - a header may arrive as
     * a bare string or as a list of values.
     *
     * @param  array<mixed, mixed>  $headers
     */
    public static function from(int $status, array $headers): self
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $strings = [];

            foreach (is_array($values) ? $values : [$values] as $value) {
                if (is_scalar($value)) {
                    $strings[] = (string) $value;
                }
            }

            $normalized[(string) $name] = $strings;
        }

        return new self($status, $normalized);
    }

    /**
     * The first value of a header, matched without regard to case.
     */
    public function header(string $name): ?string
    {
        return $this->headerValues($name)[0] ?? null;
    }

    /**
     * Every value of a header, matched without regard to case. HTTP allows a
     * header to be repeated, and `Set-Cookie` routinely is.
     *
     * @return list<string>
     */
    public function headerValues(string $name): array
    {
        foreach ($this->headers as $header => $values) {
            if (strcasecmp($header, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    public function hasHeader(string $name): bool
    {
        return $this->headerValues($name) !== [];
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
