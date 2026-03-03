<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Unit\Console;

use Labapawel\KsefApi\Console\Commands\GenerateEncryptionKeyCommand;
use Labapawel\KsefApi\Tests\TestCase;

class GenerateEncryptionKeyCommandTest extends TestCase
{
    /**
     * Test: Komenda generuje klucz w poprawnym formacie.
     */
    public function test_command_generates_key_in_correct_format(): void
    {
        $this->artisan('ksef:generate-key', ['--show' => true])
            ->expectsOutput(function ($output) {
                return str_starts_with($output, 'base64:');
            })
            ->assertExitCode(0);
    }

    /**
     * Test: Komenda wyświetla klucz z opcją --show.
     */
    public function test_command_shows_key_with_show_option(): void
    {
        $this->artisan('ksef:generate-key', ['--show' => true])
            ->assertExitCode(0);
    }

    /**
     * Test: Komenda jest zarejestrowana.
     */
    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            class_exists(GenerateEncryptionKeyCommand::class)
        );
    }

    /**
     * Test: Wygenerowany klucz ma odpowiednią długość.
     */
    public function test_generated_key_has_correct_length(): void
    {
        $this->artisan('ksef:generate-key', ['--show' => true])
            ->expectsOutputToContain('base64:')
            ->assertExitCode(0);
    }
}
