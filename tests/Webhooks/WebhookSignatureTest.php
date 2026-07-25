<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;

const SECRET = 'whsk_test_signing_secret';

it('computes an hmac over the timestamped payload', function () {
    expect(WebhookSignature::sign('{"id":"evt_1"}', SECRET, 1753420800))
        ->toBe(hash_hmac('sha256', '1753420800.{"id":"evt_1"}', SECRET));
});

it('accepts a signature it generated', function () {
    $payload = '{"id":"evt_1"}';

    WebhookSignature::verify($payload, WebhookSignature::header($payload, SECRET), SECRET);
})->throwsNoExceptions();

it('puts a live mode signature in the li segment, leaving te empty', function () {
    expect(WebhookSignature::header('{"id":"evt_1"}', SECRET, livemode: true))
        ->toContain('te=,li=');
});

it('accepts a live mode signature', function () {
    $payload = '{"id":"evt_1"}';

    WebhookSignature::verify(
        $payload,
        WebhookSignature::header($payload, SECRET, livemode: true),
        SECRET,
    );
})->throwsNoExceptions();

it('rejects a payload that was tampered with', function () {
    $header = WebhookSignature::header('{"amount":100}', SECRET);

    WebhookSignature::verify('{"amount":999999}', $header, SECRET);
})->throws(SignatureVerificationException::class, 'does not match the payload');

it('rejects a signature made with a different secret', function () {
    $payload = '{"id":"evt_1"}';

    WebhookSignature::verify($payload, WebhookSignature::header($payload, 'wrong_secret'), SECRET);
})->throws(SignatureVerificationException::class, 'does not match the payload');

it('rejects a missing header', function () {
    WebhookSignature::verify('{}', null, SECRET);
})->throws(SignatureVerificationException::class, 'missing a PayRex signature header');

it('rejects an empty header', function () {
    WebhookSignature::verify('{}', '   ', SECRET);
})->throws(SignatureVerificationException::class, 'missing a PayRex signature header');

it('rejects a header with no timestamp', function () {
    WebhookSignature::verify('{}', 'te=abc,li=', SECRET);
})->throws(SignatureVerificationException::class, 'does not carry a valid timestamp');

it('rejects a non numeric timestamp', function () {
    WebhookSignature::verify('{}', 't=yesterday,te=abc,li=', SECRET);
})->throws(SignatureVerificationException::class, 'does not carry a valid timestamp');

it('rejects a header carrying no signature at all', function () {
    WebhookSignature::verify('{}', 't=1753420800,te=,li=', SECRET);
})->throws(SignatureVerificationException::class, 'does not carry a signature');

it('refuses to verify when no secret is configured', function () {
    WebhookSignature::verify('{}', 't=1,te=abc,li=', '');
})->throws(SignatureVerificationException::class, 'No PayRex webhook secret is configured');

it('rejects a delivery older than the freshness window', function () {
    $payload = '{"id":"evt_1"}';
    $header = WebhookSignature::header($payload, SECRET, timestamp: 1753420800);

    WebhookSignature::verify($payload, $header, SECRET, tolerance: 300, now: 1753420800 + 600);
})->throws(SignatureVerificationException::class, '600 seconds old, outside the 300 second tolerance');

it('accepts a delivery inside the tolerance window', function () {
    $payload = '{"id":"evt_1"}';
    $header = WebhookSignature::header($payload, SECRET, timestamp: 1753420800);

    WebhookSignature::verify($payload, $header, SECRET, tolerance: 300, now: 1753420800 + 120);
})->throwsNoExceptions();

it('skips the timestamp check when the tolerance is zero', function () {
    $payload = '{"id":"evt_1"}';
    $header = WebhookSignature::header($payload, SECRET, timestamp: 1);

    WebhookSignature::verify($payload, $header, SECRET, tolerance: 0);
})->throwsNoExceptions();

it('accepts a delivery dated in the future, which is clock skew rather than a replay', function () {
    $payload = '{"id":"evt_1"}';
    $header = WebhookSignature::header($payload, SECRET, timestamp: 1753420800 + 600);

    WebhookSignature::verify($payload, $header, SECRET, tolerance: 300, now: 1753420800);
})->throwsNoExceptions();

it('tolerates whitespace around header segments', function () {
    $payload = '{"id":"evt_1"}';
    $signature = WebhookSignature::sign($payload, SECRET, 1753420800);

    WebhookSignature::verify(
        $payload,
        " t = 1753420800 , te = {$signature} , li = ",
        SECRET,
        tolerance: 0,
    );
})->throwsNoExceptions();
