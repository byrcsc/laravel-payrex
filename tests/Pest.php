<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\PayrexClient;
use ByRcsc\LaravelPayrex\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The package's client, resolved from the container under test.
 */
function payrex(): PayrexClient
{
    return app(PayrexClient::class);
}

/**
 * Loads a JSON fixture from `tests/Fixtures`.
 *
 * Named around Pest's own `fixture()` helper, which returns a path rather than
 * decoded contents.
 *
 * @return array<string, mixed>
 */
function payload(string $name): array
{
    $path = __DIR__."/Fixtures/{$name}.json";

    if (! file_exists($path)) {
        throw new RuntimeException("Missing fixture [{$name}] at {$path}.");
    }

    /** @var array<string, mixed> */
    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Parses a form-encoded request body back into an array, so tests can assert
 * on what was sent without hand-decoding percent escapes.
 *
 * @return array<string, mixed>
 */
function formBody(string $body): array
{
    parse_str($body, $parsed);

    return $parsed;
}
