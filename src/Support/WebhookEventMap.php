<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Support;

use ByRcsc\LaravelPayrex\Events\PayrexWebhookEvent;
use ByRcsc\LaravelPayrex\Exceptions\InvalidConfigurationException;

final class WebhookEventMap
{
    /**
     * @return array<string, class-string<PayrexWebhookEvent>>
     */
    public static function validate(mixed $map): array
    {
        if (! is_array($map)) {
            throw new InvalidConfigurationException(
                'The `payrex.webhooks.events` configuration value must be an array.'
            );
        }

        $validated = [];

        foreach ($map as $type => $class) {
            if (! is_string($type) || trim($type) === '') {
                throw new InvalidConfigurationException(
                    'Every `payrex.webhooks.events` key must be a non-empty event type string.'
                );
            }

            if (! is_string($class) || ! is_subclass_of($class, PayrexWebhookEvent::class)) {
                throw new InvalidConfigurationException(
                    "The event configured for `{$type}` must extend ".PayrexWebhookEvent::class.'.'
                );
            }

            /** @var class-string<PayrexWebhookEvent> $class */
            $validated[$type] = $class;
        }

        return $validated;
    }
}
