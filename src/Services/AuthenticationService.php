<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Services;

use Illuminate\Support\Facades\Config;
use Labapawel\KsefApi\Clients\KsefAuthClient;
use Labapawel\KsefApi\DTO\AuthenticationResponse;
use Labapawel\KsefApi\DTO\Credentials;
use Labapawel\KsefApi\Exceptions\KsefAuthenticationException;
use Labapawel\KsefApi\Models\Credential;

/**
 * Serwis wysokopoziomowy do obsługi autentykacji KSeF.
 *
 * Ułatwia:
 * - Logowanie z automatycznym odświeżeniem challenge tokena
 * - Pobieranie aktualnych poświadczeń
 * - Sprawdzenie czy poświadczenia wciąż są ważne
 */
class AuthenticationService
{
    private KsefAuthClient $authClient;

    private string $environment;

    private int $challengeTokenLifetime;

    public function __construct()
    {
        $this->authClient = new KsefAuthClient();
        $this->environment = Config::get('ksef.environment', 'demo');
        $this->challengeTokenLifetime = Config::get('ksef.challenge_token_lifetime', 10);
    }

    /**
     * Zaloguj się do KSeF dla danego NIP.
     *
     * Pobiera certyfikat z bazy danych i loguje się do API KSeF.
     *
     * Automatycznie:
     * - Pobiera dane z bazy (certyfikat, klucz prywatny, hasło)
     * - Pobiera challenge token
     * - Wymienia na access/refresh tokeny
     * - Uaktualnia dane w bazie
     *
     * @param string $nip NIP podatnika
     * @param string|null $environment Środowisko (domyślnie z konfiguracji)
     * @return AuthenticationResponse
     * @throws KsefAuthenticationException
     */
    public function login(
        string $nip,
        ?string $environment = null,
    ): AuthenticationResponse {
        $tempFiles = [];

        try {
            $env = $environment ?? $this->environment;

            // Pobierz rekord poświadczeń z bazy (certyfikat jest już tam zapisany)
            $dbCredential = Credential::forEnvironmentAndNip($env, $nip)
                ->withCertificate()
                ->orderByDesc('updated_at')
                ->first();

            if (! $dbCredential) {
                throw new KsefAuthenticationException(
                    "Brak poświadczeń w bazie dla NIP {$nip} w środowisku {$env}. Najpierw zapisz certyfikat do bazy.",
                );
            }

            // Przygotuj tymczasowe pliki z zawartością z bazy (Laravel aut. deszyfuje)
            $certificatePath = $this->createTempFile($dbCredential->certificate_encrypted ?? '', '.pem');
            $privateKeyPath = $this->createTempFile($dbCredential->private_key_encrypted ?? '', '.key');

            if (! $certificatePath || ! $privateKeyPath) {
                throw new KsefAuthenticationException(
                    "Nie udało się stworzyć tymczasowych plików dla certyfikatu",
                );
            }

            $tempFiles[] = $certificatePath;
            $tempFiles[] = $privateKeyPath;

            // Utwórz DTO z danymi z bazy
            $credentials = new Credentials(
                nip: $nip,
                ksefToken: '', // Będzie ustawiony przez API
                certificatePath: $certificatePath,
                privateKeyPath: $privateKeyPath,
                certificatePassword: $dbCredential->certificate_password_encrypted ?? '',
            );

            return $this->authClient->authenticate($credentials, $nip, $env);
        } catch (\Exception $e) {
            throw new KsefAuthenticationException(
                "Logowanie dla NIP {$nip} się nie powiodło: {$e->getMessage()}",
                previous: $e,
            );
        } finally {
            // Wyczyść tymczasowe pliki
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Stwórz tymczasowy plik z zawartością.
     *
     * @param string $content
     * @param string $suffix Rozszerzenie (np. '.pem')
     * @return string|null Ścieżka do pliku lub null jeśli operacja się nie powiodła
     */
    private function createTempFile(string $content, string $suffix = ''): ?string
    {
        if (empty($content)) {
            return null;
        }

        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'ksef_') . $suffix;

        if (file_put_contents($tempFile, $content) === false) {
            return null;
        }

        chmod($tempFile, 0600); // Zabezpiecz plik

        return $tempFile;
    }

    /**
     * Pobierz aktualne poświadczenia dla NIP.
     *
     * Jeśli challenge token wygasł, automatycznie ponawia logowanie
     * (jeśli dostępny jest certyfikat).
     *
     * @param string $nip
     * @return Credential|null
     */
    public function getCredentials(string $nip): ?Credential
    {
        return Credential::forEnvironmentAndNip($this->environment, $nip)
            ->withCertificate()
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Sprawdź czy poświadczenia dla danego NIP mogą być użyte.
     *
     * Warunki:
     * - Poświadczenia istnieją
     * - Challenge token nie wygasł
     * - Access token nie wygasł
     *
     * @param string $nip
     * @return bool
     */
    public function hasValidCredentials(string $nip): bool
    {
        $credential = $this->getCredentials($nip);

        if (! $credential) {
            return false;
        }

        // Sprawdź czy access token jest ważny
        if ($credential->isTokenExpired()) {
            return false;
        }

        // Sprawdź czy challenge token jest ważny
        return ! $this->isChallengeTokenExpired($credential);
    }

    /**
     * Pobierz access token dla danego NIP.
     *
     * Zwraca zdeszyfowany token lub null jeśli poświadczenia nie istnieją.
     *
     * @param string $nip
     * @return string|null
     */
    public function getAccessToken(string $nip): ?string
    {
        $credential = $this->getCredentials($nip);

        if (! $credential) {
            return null;
        }

        return $credential->access_token_encrypted;
    }

    /**
     * Usuń poświadczenia dla danego NIP (logout).
     *
     * @param string $nip
     * @return bool
     */
    public function logout(string $nip): bool
    {
        return Credential::forEnvironmentAndNip($this->environment, $nip)
            ->delete() > 0;
    }

    /**
     * Sprawdź czy challenge token wygasł.
     *
     * @param Credential $credential
     * @return bool
     */
    private function isChallengeTokenExpired(Credential $credential): bool
    {
        if (! isset($credential->meta['challenge_token_received_at'])) {
            // Jeśli nie znamy czasu otrzymania, przyjmij że wygasł
            return true;
        }

        $receivedAt = \Carbon\Carbon::parse($credential->meta['challenge_token_received_at']);
        $expiresAt = $receivedAt->addMinutes($this->challengeTokenLifetime);

        return now()->isAfter($expiresAt);
    }
}
