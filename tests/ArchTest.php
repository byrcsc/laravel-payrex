<?php

declare(strict_types=1);

arch('no debugging leftovers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('everything declares strict types')
    ->expect('ByRcsc\LaravelPayrex')
    ->toUseStrictTypes();

arch('dtos are immutable value objects')
    ->expect('ByRcsc\LaravelPayrex\Data')
    ->toBeReadonly()
    ->toBeFinal();

arch('enums are backed by strings')
    ->expect('ByRcsc\LaravelPayrex\Enums')
    ->toBeStringBackedEnums();

arch('resources share the base class')
    ->expect('ByRcsc\LaravelPayrex\Resources')
    ->toExtend('ByRcsc\LaravelPayrex\Resources\Resource');

arch('every exception is catchable as a PayrexException')
    ->expect('ByRcsc\LaravelPayrex\Exceptions')
    ->toExtend('ByRcsc\LaravelPayrex\Exceptions\PayrexException');

arch('webhook events share the base event')
    ->expect('ByRcsc\LaravelPayrex\Events')
    ->toExtend('ByRcsc\LaravelPayrex\Events\PayrexWebhookEvent');

arch('support helpers are final')
    ->expect('ByRcsc\LaravelPayrex\Support')
    ->toBeFinal();

arch('support helpers stay below the data layer')
    ->expect('ByRcsc\LaravelPayrex\Support')
    ->not->toUse('ByRcsc\LaravelPayrex\Data');

arch('data objects never reach for the network or the container')
    ->expect('ByRcsc\LaravelPayrex\Data')
    ->not->toUse([
        'Illuminate\Support\Facades\Http',
        'ByRcsc\LaravelPayrex\PayrexClient',
    ]);
