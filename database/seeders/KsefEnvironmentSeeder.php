<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Database\Seeders;

use Illuminate\Database\Seeder;
use Labapawel\KsefApi\Models\KsefEnvironment;

class KsefEnvironmentSeeder extends Seeder
{
    /**
     * Seed domyślne środowiska KSeF.
     */
    public function run(): void
    {
        // Jeśli środowiska już istnieją, nie dodawaj duplikatów
        $environments = [
            [
                'environment' => 'test',
                'api_url' => 'https://api-test.ksef.mf.gov.pl/v2',
                'description' => 'Środowisko testowe KSeF',
                'is_active' => true,
            ],
            [
                'environment' => 'demo',
                'api_url' => 'https://api-demo.ksef.mf.gov.pl/v2',
                'description' => 'Środowisko demonstracyjne KSeF',
                'is_active' => true,
            ],
            [
                'environment' => 'prod',
                'api_url' => 'https://ksef.mf.gov.pl/api/v2',
                'description' => 'Środowisko produkcyjne KSeF',
                'is_active' => true,
            ],
        ];

        foreach ($environments as $env) {
            KsefEnvironment::firstOrCreate(
                ['environment' => $env['environment']],
                $env
            );
        }
    }
}
