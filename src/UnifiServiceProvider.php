<?php

declare(strict_types=1);

namespace Beziworld\Unifi;

use Beziworld\Unifi\Console\CheckConnectionCommand;
use Beziworld\Unifi\Console\CreateVouchersCommand;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the UniFi connection manager and the console commands.
 */
final class UnifiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/unifi.php', 'unifi');

        $this->app->singleton(UnifiManager::class, static fn(Application $app): UnifiManager => new UnifiManager(
            $app->make(Config::class),
            $app->make(HttpFactory::class),
            $app->make(CacheFactory::class),
        ));

        $this->app->bind(UnifiClient::class, static fn(Application $app): UnifiClient => $app->make(UnifiManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/unifi.php' => $this->app->configPath('unifi.php'),
            ], 'unifi-config');

            $this->commands([CheckConnectionCommand::class, CreateVouchersCommand::class]);
        }
    }
}
