<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Type-safe readers for decoded PayRex payloads.
 *
 * The API is free to add fields, drop optional ones, or return a value this
 * package has not seen before. Every reader here degrades to a default rather
 * than throwing, so a payload surprise never costs the caller their response -
 * the untouched body is always kept on the DTO's `$raw` property.
 */
final class Payload
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Accepts the `1`/`0` and `"true"`/`"false"` forms PayRex may send back.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1', $value === 'true' => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default => $default,
        };
    }

    /**
     * As {@see self::bool()}, but yields `null` for an absent or unreadable
     * value instead of a default, so "not sent" stays distinct from `false`.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1', $value === 'true' => true,
            $value === 0, $value === '0', $value === 'false' => false,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>|null
     */
    public static function nullableArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * Normalizes any value into a JSON-object-shaped array, or null if it is
     * not one. A JSON list is rejected, since callers want an object here.
     *
     * @return array<string, mixed>|null
     */
    public static function asObject(mixed $value): ?array
    {
        if (! is_array($value) || array_is_list($value)) {
            return null;
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * A nested object, normalized to an associative array.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        return self::asObject($data[$key] ?? null);
    }

    /**
     * A list of nested objects.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $items = [];

        foreach (self::array($data, $key) as $item) {
            if (($item = self::asObject($item)) !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * String keys mapped to string values, as PayRex returns `metadata`.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, string>
     */
    public static function metadata(array $data, string $key = 'metadata'): array
    {
        $metadata = [];

        foreach (self::array($data, $key) as $metaKey => $value) {
            if (is_scalar($value)) {
                $metadata[(string) $metaKey] = (string) $value;
            }
        }

        return $metadata;
    }

    /**
     * A list of plain strings, as PayRex returns `payment_methods`.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $strings = [];

        foreach (self::array($data, $key) as $value) {
            if (is_scalar($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }

    /**
     * A timestamp, accepting either the Unix seconds or the ISO-8601 string
     * form so the DTOs keep working whichever PayRex sends.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function dateTime(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return CarbonImmutable::createFromTimestampUTC((int) $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves a backed enum, yielding null for values this package has not
     * enumerated yet rather than throwing on an unknown case.
     *
     * @template TEnum of BackedEnum
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    public static function enum(array $data, string $key, string $enum): ?BackedEnum
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $enum::tryFrom($value) : null;
    }
}
