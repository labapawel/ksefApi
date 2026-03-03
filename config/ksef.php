<?php

return [
    'environment' => env('KSEF_ENV', 'demo'),
    'api_url' => env('KSEF_URL', 'https://api-demo.ksef.mf.gov.pl/v2'),

    'credentials_table' => env('KSEF_CREDENTIALS_TABLE', 'ksef_credentials'),
    'invoices_table' => env('KSEF_INVOICES_TABLE', 'ksef_invoices'),

    // Czas ważności challenge tokena (w minutach) — po tym czasie wymagane ponowne logowanie
    'challenge_token_lifetime' => (int) env('KSEF_CHALLENGE_TOKEN_LIFETIME', 10),

    // Timeout dla żądań do API (w sekundach)
    'api_timeout' => (int) env('KSEF_API_TIMEOUT', 30),
];
