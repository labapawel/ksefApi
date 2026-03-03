<?php

return [
    'environment' => env('KSEF_ENV', 'demo'),
    'api_url' => env('KSEF_URL', 'https://api-demo.ksef.mf.gov.pl/v2'),
    'invoice_encryption_key' => env('KSEF_INVOICE_ENCRYPTION_KEY'),

    'credentials_table' => env('KSEF_CREDENTIALS_TABLE', 'ksef_credentials'),
    'invoices_table' => env('KSEF_INVOICES_TABLE', 'ksef_invoices'),
];
