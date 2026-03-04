<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Fixtures;

use Illuminate\Support\Carbon;
use Labapawel\KsefApi\Models\Credential;
use Labapawel\KsefApi\Models\Invoice;

/**
 * Fabryka danych do testów.
 *
 * Ułatwia tworzenie instancji testowych modeli z realistycznymi danymi.
 */
class DataFactory
{
    /**
     * Utwórz poświadczenie testowe z domyślnymi wartościami.
     *
     * @param array<string, mixed> $overrides
     * @return Credential
     */
    public static function createCredential(array $overrides = []): Credential
    {
        $defaults = [
            'environment' => 'demo',
            'nip' => '1234567890',
            'api_url' => 'https://ksef-demo.mf.gov.pl/api',
            'ksef_token_encrypted' => 'challenge_token_' . uniqid(),
            'access_token_encrypted' => 'test_access_token_' . uniqid(),
            'refresh_token_encrypted' => 'test_refresh_token_' . uniqid(),
            'challenge_token_received_at' => now(),
            'challenge_token_expires_at' => now()->addMinutes(10),
            'token_expires_at' => now()->addHours(24),
            // Dane firmy wystawiającej faktury
            'company_name' => 'Testowa Firma Sp. z o.o.',
            'company_nip' => '7986711699',
            'company_regon' => '014213425',
            'street' => 'Marszałkowska',
            'street_number' => '123',
            'apartment_number' => 'A/4',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'email' => 'kontakt@testowa-firma.pl',
            'phone' => '+48123456789',
            'bank_account' => 'PL61109010140000071219812874',
            'scopes' => ['InvoiceWrite', 'InvoiceRead'],
            'permissions' => ['send_invoice', 'get_invoice'],
            'meta' => [
                'issuer' => 'mf.gov.pl',
            ],
        ];

        return Credential::create(array_merge($defaults, $overrides));
    }

    /**
     * Utwórz wiele poświadczeń dla testowania.
     *
     * @param int $count
     * @param string $environment
     * @return \Illuminate\Database\Eloquent\Collection<int, Credential>
     */
    public static function createCredentials(int $count = 3, string $environment = 'demo'): \Illuminate\Database\Eloquent\Collection
    {
        $credentials = \collect();

        for ($i = 0; $i < $count; $i++) {
            $credentials->push(
                self::createCredential([
                    'environment' => $environment,
                    'nip' => (string) (1000000000 + $i),
                ])
            );
        }

        return $credentials;
    }

    /**
     * Utwórz fakturę testową z domyślnymi wartościami.
     *
     * @param array<string, mixed> $overrides
     * @return Invoice
     */
    public static function createInvoice(array $overrides = []): Invoice
    {
        $defaults = [
            'direction' => 'sale',
            'invoice_number' => 'TEST/' . date('Y/m/d') . '/' . mt_rand(1000, 9999),
            'invoice_date' => now()->format('Y-m-d'),
            'seller_nip' => '7986711699',
            'seller_name' => 'Moja Firma Sp. z o.o.',
            'buyer_nip' => '5471740555',
            'buyer_name' => 'Odbiorca Sp. z o.o.',
            'environment' => 'demo',
            'xml_encrypted' => '<?xml version="1.0"?><invoice><amount>100.00</amount></invoice>',
            'status' => 'pending',
            'is_signed' => false,
            'submitted_at' => null,
            'meta' => [
                'gross_amount' => 100.00,
                'tax_amount' => 23.00,
                'net_amount' => 77.00,
            ],
        ];

        return Invoice::create(array_merge($defaults, $overrides));
    }

    /**
     * Utwórz wiele faktur dla testowania.
     *
     * @param int $count
     * @param string $direction
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    public static function createInvoices(int $count = 5, string $direction = 'sale', string $status = 'pending'): \Illuminate\Database\Eloquent\Collection
    {
        $invoices = \collect();

        for ($i = 0; $i < $count; $i++) {
            $invoices->push(
                self::createInvoice([
                    'environment' => 'demo',
                    'direction' => $direction,
                    'status' => $status,
                    'invoice_number' => 'TEST/' . date('Y/m/d') . '/' . ($i + 1),
                ])
            );
        }

        return $invoices;
    }

    /**
     * Utwórz zaakceptowaną fakturę.
     *
     * @param array<string, mixed> $overrides
     * @return Invoice
     */
    public static function createAcceptedInvoice(array $overrides = []): Invoice
    {
        return self::createInvoice(array_merge([
            'status' => 'accepted',
            'is_signed' => true,
            'submitted_at' => now()->subMinutes(5),
            'processed_at' => now(),
        ], $overrides));
    }

    /**
     * Utwórz odrzuconą fakturę.
     *
     * @param array<string, mixed> $overrides
     * @return Invoice
     */
    public static function createRejectedInvoice(array $overrides = []): Invoice
    {
        return self::createInvoice(array_merge([
            'status' => 'rejected',
            'submitted_at' => now()->subMinutes(5),
            'processed_at' => now(),
            'error_details' => [
                'error_code' => 'INVALID_SIGNATURE',
                'error_message' => 'Podpis XAdES jest nieprawidłowy',
            ],
        ], $overrides));
    }

    /**
     * Utwórz wygaśnięte poświadczenie.
     *
     * @param array<string, mixed> $overrides
     * @return Credential
     */
    public static function createExpiredCredential(array $overrides = []): Credential
    {
        return self::createCredential(array_merge([
            'token_expires_at' => now()->subHours(1),
        ], $overrides));
    }


    /**
     * Utwórz wygaśnięty challenge token.
     *
     * @param array<string, mixed> $overrides
     * @return Credential
     */
    public static function createExpiredChallengeToken(array $overrides = []): Credential
    {
        return self::createCredential(array_merge([
            'challenge_token_received_at' => now()->subMinutes(15),
            'challenge_token_expires_at' => now()->subMinutes(5),
        ], $overrides));
    }

    /**
     * Utwórz podpisaną fakturę.
     *
     * @param array<string, mixed> $overrides
     * @return Invoice
     */
    public static function createSignedInvoice(array $overrides = []): Invoice
    {
        return self::createInvoice(array_merge([
            'is_signed' => true,
            'signature_encrypted' => '<?xml version="1.0"?><xades>signature</xades>',
        ], $overrides));
    }

    /**
     * Utwórz poświadczenie które wkrótce wygaśnie.
     *
     * @param array<string, mixed> $overrides
     * @return Credential
     */
    public static function createSoonToExpireCredential(array $overrides = []): Credential
    {
        return self::createCredential(array_merge([
            'token_expires_at' => now()->addHour(),
        ], $overrides));
    }
}
