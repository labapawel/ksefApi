<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KsefEnvironment extends Model
{
    protected $fillable = [
        'environment',
        'api_url',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('ksef.environments_table', 'ksef_environments');
    }

    /**
     * Relacja: środowisko ma wiele poświadczeń.
     *
     * @return HasMany
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class, 'ksef_environment_id');
    }

    /**
     * Scope: pobierz tylko aktywne środowiska.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: pobierz środowisko po identyfikatorze.
     *
     * @param Builder $query
     * @param string $environment
     * @return Builder
     */
    public function scopeByEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Pobierz środowisko po identyfikatorze (static helper).
     *
     * @param string $environment
     * @return self|null
     */
    public static function findByEnvironment(string $environment): ?self
    {
        return self::byEnvironment($environment)->first();
    }

    /**
     * Pobierz aktywne środowisko po identyfikatorze.
     *
     * @param string $environment
     * @return self|null
     */
    public static function findActiveByEnvironment(string $environment): ?self
    {
        return self::active()->byEnvironment($environment)->first();
    }
}
