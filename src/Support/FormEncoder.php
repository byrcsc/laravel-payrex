<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Support;

/**
 * Encodes request parameters the way the PayRex API expects them.
 *
 * PayRex accepts `application/x-www-form-urlencoded` bodies and parses lists
 * from repeated bracket pairs - `payment_methods[]=card&payment_methods[]=gcash`.
 * PHP's `http_build_query()` emits numeric indices instead (`payment_methods[0]=card`),
 * which the API reads as a keyed object rather than a list, so the indices are
 * stripped back out. Nested string keys such as `metadata[order_id]` are left alone.
 */
final class FormEncoder
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function encode(array $params): string
    {
        $query = http_build_query(self::normalize($params));

        return preg_replace('/%5B\d+%5D/', '%5B%5D', $query) ?? $query;
    }

    /**
     * Unwraps enums and booleans into scalars the API understands.
     *
     * `http_build_query()` drops null values entirely, which is what we want:
     * an unset optional argument should not be sent at all.
     *
     * @param  array<array-key, mixed>  $params
     * @return array<array-key, mixed>
     */
    private static function normalize(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => self::normalize($value),
                default => $value,
            };
        }

        return $normalized;
    }
}
