<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;

final class WebhookUpdateCommand extends Command
{
    protected $signature = 'payrex:webhook-update
        {id : The webhook endpoint to update}
        {--url= : A new delivery URL}
        {--event=* : Replace the subscribed event types; repeat for several}
        {--description= : A new description}';

    protected $description = 'Update a webhook endpoint registered with PayRex';

    public function handle(): int
    {
        $url = $this->option('url');
        $description = $this->option('description');
        $events = array_values(array_filter((array) $this->option('event'), is_string(...)));

        if (! is_string($url) && ! is_string($description) && $events === []) {
            $this->components->error('Nothing to update. Pass at least one of --url, --event, or --description.');

            return self::FAILURE;
        }

        $endpoint = $this->attempt(fn (): WebhookEndpoint => $this->payrex()->webhooks()->update(
            (string) $this->argument('id'),
            url: is_string($url) ? $url : null,
            events: $events === [] ? null : $events,
            description: is_string($description) ? $description : null,
        ));

        if ($endpoint === null) {
            return self::FAILURE;
        }

        $this->components->info('Webhook endpoint updated.');
        $this->renderEndpoint($endpoint);

        return self::SUCCESS;
    }
}
