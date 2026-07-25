<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use ByRcsc\LaravelPayrex\Data\WebhookEndpoint;
use ByRcsc\LaravelPayrex\Exceptions\PayrexException;
use ByRcsc\LaravelPayrex\PayrexClient;
use Illuminate\Console\Command as BaseCommand;

/**
 * Shared plumbing for the package's Artisan commands.
 *
 * A PayRex error is reported as a failed command rather than an uncaught
 * exception - a stack trace tells an operator nothing the API's own message
 * does not say better.
 */
abstract class Command extends BaseCommand
{
    protected function payrex(): PayrexClient
    {
        return $this->laravel->make(PayrexClient::class);
    }

    /**
     * Runs a call against PayRex, turning a failure into exit code 1.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $call
     * @return TResult|null
     */
    protected function attempt(callable $call): mixed
    {
        try {
            return $call();
        } catch (PayrexException $exception) {
            $this->components->error($exception->getMessage());

            return null;
        }
    }

    /**
     * A required string argument.
     *
     * Symfony types arguments as `mixed` because a signature may declare an
     * array one. Every argument here is a required scalar, so the fallback is
     * unreachable - it exists to keep the narrowing out of each command.
     */
    protected function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        return is_string($value) ? $value : '';
    }

    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function stringArrayOption(string $key): array
    {
        return array_values(array_filter((array) $this->option($key), is_string(...)));
    }

    protected function renderEndpoint(WebhookEndpoint $endpoint): void
    {
        $this->components->twoColumnDetail('ID', $endpoint->id);
        $this->components->twoColumnDetail('URL', $endpoint->url ?? '-');
        $this->components->twoColumnDetail('Status', $endpoint->status->value ?? '-');
        $this->components->twoColumnDetail('Description', $endpoint->description ?? '-');
        $this->components->twoColumnDetail('Mode', $endpoint->livemode ? 'live' : 'test');
        $this->components->twoColumnDetail(
            'Events',
            $endpoint->events === [] ? '-' : implode(', ', $endpoint->events),
        );
    }
}
