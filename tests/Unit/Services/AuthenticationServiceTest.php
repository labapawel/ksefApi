<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Unit\Services;

use Labapawel\KsefApi\Models\Credential;
use Labapawel\KsefApi\Services\AuthenticationService;
use Labapawel\KsefApi\Tests\Fixtures\DataFactory;
use Labapawel\KsefApi\Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    /**
     * Test: Sprawdzenie czy poświadczenia mogą być pobrane po zalogowaniu.
     */
    public function test_can_get_credentials_after_login(): void
    {
        $nip = '1234567890';
        $credential = DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
        ]);

        $service = new AuthenticationService();
        $retrieved = $service->getCredentials($nip);

        $this->assertNotNull($retrieved);
        $this->assertEquals($nip, $retrieved->nip);
    }

    /**
     * Test: Sprawdzenie czy getCredentials zwraca null dla nieistniejącego NIP.
     */
    public function test_get_credentials_returns_null_for_non_existent_nip(): void
    {
        $service = new AuthenticationService();
        $credential = $service->getCredentials('9999999999');

        $this->assertNull($credential);
    }

    /**
     * Test: hasValidCredentials zwraca true dla ważnych poświadczeń.
     */
    public function test_has_valid_credentials_returns_true_for_valid_token(): void
    {
        $nip = '1234567890';
        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
            'token_expires_at' => now()->addHours(24),
        ]);

        $service = new AuthenticationService();
        $hasValid = $service->hasValidCredentials($nip);

        $this->assertTrue($hasValid);
    }

    /**
     * Test: hasValidCredentials zwraca false gdy poświadczenia nie istnieją.
     */
    public function test_has_valid_credentials_returns_false_when_not_exists(): void
    {
        $service = new AuthenticationService();
        $hasValid = $service->hasValidCredentials('9999999999');

        $this->assertFalse($hasValid);
    }

    /**
     * Test: hasValidCredentials zwraca false gdy access token wygasł.
     */
    public function test_has_valid_credentials_returns_false_when_token_expired(): void
    {
        $nip = '1234567890';
        DataFactory::createExpiredCredential([
            'nip' => $nip,
            'environment' => 'demo',
        ]);

        $service = new AuthenticationService();
        $hasValid = $service->hasValidCredentials($nip);

        $this->assertFalse($hasValid);
    }

    /**
     * Test: getAccessToken zwraca token dla istniejących poświadczeń.
     */
    public function test_get_access_token_returns_token(): void
    {
        $nip = '1234567890';
        $tokenValue = 'test_access_token_value';

        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
            'access_token_encrypted' => $tokenValue,
        ]);

        $service = new AuthenticationService();
        $token = $service->getAccessToken($nip);

        $this->assertNotNull($token);
        $this->assertEquals($tokenValue, $token);
    }

    /**
     * Test: getAccessToken zwraca null dla nieistniejącego NIP.
     */
    public function test_get_access_token_returns_null_for_non_existent_nip(): void
    {
        $service = new AuthenticationService();
        $token = $service->getAccessToken('9999999999');

        $this->assertNull($token);
    }

    /**
     * Test: logout usuwa poświadczenia.
     */
    public function test_logout_deletes_credentials(): void
    {
        $nip = '1234567890';
        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
        ]);

        $this->assertDatabaseHas('ksef_credentials', [
            'nip' => $nip,
        ]);

        $service = new AuthenticationService();
        $result = $service->logout($nip);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('ksef_credentials', [
            'nip' => $nip,
        ]);
    }

    /**
     * Test: logout zwraca false gdy poświadczenia nie istnieją.
     */
    public function test_logout_returns_false_when_not_exists(): void
    {
        $service = new AuthenticationService();
        $result = $service->logout('9999999999');

        $this->assertFalse($result);
    }

    /**
     * Test: Wielokrotne logowania dla tego samego NIP w różnych środowiskach.
     */
    public function test_can_store_credentials_for_same_nip_different_environments(): void
    {
        $nip = '1234567890';

        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'test',
        ]);

        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
        ]);

        $demoService = new AuthenticationService();
        $demoCredential = $demoService->getCredentials($nip);

        $this->assertNotNull($demoCredential);
        $this->assertEquals('demo', $demoCredential->environment);
    }

    /**
     * Test: Aktualizacja istniejących poświadczeń przy ponownym logowaniu.
     */
    public function test_updating_existing_credentials_maintains_nip(): void
    {
        $nip = '1234567890';
        $firstToken = 'first_access_token';
        $secondToken = 'second_access_token';

        DataFactory::createCredential([
            'nip' => $nip,
            'environment' => 'demo',
            'access_token_encrypted' => $firstToken,
        ]);

        Credential::forEnvironmentAndNip('demo', $nip)->first()
            ->update(['access_token_encrypted' => $secondToken]);

        $service = new AuthenticationService();
        $credential = $service->getCredentials($nip);

        $this->assertEquals($secondToken, $credential->access_token_encrypted);
    }
}
