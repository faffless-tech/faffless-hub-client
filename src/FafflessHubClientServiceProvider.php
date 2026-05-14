<?php

namespace Faffless\HubClient;

use Illuminate\Support\ServiceProvider;

class FafflessHubClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/faffless-hub.php', 'faffless-hub');

        $this->app->singleton(FafflessHubClient::class, fn () => new FafflessHubClient);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/faffless-hub.php' => config_path('faffless-hub.php'),
        ], 'faffless-hub-config');
    }
}
