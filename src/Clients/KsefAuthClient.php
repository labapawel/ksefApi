<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Clients;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Labapawel\KsefApi\DTO\AuthenticationResponse;
use Labapawel\KsefApi\DTO\Credentials;
use Labapawel\KsefApi\Exceptions\KsefApiException;
use Labapawel\KsefApi\Exceptions\KsefAuthenticationException;
use Labapawel\KsefApi\Models\Credential;

/**
 * Klient do obsługi autentykacji w API KSeF.
 *
 * Obsługuje:
 * - Pobieranie challenge tokenów
 * - Wymianę challenge tokenów na access/refresh tokeny
 * - Automatyczne odświeżenie logowania jeśli challenge token wygasł
 * - Zapis poświadczeń w bazie danych
 */
class KsefAuthClient
{
    private Client $httpClient;

    private string $environment;

    private string $apiUrl;

    private int $challengeTokenLifetime;

    public function __construct()
    {
        $this->environment = Config::get('ksef.environment', 'demo');
        $this->apiUrl = Config::get('ksef.api_url', 'https://api-demo.ksef.mf.gov.pl/v2');
        $this->challengeTokenLifetime = Config::get('ksef.challenge_token_lifetime', 10);
        $timeout = Config::get('ksef.api_timeout', 30);

        $this->httpClient = new Client([
            'timeout' => $timeout,
            'verify' => false, // TODO: Nadaj poprawny cert w produkcji
        ]);
    }

    /**
     * Zaloguj się do KSeF i zapisz poświadczenia w bazie.
     *
     * Ejecutuje pełny flow logowania:
     * 1. Pobiera challenge token z API
     * 2. Wymienia challenge token na access/refresh tokeny
     * 3. Zapisuje wszystkie tokeny w bazie danych
     *
     * Jeśli istniejące poświadczenia mają wygasły challenge token,
     * automatycznie ponawia logowanie.
     *
     * @param Credentials $credentials Dane do logowania (NIP, certyfikat, itd.)
     * @param string $nip NIP podatnika
     * @param string|null $environment Środowisko (domyślnie z configu)
     * @return AuthenticationResponse
     * @throws KsefAuthenticationException
     * @throws KsefApiException
     */
    public function authenticate(Credentials $credentials, string $nip, ?string $environment = null): AuthenticationResponse
    {
        $env = $environment ?? $this->environment;
        // Weź pierwszy aktualny rekord poświadczeń dla NIP+środowisko
        $existingCredential = Credential::forEnvironmentAndNip($env, $nip)
            ->withCertificate()
            ->orderByDesc('updated_at')
            ->first();

        // Jeśli challenge token istnieje i jeszcze jest ważny, możemy go użyć
        if ($existingCredential && ! $this->isChallengeTokenExpired($existingCredential)) {
            // Użyj istniejącego challenge tokena
            return new AuthenticationResponse(
                challengeToken: $existingCredential->ksef_token_encrypted,
                accessToken: $existingCredential->access_token_encrypted ?? '',
                refreshToken: $existingCredential->refresh_token_encrypted ?? '',
                tokenExpiresAt: $existingCredential->token_expires_at ?? now(),
                challengeTokenReceivedAt: $this->getChallengeTokenReceivedAt($existingCredential),
            );
        }

        // Challenge token wygasł lub nie istnieje — pobierz nowy
        $challengeToken = $this->initializeSession($credentials);

        // Wymień challenge token na access/refresh tokeny
        $authResponse = $this->authorizeSession($credentials, $challengeToken);

        // Zapisz w bazie
        $this->saveCredentialsToDatabase($credentials, $authResponse, $env);

        return $authResponse;
    }

    /**
     * Pobierz challenge token z API KSeF (InitializeSession).
     *
     * Żądanie zawiera certyfikat i NIP, jako odpowiedź otrzymujemy
     * tymczasowy challenge token do podpisania żądania dostępu.
     *
     * @param Credentials $credentials
     * @return string Challenge token
     * @throws KsefApiException
     */
    private function initializeSession(Credentials $credentials): string
    {
        try {
            $response = $this->httpClient->post(
                "{$this->apiUrl}/sessions",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contextIdentifier' => [
                            'type' => 'nip',
                            'identifier' => $credentials->nip,
                        ],
                    ],
                    'cert' => $credentials->certificatePath,
                    'ssl_key' => $credentials->privateKeyPath,
                ],
            );

            $body = json_decode($response->getBody()->getContents(), true);

            if (! isset($body['sessionToken']['token'])) {
                throw new KsefApiException(
                    'Nie znaleziono challengeToken w odpowiedzi API',
                    response: $body,
                );
            }

            return $body['sessionToken']['token'];
        } catch (\Exception $e) {
            throw new KsefApiException(
                "Błąd podczas pobierania challenge tokena: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Wymień challenge token na access/refresh tokeny (AuthorizeSession).
     *
     * Żądanie zawiera challenge token podpisany certyfikatem,
     * jako odpowiedź otrzymujemy access token i refresh token.
     *
     * @param Credentials $credentials
     * @param string $challengeToken
     * @return AuthenticationResponse
     * @throws KsefApiException
     */
    private function authorizeSession(Credentials $credentials, string $challengeToken): AuthenticationResponse
    {
        try {
            // TODO: Zamiast tego mockowania, zainplementuj prawidłowe podpisywanie XAdES
            $signedRequest = $this->createSignedAuthRequest($challengeToken, $credentials);

            $response = $this->httpClient->post(
                "{$this->apiUrl}/sessions/{$challengeToken}",
                [
                    'headers' => [
                        'Content-Type' => 'application/octet-stream',
                    ],
                    'body' => $signedRequest,
                    'cert' => $credentials->certificatePath,
                    'ssl_key' => $credentials->privateKeyPath,
                ],
            );

            $body = json_decode($response->getBody()->getContents(), true);

            // Zmapuj odpowiedź z API na AuthenticationResponse
            return $this->mapAuthResponse($body, $challengeToken);
        } catch (\Exception $e) {
            throw new KsefApiException(
                "Błąd podczas wymiany challenge tokena: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Utwórz podpisane żądanie autoryzacji (XAdES).
     *
     * TODO: Zaimplementuj pełne XAdES podpisywanie przy użyciu OpenSSL / biblioteki XAdES.
     *
     * @param string $challengeToken
     * @param Credentials $credentials
     * @return string Podpisane żądanie (XML)
     */
    private function createSignedAuthRequest(string $challengeToken, Credentials $credentials): string
    {
        // Placeholder — rzeczywista implementacja wymaga biblioteki do XAdES
        return json_encode([
            'sessionToken' => [
                'token' => $challengeToken,
            ],
        ]);
    }

    /**
     * Zmapuj odpowiedź z API na DTO AuthenticationResponse.
     *
     * @param array $apiResponse
     * @param string $challengeToken
     * @return AuthenticationResponse
     * @throws KsefApiException
     */
    private function mapAuthResponse(array $apiResponse, string $challengeToken): AuthenticationResponse
    {
        if (! isset($apiResponse['accessToken'], $apiResponse['refreshToken'])) {
            throw new KsefApiException(
                'Brakuje accessToken lub refreshToken w odpowiedzi API',
                response: $apiResponse,
            );
        }

        $expiresAt = isset($apiResponse['expiresIn'])
            ? now()->addSeconds($apiResponse['expiresIn'])
            : now()->addHours(24);

        return new AuthenticationResponse(
            challengeToken: $challengeToken,
            accessToken: $apiResponse['accessToken'],
            refreshToken: $apiResponse['refreshToken'],
            tokenExpiresAt: $expiresAt,
            challengeTokenReceivedAt: now(),
        );
    }

    /**
     * Zapisz poświadczenia w bazie danych.
     *
     * @param string $nip
     * @param AuthenticationResponse $authResponse
     * @return Credential
     */
    private function saveCredentialsToDatabase(Credentials $credentials, AuthenticationResponse $authResponse, string $environment): Credential
    {
        $credential = Credential::forEnvironmentAndNip($environment, $credentials->nip)
            ->orderByDesc('updated_at')
            ->first();

        $certificateContent = $this->readFileIfExists($credentials->certificatePath);
        $privateKeyContent = $this->readFileIfExists($credentials->privateKeyPath);

        if ($credential) {
            // Aktualizuj istniejące poświadczenia
            $credential->update([
                'api_url' => $this->apiUrl,
                'ksef_token_encrypted' => $authResponse->challengeToken,
                'access_token_encrypted' => $authResponse->accessToken,
                'refresh_token_encrypted' => $authResponse->refreshToken,
                'certificate_encrypted' => $certificateContent,
                'private_key_encrypted' => $privateKeyContent,
                'certificate_password_encrypted' => $credentials->certificatePassword,
                'challenge_token_received_at' => $authResponse->challengeTokenReceivedAt,
                'challenge_token_expires_at' => $authResponse->challengeTokenReceivedAt->copy()->addMinutes($this->challengeTokenLifetime),
                'token_expires_at' => $authResponse->tokenExpiresAt,
                'meta' => [
                    'challenge_token_received_at' => $authResponse->challengeTokenReceivedAt->toIso8601String(),
                    'last_auth_at' => now()->toIso8601String(),
                ],
            ]);

            return $credential;
        }

        // Utwórz nowe poświadczenia
        return Credential::create([
            'environment' => $environment,
            'nip' => $credentials->nip,
            'api_url' => $this->apiUrl,
            'ksef_token_encrypted' => $authResponse->challengeToken,
            'access_token_encrypted' => $authResponse->accessToken,
            'refresh_token_encrypted' => $authResponse->refreshToken,
            'certificate_encrypted' => $certificateContent,
            'private_key_encrypted' => $privateKeyContent,
            'certificate_password_encrypted' => $credentials->certificatePassword,
            'challenge_token_received_at' => $authResponse->challengeTokenReceivedAt,
            'challenge_token_expires_at' => $authResponse->challengeTokenReceivedAt->copy()->addMinutes($this->challengeTokenLifetime),
            'token_expires_at' => $authResponse->tokenExpiresAt,
            'meta' => [
                'challenge_token_received_at' => $authResponse->challengeTokenReceivedAt->toIso8601String(),
                'first_auth_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Odczytaj plik jeśli istnieje.
     *
     * @param string $path
     * @return string|null
     */
    private function readFileIfExists(string $path): ?string
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    /**
     * Sprawdź czy challenge token w bazie wygasł.
     *
     * @param Credential $credential
     * @return bool
     */
    private function isChallengeTokenExpired(Credential $credential): bool
    {
        $receivedAt = $this->getChallengeTokenReceivedAt($credential);
        $expiresAt = $receivedAt->addMinutes($this->challengeTokenLifetime);

        return now()->isAfter($expiresAt);
    }

    /**
     * Pobierz czas otrzymania challenge tokena z meta.
     *
     * @param Credential $credential
     * @return Carbon
     */
    private function getChallengeTokenReceivedAt(Credential $credential): Carbon
    {
        if (isset($credential->meta['challenge_token_received_at'])) {
            return Carbon::parse($credential->meta['challenge_token_received_at']);
        }

        // Fallback — jeśli meta nie zawiera czasu, użyj updated_at
        return $credential->updated_at ?? now();
    }

    /**
     * Odśwież access token używając refresh tokena.
     *
     * TODO: Zaimplementuj logikę odświeżania tokena.
     *
     * @param string $nip
     * @param string $refreshToken
     * @return AuthenticationResponse
     * @throws KsefApiException
     */
    public function refreshToken(string $nip, string $refreshToken): AuthenticationResponse
    {
        // TODO: Implementacja
        throw new KsefApiException('Метода refreshToken nie jest jeszcze zaimplementowana');
    }
}
