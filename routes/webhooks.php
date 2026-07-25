<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Exceptions\InvalidConfigurationException;
use ByRcsc\LaravelPayrex\Http\Controllers\WebhookController;
use ByRcsc\LaravelPayrex\Http\Middleware\VerifyPayrexSignature;
use Illuminate\Support\Facades\Route;

if (! config('payrex.webhooks.enabled', true)) {
    return;
}

/** @var array<int, class-string|string> $middleware */
$middleware = config('payrex.webhooks.middleware', []);
$path = config('payrex.webhooks.path', 'payrex/webhook');

if (! is_string($path) || trim($path) === '') {
    throw new InvalidConfigurationException(
        'The `payrex.webhooks.path` configuration value must be a non-empty string.'
    );
}

Route::post($path, WebhookController::class)
    ->middleware(array_merge([VerifyPayrexSignature::class], $middleware))
    ->withoutMiddleware(['web', 'csrf'])
    ->name('payrex.webhook');
