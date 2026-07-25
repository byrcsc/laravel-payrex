<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Support\WebhookEventMap;
use Illuminate\Contracts\Foundation\Application;

/**
 * The event types the application has mapped to Laravel events.
 *
 * The commands offer only these, rather than a hardcoded list of everything
 * PayRex documents - a type nobody has mapped has no listener to reach, and
 * PayRex adds types faster than a package release can follow.
 */
final class WebhookEventTypes
{
    /**
     * @return list<string>
     */
    public static function configured(Application $app): array
    {
        $types = array_keys(WebhookEventMap::validate(
            $app->make('config')->get('payrex.webhooks.events', [])
        ));

        sort($types);

        return $types;
    }
}
