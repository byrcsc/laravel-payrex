<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Resources;

use ByRcsc\LaravelPayrex\Data\Deleted;
use ByRcsc\LaravelPayrex\Data\Listing;
use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;
use ByRcsc\LaravelPayrex\Http\Middleware\VerifyPayrexSignature;
use ByRcsc\LaravelPayrex\Support\PayrexCursorPaginator;
use Generator;

/**
 * Webhook endpoint management.
 *
 * This is the API for registering where PayRex should send events. Receiving
 * those events is handled separately - see the package's webhook route,
 * {@see VerifyPayrexSignature}, and the events under
 * `ByRcsc\LaravelPayrex\Events`.
 */
final class Webhooks extends Resource
{
    private const URI = '/webhooks';

    /**
     * Registers an endpoint. The response carries the `secretKey` used to sign
     * deliveries - store it as `PAYREX_WEBHOOK_SECRET`, because PayRex will not
     * show it in full again.
     *
     * @param  list<string>  $events
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $url,
        array $events,
        ?string $description = null,
        array $options = [],
    ): WebhookEndpoint {
        return WebhookEndpoint::from($this->client->post(self::URI, $this->payload([
            'url' => $url,
            'events' => $events,
            'description' => $description,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieve(string $id, array $options = []): WebhookEndpoint
    {
        return WebhookEndpoint::from($this->client->get($this->path(self::URI, $id), $options));
    }

    /**
     * @param  list<string>|null  $events
     * @param  array<string, mixed>  $options
     */
    public function update(
        string $id,
        ?string $url = null,
        ?array $events = null,
        ?string $description = null,
        array $options = [],
    ): WebhookEndpoint {
        return WebhookEndpoint::from($this->client->put($this->path(self::URI, $id), $this->payload([
            'url' => $url,
            'events' => $events,
            'description' => $description,
        ], $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function delete(string $id, array $options = []): Deleted
    {
        return Deleted::from($this->client->delete($this->path(self::URI, $id), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Listing<WebhookEndpoint>
     */
    public function list(
        ?int $limit = null,
        ?string $before = null,
        ?string $after = null,
        array $options = [],
    ): Listing {
        return Listing::from(
            $this->client->get(self::URI, $this->listParams($limit, $before, $after, $options)),
            WebhookEndpoint::from(...),
        );
    }

    /**
     * Every registered endpoint, walked page by page.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, WebhookEndpoint>
     */
    public function autoPaging(int $limit = 100, array $options = []): Generator
    {
        return $this->walkPages(
            fn (?string $after) => $this->list(limit: $limit, after: $after, options: $options)
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return PayrexCursorPaginator<WebhookEndpoint>
     */
    public function paginate(
        int $perPage = 10,
        ?string $cursorName = null,
        ?string $path = null,
        array $options = [],
    ): PayrexCursorPaginator {
        return $this->cursorPaginate(
            fn (?string $before, ?string $after, int $limit) => $this->list(
                limit: $limit,
                before: $before,
                after: $after,
                options: $options,
            ),
            perPage: $perPage,
            cursorName: $cursorName,
            path: $path,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function enable(string $id, array $options = []): WebhookEndpoint
    {
        return WebhookEndpoint::from($this->client->post($this->path(self::URI, $id, 'enable'), $options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function disable(string $id, array $options = []): WebhookEndpoint
    {
        return WebhookEndpoint::from($this->client->post($this->path(self::URI, $id, 'disable'), $options));
    }
}
