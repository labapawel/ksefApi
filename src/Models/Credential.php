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
        'ksef_token_encrypted',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'certificate_encrypted',
        'private_key_encrypted',
        'certificate_password_encrypted',
        'token_expires_at',
        'meta',
    ];

    /**
     * Atrybuty które powinny być konwertowane do natywnych typów.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'token_expires_at' => 'datetime',
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
}
