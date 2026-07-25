<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex;

use ByRcsc\LaravelPayrex\Commands\WebhookCreateCommand;
use ByRcsc\LaravelPayrex\Commands\WebhookDeleteCommand;
use ByRcsc\LaravelPayrex\Commands\WebhookListCommand;
use ByRcsc\LaravelPayrex\Commands\WebhookTestCommand;
use ByRcsc\LaravelPayrex\Commands\WebhookToggleCommand;
use ByRcsc\LaravelPayrex\Commands\WebhookUpdateCommand;
use ByRcsc\LaravelPayrex\Support\Payload;
use ByRcsc\LaravelPayrex\Support\WebhookEventMap;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class PayrexServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-payrex')
            ->hasConfigFile('payrex')
            ->hasRoute('webhooks')
            ->hasMigration('add_payrex_customer_id_column')
            ->hasCommands([
                WebhookTestCommand::class,
                WebhookListCommand::class,
                WebhookCreateCommand::class,
                WebhookUpdateCommand::class,
                WebhookDeleteCommand::class,
                WebhookToggleCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PayrexClient::class, function (Application $app): PayrexClient {
            $config = Payload::asObject($app->make('config')->get('payrex')) ?? [];
            $retry = Payload::object($config, 'retry') ?? [];
            $webhooks = Payload::object($config, 'webhooks') ?? [];

            return new PayrexClient(
                http: $app->make(HttpFactory::class),
                secretKey: Payload::nullableString($config, 'secret_key'),
                publicKey: Payload::nullableString($config, 'public_key'),
                webhookSecret: Payload::nullableString($config, 'webhook_secret'),
                baseUrl: Payload::string($config, 'base_url') ?: 'https://api.payrexhq.com',
                timeout: Payload::int($config, 'timeout', 30),
                connectTimeout: Payload::int($config, 'connect_timeout', 10),
                retryTimes: Payload::int($retry, 'times', 1),
                retrySleep: Payload::int($retry, 'sleep', 200),
                webhookTolerance: Payload::int($webhooks, 'tolerance', 300),
            );
        });

        $this->app->alias(PayrexClient::class, 'payrex');
    }

    public function packageBooted(): void
    {
        WebhookEventMap::validate($this->app->make('config')->get('payrex.webhooks.events', []));
    }
}
