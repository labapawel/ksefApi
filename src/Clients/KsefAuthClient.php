<?php

declare(strict_types=1);

namespace Labap\KsefApi\Clients;

class KsefAuthClient
{
    public function authenticate(array $credentials): array
    {
        // TODO: implement challenge + token exchange for KSeF.
        return [
            'accessToken' => null,
            'refreshToken' => null,
            'expiresAt' => null,
        ];
    }
}
