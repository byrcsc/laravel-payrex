<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\Deleted;

final class WebhookDeleteCommand extends Command
{
    protected $signature = 'payrex:webhook-delete
        {id : The webhook endpoint to delete}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete a webhook endpoint registered with PayRex';

    public function handle(): int
    {
        $id = (string) $this->argument('id');

        if ($this->option('force') !== true && ! $this->confirmToProceed($id)) {
            $this->components->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        $deleted = $this->attempt(fn (): Deleted => $this->payrex()->webhooks()->delete($id));

        if ($deleted === null) {
            return self::FAILURE;
        }

        $this->components->info("Webhook endpoint [{$id}] deleted. PayRex will stop delivering to it.");

        return self::SUCCESS;
    }

    private function confirmToProceed(string $id): bool
    {
        return $this->confirm("Delete the webhook endpoint [{$id}]?", default: false);
    }
}
