<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

class GenerateEncryptionKeyCommand extends Command
{
    /**
     * Nazwa i sygnatura komendy konsoli.
     *
     * @var string
     */
    protected $signature = 'ksef:generate-key 
                            {--show : Wyświetl klucz zamiast modyfikować plik .env}
                            {--force : Wymuś nadpisanie istniejącego klucza w .env}';

    /**
     * Opis komendy konsoli.
     *
     * @var string
     */
    protected $description = 'Wygeneruj nowy klucz szyfrowania APP_KEY dla KSeF (AES-256-CBC)';

    /**
     * Wykonaj komendę konsoli.
     *
     * @return int
     */
    public function handle(): int
    {
        $key = $this->generateRandomKey();

        if ($this->option('show')) {
            $this->line('<comment>' . $key . '</comment>');
            return self::SUCCESS;
        }

        if (! $this->setKeyInEnvironmentFile($key)) {
            return self::FAILURE;
        }

        $this->components->info('Klucz szyfrowania został wygenerowany pomyślnie.');
        $this->newLine();
        $this->components->warn('UWAGA: Zachowaj kopię zapasową tego klucza w bezpiecznym miejscu!');
        $this->components->warn('Zmiana klucza po zaszyfrowaniu danych uniemożliwi ich odczyt.');

        return self::SUCCESS;
    }

    /**
     * Wygeneruj losowy klucz szyfrowania.
     *
     * @return string
     */
    protected function generateRandomKey(): string
    {
        return 'base64:' . base64_encode(
            Encrypter::generateKey($this->laravel['config']['app.cipher'])
        );
    }

    /**
     * Ustaw klucz aplikacji w pliku środowiskowym.
     *
     * @param string $key
     * @return bool
     */
    protected function setKeyInEnvironmentFile(string $key): bool
    {
        $currentKey = $this->laravel['config']['app.key'];

        if (strlen($currentKey) !== 0 && (! $this->option('force'))) {
            $this->components->error('Klucz szyfrowania (APP_KEY) już istnieje.');
            $this->components->warn('Użyj opcji --force aby go nadpisać (UWAGA: zaszyfrowane dane staną się nieosiągalne!)');
            return false;
        }

        if (! $this->writeNewEnvironmentFileWith($key)) {
            return false;
        }

        return true;
    }

    /**
     * Zapisz nowy klucz do pliku .env.
     *
     * @param string $key
     * @return bool
     */
    protected function writeNewEnvironmentFileWith(string $key): bool
    {
        $envPath = $this->laravel->environmentFilePath();

        if (! file_exists($envPath)) {
            $this->components->error('Plik .env nie istnieje.');
            $this->components->info('Utwórz plik .env na podstawie .env.example');
            return false;
        }

        $content = file_get_contents($envPath);

        if (str_contains($content, 'APP_KEY=')) {
            // Zamień istniejący klucz
            $replaced = preg_replace(
                '/^APP_KEY=.*$/m',
                'APP_KEY=' . $key,
                $content
            );
        } else {
            // Dodaj nowy klucz
            $replaced = $content . "\nAPP_KEY=" . $key . "\n";
        }

        if ($replaced === null || $replaced === $content) {
            $this->components->error('Nie udało się zaktualizować pliku .env');
            return false;
        }

        file_put_contents($envPath, $replaced);

        return true;
    }
}
