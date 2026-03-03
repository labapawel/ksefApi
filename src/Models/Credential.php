<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    /**
     * Nazwa tabeli bazy danych.
     *
     * @var string
     */
    protected $table = 'ksef_credentials';

    /**
     * Atrybuty które mogą być masowo przypisane.
     *
     * @var list<string>
     */
    protected $fillable = [
        'environment',
        'nip',
        'api_url',
        'ksef_token_encrypted',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'certificate_encrypted',
        'private_key_encrypted',
        'certificate_password_encrypted',
        'challenge_token_received_at',
        'challenge_token_expires_at',
        'token_expires_at',
        'scopes',
        'permissions',
        'meta',
    ];

    /**
     * Atrybuty które powinny być konwertowane do natywnych typów.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'challenge_token_received_at' => 'datetime',
        'challenge_token_expires_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'scopes' => 'json',
        'permissions' => 'json',
        'meta' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Atrybuty które powinny być zaszyfrowane.
     *
     * @var list<string>
     */
    protected $encrypted = [
        'ksef_token_encrypted',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'certificate_encrypted',
        'private_key_encrypted',
        'certificate_password_encrypted',
    ];

    /**
     * Filtruj poświadczenia dla danego środowiska.
     *
     * @param Builder<Credential> $query
     * @param string $environment
     * @return Builder<Credential>
     */
    public function scopeEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Filtruj poświadczenia dla danego NIP.
     *
     * @param Builder<Credential> $query
     * @param string $nip
     * @return Builder<Credential>
     */
    public function scopeNip(Builder $query, string $nip): Builder
    {
        return $query->where('nip', $nip);
    }

    /**
     * Filtruj poświadczenia dla danego środowiska i NIP.
     *
     * @param Builder<Credential> $query
     * @param string $environment
     * @param string $nip
     * @return Builder<Credential>
     */
    public function scopeForEnvironmentAndNip(Builder $query, string $environment, string $nip): Builder
    {
        return $query->where('environment', $environment)->where('nip', $nip);
    }

    /**
     * Filtruj poświadczenia które mają komplet certyfikatu.
     *
     * @param Builder<Credential> $query
     * @return Builder<Credential>
     */
    public function scopeWithCertificate(Builder $query): Builder
    {
        return $query
            ->whereNotNull('certificate_encrypted')
            ->whereNotNull('private_key_encrypted')
            ->whereNotNull('certificate_password_encrypted');
    }

    /**
     * Filtruj poświadczenia dla danego URL API.
     *
     * @param Builder<Credential> $query
     * @param string $apiUrl
     * @return Builder<Credential>
     */
    public function scopeApiUrl(Builder $query, string $apiUrl): Builder
    {
        return $query->where('api_url', $apiUrl);
    }

    /**
     * Filtruj poświadczenia z ważnym access tokenem.
     *
     * @param Builder<Credential> $query
     * @return Builder<Credential>
     */
    public function scopeValidToken(Builder $query): Builder
    {
        return $query->where('token_expires_at', '>', now());
    }

    /**
     * Filtruj poświadczenia z ważnym challenge tokenem.
     *
     * @param Builder<Credential> $query
     * @return Builder<Credential>
     */
    public function scopeValidChallengeToken(Builder $query): Builder
    {
        return $query->where('challenge_token_expires_at', '>', now());
    }

    /**
     * Sprawdź czy token dostępu wygasł.
     *
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Sprawdź czy token dostępu jest jeszcze ważny.
     *
     * @return bool
     */
    public function isTokenValid(): bool
    {
        return ! $this->isTokenExpired();
    }

    /**
     * Sprawdź czy challenge token wygasł.
     *
     * @return bool
     */
    public function isChallengeTokenExpired(): bool
    {
        if ($this->challenge_token_expires_at === null) {
            return true;
        }

        return $this->challenge_token_expires_at->isPast();
    }

    /**
     * Sprawdź czy challenge token jest jeszcze ważny.
     *
     * @return bool
     */
    public function isChallengeTokenValid(): bool
    {
        return ! $this->isChallengeTokenExpired();
    }
}
