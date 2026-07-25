<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Http\Controllers;

use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookEvent;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookReceived;
use ByRcsc\LaravelPayrex\Http\Middleware\VerifyPayrexSignature;
use ByRcsc\LaravelPayrex\Support\WebhookEventMap;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives PayRex webhook deliveries.
 *
 * The signature is checked by {@see VerifyPayrexSignature} before this ever
 * runs, so a payload reaching here is trusted.
 *
 * Two events fire for every delivery: the generic {@see PayrexWebhookReceived},
 * and - when the type is mapped in `config('payrex.webhooks.events')` - a class
 * specific to that type. Do the actual work in queued listeners; PayRex expects
 * a prompt response and will retry a slow one.
 */
final class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events, Repository $config): JsonResponse
    {
        $event = WebhookEvent::fromJson($request->getContent());

        $events->dispatch(new PayrexWebhookReceived($event));

        $mapped = $this->eventClassFor($event->type, $config);

        if ($mapped !== null) {
            $events->dispatch(new $mapped($event));
        }

        return new JsonResponse(['received' => true, 'id' => $event->id]);
    }

    /**
     * @return class-string<PayrexWebhookEvent>|null
     */
    private function eventClassFor(string $type, Repository $config): ?string
    {
        return WebhookEventMap::validate(
            $config->get('payrex.webhooks.events', [])
        )[$type] ?? null;
    }
}
