<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;

final class WebhookCreateCommand extends Command
{
    protected $signature = 'payrex:webhook-create
        {url : The URL PayRex should POST deliveries to}
        {--event=* : An event type to subscribe to; repeat for several, or omit for every type this package maps}
        {--description= : A note to identify the endpoint by}';

    protected $description = 'Register a webhook endpoint with PayRex';

    public function handle(): int
    {
        $events = $this->events();

        if ($events === []) {
            $this->components->error(
                'No event types to subscribe to. Pass --event, or map some types in config/payrex.php.'
            );

            return self::FAILURE;
        }

        $description = $this->option('description');

        $endpoint = $this->attempt(fn (): WebhookEndpoint => $this->payrex()->webhooks()->create(
            url: (string) $this->argument('url'),
            events: $events,
            description: is_string($description) ? $description : null,
        ));

        if ($endpoint === null) {
            return self::FAILURE;
        }

        $this->components->info('Webhook endpoint registered.');
        $this->renderEndpoint($endpoint);

        if ($endpoint->secretKey !== null) {
            $this->newLine();
            $this->components->warn(
                'Copy the signing secret below into PAYREX_WEBHOOK_SECRET now — PayRex will not show it in full again.'
            );
            $this->components->twoColumnDetail('Signing secret', $endpoint->secretKey);
        }

        return self::SUCCESS;
    }

    /**
     * The requested event types, defaulting to everything the package maps.
     *
     * @return list<string>
     */
    private function events(): array
    {
        $events = array_values(array_filter((array) $this->option('event'), is_string(...)));

        if ($events !== []) {
            return $events;
        }

        return WebhookEventTypes::configured($this->laravel);
    }
}
