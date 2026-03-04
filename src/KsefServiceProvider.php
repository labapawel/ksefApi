<?php

declare(strict_types=1);

namespace Labapawel\KsefApi;

use Illuminate\Support\ServiceProvider;

class KsefServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ksef.php', 'ksef');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/ksef.php' => config_path('ksef.php'),
        ], 'ksef-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ksef-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'ksef-seeders');
    }
}
