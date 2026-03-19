<?php

namespace Brigada\Guardian;

use Illuminate\Support\ServiceProvider;

class GuardianServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/guardian.php', 'guardian');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/guardian.php' => config_path('guardian.php'),
        ], 'guardian-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
