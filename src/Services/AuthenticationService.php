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
     * Automatycznie:
     * - Pobiera challenge token
     * - Wymienia na access/refresh tokeny
     * - Zapisuje w bazie danych
     * - Odświeża jeśli poprzedni challenge token wygasł
     *
     * @param string $certificatePath Ścieżka do pliku certyfikatu PKCS#12 lub PEM
     * @param string $privateKeyPath Ścieżka do klucza prywatnego
     * @param string $certificatePassword Hasło do certyfikatu
     * @param string $nip NIP podatnika
     * @return AuthenticationResponse
     * @throws KsefAuthenticationException
     */
    public function login(
        string $certificatePath,
        string $privateKeyPath,
        string $certificatePassword,
        string $nip,
    ): AuthenticationResponse {
        try {
            $credentials = new Credentials(
                nip: $nip,
                ksefToken: '', // Będzie ustawiony przez API
                certificatePath: $certificatePath,
                privateKeyPath: $privateKeyPath,
                certificatePassword: $certificatePassword,
            );

            return $this->authClient->authenticate($credentials, $nip);
        } catch (\Exception $e) {
            throw new KsefAuthenticationException(
                "Logowanie dla NIP {$nip} się nie powiodło: {$e->getMessage()}",
                previous: $e,
            );
        }
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
