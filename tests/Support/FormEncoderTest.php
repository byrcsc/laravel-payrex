<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Enums\Currency;
use ByRcsc\LaravelPayrex\Enums\PaymentMethodType;
use ByRcsc\LaravelPayrex\Support\FormEncoder;

it('encodes scalars', function () {
    expect(FormEncoder::encode(['amount' => 10000, 'currency' => 'PHP']))
        ->toBe('amount=10000&currency=PHP');
});

it('drops null values so unset optional arguments are never sent', function () {
    expect(FormEncoder::encode(['amount' => 10000, 'description' => null]))
        ->toBe('amount=10000');
});

it('unwraps backed enums to their values', function () {
    expect(FormEncoder::encode([
        'currency' => Currency::PHP,
        'payment_methods' => [PaymentMethodType::Gcash],
    ]))->toBe('currency=PHP&payment_methods%5B%5D=gcash');
});

it('sends booleans as true and false rather than 1 and 0', function () {
    expect(FormEncoder::encode(['a' => true, 'b' => false]))->toBe('a=true&b=false');
});

it('strips numeric indices from lists of scalars', function () {
    $encoded = FormEncoder::encode(['payment_methods' => ['card', 'gcash']]);

    expect(urldecode($encoded))->toBe('payment_methods[]=card&payment_methods[]=gcash')
        ->and($encoded)->not->toContain('%5B0%5D');
});

it('preserves string keys on nested objects', function () {
    expect(urldecode(FormEncoder::encode(['metadata' => ['order_id' => '42']])))
        ->toBe('metadata[order_id]=42');
});

/*
 * A list of objects encodes to a repeated empty bracket per field:
 *
 *     line_items[][name]=Sticker&line_items[][amount]=10000
 *
 * Rack -- which PayRex's API parses with -- merges consecutive `[]` segments
 * into one hash and only starts a new element when a key repeats, so the above
 * arrives as a single line item. PHP's own `parse_str()` instead starts a new
 * element on every `[]`, so a PHP round-trip of this string is lossy and must
 * not be used to assert on it.
 *
 * This is the encoding the official payrex-php SDK has always sent, so it is
 * what the live API is known to accept. It is still worth re-confirming against
 * a sandbox for multi-item payloads.
 */
it('encodes a list of objects the way rack expects', function () {
    $encoded = FormEncoder::encode([
        'line_items' => [
            ['name' => 'Sticker pack', 'amount' => 10000],
            ['name' => 'Mug', 'amount' => 25000],
        ],
    ]);

    expect(urldecode($encoded))->toBe(
        'line_items[][name]=Sticker pack'
        .'&line_items[][amount]=10000'
        .'&line_items[][name]=Mug'
        .'&line_items[][amount]=25000'
    );
});

it('returns an empty string for no parameters', function () {
    expect(FormEncoder::encode([]))->toBe('');
});
