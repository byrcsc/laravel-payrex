<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;

final class WebhookListCommand extends Command
{
    protected $signature = 'payrex:webhook-list
        {--limit= : How many endpoints to fetch per page}
        {--all : Walk every page rather than only the first}';

    protected $description = 'List the webhook endpoints registered with PayRex';

    public function handle(): int
    {
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $endpoints = $this->attempt(function () use ($limit): array {
            if ($this->option('all') === true) {
                return iterator_to_array(
                    $this->payrex()->webhooks()->autoPaging(limit: $limit ?? 100),
                    preserve_keys: false,
                );
            }

            return $this->payrex()->webhooks()->list(limit: $limit)->data;
        });

        if ($endpoints === null) {
            return self::FAILURE;
        }

        if ($endpoints === []) {
            $this->components->warn('No webhook endpoints are registered with PayRex.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'URL', 'Status', 'Mode', 'Events'],
            array_map(fn (WebhookEndpoint $endpoint): array => [
                $endpoint->id,
                $endpoint->url ?? '—',
                $endpoint->status->value ?? '—',
                $endpoint->livemode ? 'live' : 'test',
                (string) count($endpoint->events),
            ], $endpoints),
        );

        return self::SUCCESS;
    }
}
