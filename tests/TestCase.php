<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            \Labapawel\KsefApi\KsefServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Ustaw testową bazę danych
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Ustaw klucz aplikacji do szyfrowania
        $app['config']->set('app.key', 'base64:' . base64_encode(openssl_random_pseudo_bytes(32)));
    }

    /**
     * Uruchom migracje dla testów.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Załaduj migracje z paczki
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
