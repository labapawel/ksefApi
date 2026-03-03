<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Unit\Models;

use Labapawel\KsefApi\Models\Credential;
use Labapawel\KsefApi\Tests\TestCase;

class CredentialTest extends TestCase
{
    /**
     * Test: Sprawdzenie czy model Credential może być utworzony.
     */
    public function test_credential_can_be_created(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'access_token_encrypted' => 'test_token',
            'token_expires_at' => now()->addHours(24),
            'meta' => ['key' => 'value'],
        ]);

        $this->assertDatabaseHas('ksef_credentials', [
            'environment' => 'demo',
            'nip' => '1234567890',
        ]);

        $this->assertIsInt($credential->id);
    }

    /**
     * Test: Sprawdzenie czy atrybut token_expires_at jest konwertowany na Carbon.
     */
    public function test_token_expires_at_is_cast_to_datetime(): void
    {
        $expiresAt = now()->addHours(24);

        $credential = Credential::create([
            'environment' => 'test',
            'nip' => '9876543210',
            'token_expires_at' => $expiresAt,
        ]);

        $credential = $credential->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $credential->token_expires_at);
        $this->assertTrue($credential->token_expires_at->isSameAs($expiresAt, 'minute'));
    }

    /**
     * Test: Sprawdzenie czy meta jest konwertowane na JSON.
     */
    public function test_meta_is_cast_to_json(): void
    {
        $meta = [
            'issuer' => 'mf.gov.pl',
            'scopes' => ['InvoiceWrite', 'InvoiceRead'],
            'permissions' => ['write', 'read'],
        ];

        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'meta' => $meta,
        ]);

        $credential = $credential->fresh();

        $this->assertIsArray($credential->meta);
        $this->assertEquals($meta, $credential->meta);
    }

    /**
     * Test: Scope "environment".
     */
    public function test_scope_environment(): void
    {
        Credential::create([
            'environment' => 'demo',
            'nip' => '1111111111',
        ]);

        Credential::create([
            'environment' => 'test',
            'nip' => '2222222222',
        ]);

        $demoCredentials = Credential::environment('demo')->get();

        $this->assertCount(1, $demoCredentials);
        $this->assertEquals('demo', $demoCredentials->first()->environment);
    }

    /**
     * Test: Scope "nip".
     */
    public function test_scope_nip(): void
    {
        Credential::create([
            'environment' => 'demo',
            'nip' => '1111111111',
        ]);

        Credential::create([
            'environment' => 'demo',
            'nip' => '2222222222',
        ]);

        $nipCredentials = Credential::nip('1111111111')->get();

        $this->assertCount(1, $nipCredentials);
        $this->assertEquals('1111111111', $nipCredentials->first()->nip);
    }

    /**
     * Test: Scope "forEnvironmentAndNip".
     */
    public function test_scope_for_environment_and_nip(): void
    {
        Credential::create([
            'environment' => 'demo',
            'nip' => '1111111111',
        ]);

        Credential::create([
            'environment' => 'test',
            'nip' => '1111111111',
        ]);

        $credential = Credential::forEnvironmentAndNip('demo', '1111111111')->first();

        $this->assertNotNull($credential);
        $this->assertEquals('demo', $credential->environment);
        $this->assertEquals('1111111111', $credential->nip);
    }

    /**
     * Test: Metoda "isTokenExpired" — token wygasł.
     */
    public function test_is_token_expired_returns_true_when_token_expired(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'token_expires_at' => now()->subHours(1),
        ]);

        $this->assertTrue($credential->isTokenExpired());
    }

    /**
     * Test: Metoda "isTokenExpired" — token jeszcze ważny.
     */
    public function test_is_token_expired_returns_false_when_token_valid(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'token_expires_at' => now()->addHours(1),
        ]);

        $this->assertFalse($credential->isTokenExpired());
    }

    /**
     * Test: Metoda "isTokenExpired" — brak daty wygaśnięcia (uznawane za wygasłe).
     */
    public function test_is_token_expired_returns_true_when_no_expiration_date(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'token_expires_at' => null,
        ]);

        $this->assertTrue($credential->isTokenExpired());
    }

    /**
     * Test: Metoda "isTokenValid" — token ważny.
     */
    public function test_is_token_valid_returns_true_when_token_not_expired(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'token_expires_at' => now()->addHours(12),
        ]);

        $this->assertTrue($credential->isTokenValid());
    }

    /**
     * Test: Metoda "isTokenValid" — token wygasł.
     */
    public function test_is_token_valid_returns_false_when_token_expired(): void
    {
        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'token_expires_at' => now()->subHours(1),
        ]);

        $this->assertFalse($credential->isTokenValid());
    }

    /**
     * Test: Warunek unikalności (environment, nip).
     */
    public function test_unique_constraint_on_environment_and_nip(): void
    {
        Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
        ]);

        // Próba utworzenia duplikatu powinna rzucić wyjątek
        $this->expectException(\Illuminate\Database\QueryException::class);

        Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
        ]);
    }

    /**
     * Test: Szyfrowanie pola access_token_encrypted.
     */
    public function test_access_token_encrypted_is_encrypted(): void
    {
        $plainToken = 'my_plain_secret_token_12345';

        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'access_token_encrypted' => $plainToken,
        ]);

        // Pobierz wartość bezpośrednio z bazy (bez deszyfrowania)
        $encrypted = \DB::table('ksef_credentials')
            ->where('id', $credential->id)
            ->first()
            ->access_token_encrypted;

        // Sprawdź że nie ma plaintext w bazie
        $this->assertNotEquals($plainToken, $encrypted);

        // Sprawdź że model zwraca zdeszyfowany token
        $credential = $credential->fresh();
        $this->assertEquals($plainToken, $credential->access_token_encrypted);
    }

    /**
     * Test: Szyfrowanie pola certificate_encrypted.
     */
    public function test_certificate_encrypted_is_encrypted(): void
    {
        $plainCert = '-----BEGIN CERTIFICATE-----\nMIIC...-----END CERTIFICATE-----';

        $credential = Credential::create([
            'environment' => 'demo',
            'nip' => '1234567890',
            'certificate_encrypted' => $plainCert,
        ]);

        // Pobierz fresh instancję
        $credential = $credential->fresh();

        // Sprawdź że model zwraca zdeszyfowany certyfikat
        $this->assertEquals($plainCert, $credential->certificate_encrypted);

        // Sprawdź że w bazie jest zaszyfrowany
        $encrypted = \DB::table('ksef_credentials')
            ->where('id', $credential->id)
            ->first()
            ->certificate_encrypted;

        $this->assertNotEquals($plainCert, $encrypted);
    }

    /**
     * Test: Tworzenie wielu poświadczeń dla różnych środowisk tego samego NIP.
     */
    public function test_can_create_credentials_for_same_nip_different_environments(): void
    {
        $nip = '1234567890';

        Credential::create([
            'environment' => 'test',
            'nip' => $nip,
            'access_token_encrypted' => 'test_token',
        ]);

        Credential::create([
            'environment' => 'demo',
            'nip' => $nip,
            'access_token_encrypted' => 'demo_token',
        ]);

        Credential::create([
            'environment' => 'prod',
            'nip' => $nip,
            'access_token_encrypted' => 'prod_token',
        ]);

        $credentials = Credential::where('nip', $nip)->get();

        $this->assertCount(3, $credentials);
        $this->assertEquals(['test', 'demo', 'prod'], $credentials->pluck('environment')->sort()->values()->toArray());
    }
}
