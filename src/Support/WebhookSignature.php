<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Support;

use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;

/**
 * Verifies the signature PayRex sends alongside a webhook delivery.
 *
 * The header looks like `t=1700000000,te=<hex>,li=<hex>`: a Unix timestamp,
 * then the test-mode and live-mode signatures. Exactly one of the two is
 * populated, depending on which set of keys produced the event. Each signature
 * is `HMAC-SHA256("{timestamp}.{payload}", secret)` in lowercase hex.
 */
final class WebhookSignature
{
    private const ALGORITHM = 'sha256';

    /**
     * Both the `te` and `li` slots are always tried. Which one is populated is
     * knowable only from the payload, and the payload is not trustworthy until
     * this method has returned — so nothing here branches on it. Only the
     * configured secret decides what verifies, and a test-mode secret cannot
     * produce a live-mode signature regardless of which slot carries it.
     *
     * @param  int  $tolerance  Seconds a delivery may lag behind now before it
     *                          is treated as stale. Pass 0 to skip the check.
     *
     * @throws SignatureVerificationException
     */
    public static function verify(
        string $payload,
        ?string $header,
        string $secret,
        int $tolerance = 300,
        ?int $now = null,
    ): void {
        if ($secret === '') {
            throw new SignatureVerificationException(
                'No PayRex webhook secret is configured. Set PAYREX_WEBHOOK_SECRET to the signing secret of your webhook endpoint.'
            );
        }

        if ($header === null || trim($header) === '') {
            throw new SignatureVerificationException('The request is missing a PayRex signature header.');
        }

        $parts = self::parse($header);
        $timestamp = $parts['t'] ?? null;

        if ($timestamp === null || ! ctype_digit($timestamp)) {
            throw new SignatureVerificationException(
                "The signature header [{$header}] does not carry a valid timestamp."
            );
        }

        $signatures = [];

        foreach (['te', 'li'] as $key) {
            $signature = $parts[$key] ?? null;

            if ($signature !== null && $signature !== '') {
                $signatures[] = $signature;
            }
        }

        if ($signatures === []) {
            throw new SignatureVerificationException(
                "The signature header [{$header}] does not carry a signature."
            );
        }

        if ($tolerance > 0) {
            /*
             * Only staleness is rejected. A timestamp in the future means
             * PayRex's clock runs ahead of ours, which is a clock-sync problem
             * rather than a replay — and rejecting it would drop deliveries
             * this package has no way to ask PayRex to resend.
             */
            $age = ($now ?? time()) - (int) $timestamp;

            if ($age > $tolerance) {
                throw new SignatureVerificationException(
                    "The webhook timestamp is {$age} seconds old, outside the {$tolerance} second tolerance."
                );
            }
        }

        $expected = self::sign($payload, $secret, (int) $timestamp);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new SignatureVerificationException('The webhook signature does not match the payload.');
    }

    /**
     * The expected signature for a payload — the value PayRex puts in `te`/`li`.
     */
    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac(self::ALGORITHM, "{$timestamp}.{$payload}", $secret);
    }

    /**
     * Builds a complete signature header. Useful for testing your own
     * listeners without reaching for a live PayRex delivery.
     */
    public static function header(string $payload, string $secret, ?int $timestamp = null, bool $livemode = false): string
    {
        $timestamp ??= time();
        $signature = self::sign($payload, $secret, $timestamp);

        return $livemode
            ? "t={$timestamp},te=,li={$signature}"
            : "t={$timestamp},te={$signature},li=";
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $segment) {
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $parts[trim($key)] = trim($value);
        }

        return $parts;
    }
}
