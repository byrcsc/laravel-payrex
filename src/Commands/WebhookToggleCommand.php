<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;

final class WebhookToggleCommand extends Command
{
    protected $signature = 'payrex:webhook-toggle
        {id : The webhook endpoint to enable or disable}
        {--enable : Enable the endpoint}
        {--disable : Disable the endpoint}';

    protected $description = 'Enable or disable a webhook endpoint registered with PayRex';

    public function handle(): int
    {
        $enable = $this->option('enable') === true;
        $disable = $this->option('disable') === true;

        if ($enable === $disable) {
            $this->components->error('Pass exactly one of --enable or --disable.');

            return self::FAILURE;
        }

        $id = $this->stringArgument('id');
        $webhooks = $this->payrex()->webhooks();

        $endpoint = $this->attempt(fn (): WebhookEndpoint => $enable
            ? $webhooks->enable($id)
            : $webhooks->disable($id));

        if ($endpoint === null) {
            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Webhook endpoint [%s] %s.',
            $id,
            $enable ? 'enabled' : 'disabled',
        ));
        $this->renderEndpoint($endpoint);

        return self::SUCCESS;
    }
}
