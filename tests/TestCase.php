<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Tests;

use ByRcsc\LaravelPayrex\PayrexServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PayrexServiceProvider::class];
    }

    /**
     * Config applied before the app boots.
     *
     * Set this and call `refreshApplication()` to test anything decided at boot
     * time, such as route registration — a plain `config()->set()` lands too
     * late for that, and is discarded by the rebuild.
     *
     * @var array<string, mixed>
     */
    protected array $bootConfig = [];

    protected function defineEnvironment($app): void
    {
        $app['config']->set('payrex.secret_key', 'sk_test_51secret');
        $app['config']->set('payrex.public_key', 'pk_test_51publishable');
        $app['config']->set('payrex.webhook_secret', 'whsk_test_signing_secret');
        $app['config']->set('payrex.base_url', 'https://api.payrexhq.test');
        $app['config']->set('payrex.retry.times', 1);

        foreach ($this->bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}
