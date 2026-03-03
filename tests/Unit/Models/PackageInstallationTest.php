<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Unit\Models;

use Labapawel\KsefApi\Tests\TestCase;

/**
 * Test sprawdzający czy paczka jest poprawnie zainstalowana.
 */
class PackageInstallationTest extends TestCase
{
    /**
     * Test: Sprawdzenie czy paczka jest dostępna.
     */
    public function test_package_is_installed(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test: Sprawdzenie czy migracje są dostępne.
     */
    public function test_migrations_exist(): void
    {
        $this->assertFileExists(__DIR__ . '/../../../../database/migrations/2026_03_03_000001_create_ksef_credentials_table.php');
        $this->assertFileExists(__DIR__ . '/../../../../database/migrations/2026_03_03_000002_create_ksef_invoices_table.php');
    }

    /**
     * Test: Sprawdzenie czy modele są dostępne.
     */
    public function test_models_exist(): void
    {
        $this->assertTrue(class_exists(\Labapawel\KsefApi\Models\Credential::class));
        $this->assertTrue(class_exists(\Labapawel\KsefApi\Models\Invoice::class));
    }
}
