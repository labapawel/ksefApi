<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\DTO;

use Illuminate\Support\Carbon;

/**
 * Odpowiedź z API logowania KSeF.
 */
final class AuthenticationResponse
{
    /**
     * @param string $challengeToken    Tymczasowy token wyzwania z API
     * @param string $accessToken       JWT token dostępu
     * @param string $refreshToken      JWT token odświeżający
     * @param Carbon $tokenExpiresAt    Czas wygaśnięcia access tokena
     * @param Carbon $challengeTokenReceivedAt  Czas otrzymania challenge tokena
     */
    public function __construct(
        public readonly string $challengeToken,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly Carbon $tokenExpiresAt,
        public readonly Carbon $challengeTokenReceivedAt,
    ) {
    }

    /**
     * Sprawdź czy challenge token wygasł.
     *
     * @param int $lifetimeMinutes Czas ważności tokena w minutach
     * @return bool
     */
    public function isChallengeTokenExpired(int $lifetimeMinutes = 10): bool
    {
        $expiresAt = $this->challengeTokenReceivedAt->addMinutes($lifetimeMinutes);
        return now()->isAfter($expiresAt);
    }

    /**
     * Sprawdź czy access token wygasł.
     *
     * @return bool
     */
    public function isAccessTokenExpired(): bool
    {
        return now()->isAfter($this->tokenExpiresAt);
    }
}
