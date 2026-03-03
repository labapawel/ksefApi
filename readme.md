# KsefApi

Komponent Laravel do integracji z API KSeF.

Repozytorium/paczka: `labapawel/ksef-api`

## Zakres

Aktualny szkielet paczki zawiera:

- provider Laravel z publikacją konfiguracji i migracji
- plik konfiguracyjny paczki: `config/ksef.php`
- migracje dla tabel z poświadczeniami i fakturami
- **Modele Eloquent**: `Credential` i `Invoice` z Scopes i metodami pomocniczymi
- bazowe klasy placeholder: kontrakty/klienci/repozytoria/DTO

## Wymagania

- PHP `^8.2`
- Laravel `^10 | ^11 | ^12`
- rozszerzenia PHP: `curl`, `json`, `openssl`

## Instalacja

```bash
composer require labapawel/ksef-api
```

## Publikacja konfiguracji

```bash
php artisan vendor:publish --tag=ksef-config
```

## Publikacja migracji

```bash
php artisan vendor:publish --tag=ksef-migrations
php artisan migrate
```

Paczka rejestruje też migracje przez `loadMigrationsFrom`, więc publikacja jest opcjonalna, jeśli uruchamiasz migracje paczki bezpośrednio.

## Zmienne środowiskowe

Przykładowe wartości `.env`:

```dotenv
KSEF_ENV=demo
KSEF_URL=https://api-demo.ksef.mf.gov.pl/v2
KSEF_INVOICE_ENCRYPTION_KEY=change_me_to_strong_random_key

KSEF_CREDENTIALS_TABLE=ksef_credentials
KSEF_INVOICES_TABLE=ksef_invoices
```

### Opis parametrów

| Parametr | Wymagany | Opis |
|----------|----------|------|
| `KSEF_ENV` | ✅ | Środowisko KSeF: `test`, `demo`, lub `prod`. Wpływa na wybór API URL i certyfikatów. |
| `KSEF_URL` | ✅ | URL API KSeF dla wybranego środowiska. Domyślnie: `https://api-demo.ksef.mf.gov.pl/v2` (demo) |
| `KSEF_INVOICE_ENCRYPTION_KEY` | ✅ | Klucz szyfrujący do AES-256-CBC dla faktur. Powinien być silny, losowo wygenerowany (min. 32 znaki). **Nigdy nie commituj tego do Gita!** |
| `KSEF_CREDENTIALS_TABLE` | ❌ | Nazwa tabeli dla poświadczeń. Domyślnie: `ksef_credentials` |
| `KSEF_INVOICES_TABLE` | ❌ | Nazwa tabeli dla faktur. Domyślnie: `ksef_invoices` |

### Generowanie klucza szyfrującego

```bash
# Wygeneruj losowy klucz 32-bajtowy zakodowany base64
php -r "echo 'KSEF_INVOICE_ENCRYPTION_KEY=' . base64_encode(openssl_random_pseudo_bytes(32)) . PHP_EOL;"
```

### Wartości dla poszczególnych środowisk

**Środowisko TEST:**
```dotenv
KSEF_ENV=test
KSEF_URL=https://api-test.ksef.mf.gov.pl/v2
```

**Środowisko DEMO:**
```dotenv
KSEF_ENV=demo
KSEF_URL=https://api-demo.ksef.mf.gov.pl/v2
```

**Środowisko PRODUCTION:**
```dotenv
KSEF_ENV=prod
KSEF_URL=https://api.ksef.mf.gov.pl/v2
```

⚠️ **Ważne**: Każde środowisko ma zupełnie inne certyfikaty, tokeny i dane poświadczeń. Nigdy nie mieszaj środowisk!

## Autentykacja

### Logowanie do KSeF

Paczka automatycznie zarządza challenge tokenami i access tokenami.

#### Używając AuthenticationService (rekomendowane)

```php
use Labapawel\KsefApi\Services\AuthenticationService;

$auth = new AuthenticationService();

// Zaloguj się
$response = $auth->login(
    certificatePath: '/path/to/cert.pem',
    privateKeyPath: '/path/to/key.pem',
    certificatePassword: 'hasło123',
    nip: '1234567890',
);

// Sprawdź czy poświadczenia są ważne
if ($auth->hasValidCredentials('1234567890')) {
    $token = $auth->getAccessToken('1234567890');
    // Użyj token do żądań API
}

// Wyloguj się (usuń poświadczenia)
$auth->logout('1234567890');
```

#### Używając KsefAuthClient (niski poziom)

```php
use Labapawel\KsefApi\Clients\KsefAuthClient;
use Labapawel\KsefApi\DTO\Credentials;

$client = new KsefAuthClient();

$credentials = new Credentials(
    nip: '1234567890',
    ksefToken: '',
    certificatePath: '/path/to/cert.pem',
    privateKeyPath: '/path/to/key.pem',
    certificatePassword: 'hasło123',
);

$authResponse = $client->authenticate($credentials, '1234567890');

// $authResponse zawiera:
// - challengeToken (tymczasowy)
// - accessToken (JWT)
// - refreshToken (JWT)
// - tokenExpiresAt (Carbon)
// - challengeTokenReceivedAt (Carbon)
```

### Challenge Token Lifecycle

Pakiet automatycznie zarządza **challenge tokenami**:

1. **Pierwszy request** — API zwraca nowy challenge token (ważny przez N minut, domyślnie 10)
2. **Challenge token przechowywany** — zapisany w bazie w `ksef_token_encrypted`
3. **Challenge token wygasa** — jeśli upłynęło N minut od otrzymania
4. **Automatyczne odświeżenie** — przy następnym logowaniu pobierany jest nowy token

Czas ważności challenge tokena kontroluje parametr `.env`:

```dotenv
KSEF_CHALLENGE_TOKEN_LIFETIME=10  # minuty
KSEF_API_TIMEOUT=30              # sekundy
```

### Poświadczenia w bazie danych

Wszystkie dane są automatycznie szyfrowane i przechowywane w tabeli `ksef_credentials`:

```php
use Labapawel\KsefApi\Models\Credential;

// Pobierz poświadczenia
$credential = Credential::forEnvironmentAndNip('demo', '1234567890')->first();

// Dostęp do tokenów (automatyczne deszyfrowanie)
$challengeToken = $credential->ksef_token_encrypted;
$accessToken = $credential->access_token_encrypted;
$refreshToken = $credential->refresh_token_encrypted;

// Sprawdź ważność
if ($credential->isTokenValid()) {
    // Access token nie wygasł
}

if ($credential->isTokenExpired()) {
    // Access token wygasł, wymagane odświeżenie
}

// Meta zawiera czas otrzymania challenge tokena
$receivedAt = $credential->meta['challenge_token_received_at'] ?? null;
```

## Model danych

### `ksef_credentials`

Tabela przechowuje zaszyfrowane dane wrażliwe KSeF dla pary `environment + nip`:

- zaszyfrowane: token KSeF, access token, refresh token
- zaszyfrowane: certyfikat, klucz prywatny, hasło do certyfikatu
- metadane: data wygaśnięcia tokena, dodatkowe `meta` (json)

### `ksef_invoices`

Tabela przechowuje metadane biznesowe faktury oraz zaszyfrowany XML:

- jawne/wyszukiwalne: kierunek, numer i data faktury, NIP/nazwa sprzedawcy i nabywcy
- pola integracyjne: numer KSeF, numer referencyjny, status
- zaszyfrowany payload: pełny XML (`xml_encrypted`)

## Modele Eloquent

Paczka udostępnia dwa modele Eloquent z potężnymi scopes i metodami pomocniczymi.

### Model `Credential`

Przechowuje poświadczenia KSeF dla pary `environment + nip`.

```php
use Labapawel\KsefApi\Models\Credential;

// Szukaj poświadczeń
$credential = Credential::forEnvironmentAndNip('demo', '1234567890')->first();

// Dostępne scopes
Credential::environment('demo')->get();
Credential::nip('1234567890')->get();
Credential::forEnvironmentAndNip('demo', '1234567890')->first();

// Sprawdzenie ważności tokena
if ($credential->isTokenValid()) {
    // token jest jeszcze ważny
}

if ($credential->isTokenExpired()) {
    // token wygasł, wymagane odświeżenie
}
```

### Model `Invoice`

Przechowuje metadane faktury oraz zaszyfrowany XML.

```php
use Labapawel\KsefApi\Models\Invoice;

// Szukaj faktur
$invoices = Invoice::sale()->accepted()->get();
$outgoing = Invoice::direction('sale')->sellerNip('7986711699')->get();
$income = Invoice::purchase()->pending()->get();

// Dostępne scopes
Invoice::direction('sale')->get();                 // sale | purchase
Invoice::sale()->get();                            // alias dla sale direction
Invoice::purchase()->get();                        // alias dla purchase direction
Invoice::status('accepted')->get();                // pending|processing|accepted|rejected
Invoice::pending()->get();                         // oczekujące
Invoice::processing()->get();                      // w trakcie przetwarzania
Invoice::accepted()->get();                        // zaakceptowane
Invoice::rejected()->get();                        // odrzucone
Invoice::sellerNip('7986711699')->get();          // po NIP sprzedawcy
Invoice::buyerNip('5471740555')->get();           // po NIP nabywcy
Invoice::ksefNumber('20260303-EE-3FAFFEF000')->get(); // po numerze KSeF

// Metody sprawdzające status
if ($invoice->isAccepted()) {
    // Faktura została zaakceptowana
}

if ($invoice->isRejected()) {
    // Faktura została odrzucona
}

if ($invoice->isPending()) {
    // Faktura czeka na przetwarzanie
}

if ($invoice->isProcessed()) {
    // Faktura została przetworzona przez KSeF
}
```

## Planowane API (szkielet gotowy)

- `Labapawel\\KsefApi\\Clients\\KsefAuthClient`
- `Labapawel\\KsefApi\\Clients\\KsefInvoiceClient`
- `Labapawel\\KsefApi\\Repositories\\CredentialRepository`
- `Labapawel\\KsefApi\\Repositories\\InvoiceRepository`
- `Labapawel\\KsefApi\\Support\\EncryptionService`
- `Labapawel\\KsefApi\\Support\\XmlInvoiceParser`

## Szyfrowanie

Modele `Credential` i `Invoice` automatycznie szyfrują wrażliwe dane za pomocą klucza aplikacyjnego Laravel:

**Model `Credential` szyfruje:**
- `ksef_token_encrypted` — Token wyzwania KSeF
- `access_token_encrypted` — JWT token dostępu
- `refresh_token_encrypted` — JWT token odświeżający
- `certificate_encrypted` — Certyfikat X.509
- `private_key_encrypted` — Klucz prywatny RSA
- `certificate_password_encrypted` — Hasło do certyfikatu

**Model `Invoice` szyfruje:**
- `xml_encrypted` — Pełna zawartość faktury w formacie XML

Szyfrowanie/deszyfrowanie odbywa się automatycznie podczas odczytu i zapisu:

```php
// Zapis — dane są automatycznie szyfrowane
$credential = Credential::create([
    'environment' => 'demo',
    'nip' => '1234567890',
    'access_token_encrypted' => 'secret_token_value', // będzie zaszyfrowany
]);

// Odczyt — dane są automatycznie deszyfrowane
echo $credential->access_token_encrypted; // wyświetli zdeszyfowany token
```

## Uwagi bezpieczeństwa

- Nie zapisuj tokenów/certyfikatów/kluczy prywatnych w jawnych kolumnach.
- Używaj dedykowanego klucza aplikacyjnego do szyfrowania danych komponentu.
- Rotuj klucze szyfrujące z kontrolowaną strategią migracji danych.
- Trzymaj środowiska KSeF (`test`, `demo`, `prod`) całkowicie rozdzielone.

## Typowe użycie

### Przechowywanie poświadczeń

```php
use Labapawel\KsefApi\Models\Credential;

// Zapisz poświadczenia dla danego środowiska i NIP
Credential::create([
    'environment' => 'demo',
    'nip' => '7986711699',
    'access_token_encrypted' => $token,
    'refresh_token_encrypted' => $refreshToken,
    'certificate_encrypted' => $certPem,
    'private_key_encrypted' => $privateKeyPem,
    'certificate_password_encrypted' => $certPassword,
    'token_expires_at' => now()->addHours(24),
    'meta' => ['issuer' => 'mf.gov.pl'],
]);

// Pobierz późno poświadczenia
$cred = Credential::forEnvironmentAndNip('demo', '7986711699')->firstOrFail();

// Sprawdź czy token nie wygasł
if ($cred->isTokenExpired()) {
    // Odśwież token tutaj
}
```

### Rejestrowanie faktury

```php
use Labapawel\KsefApi\Models\Invoice;

Invoice::create([
    'direction' => 'sale',                           // sale lub purchase
    'invoice_number' => 'TEST/2026/03/03/001',
    'invoice_date' => '2026-03-03',
    'seller_nip' => '7986711699',
    'seller_name' => 'Moja Firma Sp. z o.o.',
    'buyer_nip' => '5471740555',
    'buyer_name' => 'Odbiorca Sp. z o.o.',
    'xml_encrypted' => $xmlContent,                  // będzie zaszyfrowany
    'xml_hash' => hash('sha256', $xmlContent),
    'status' => 'pending',
    'meta' => [
        'gross_amount' => 1234.56,
        'tax_amount' => 286.66,
        'net_amount' => 947.90,
        'line_items_count' => 2,
    ],
]);
```

### Wyszukiwanie faktur

```php
// Wszystkie zatwierdzone faktury sprzedane w marcu 2026
$invoices = Invoice::sale()
    ->accepted()
    ->whereMonth('invoice_date', 3)
    ->whereYear('invoice_date', 2026)
    ->get();

// Faktury niezgodnie oczekujące na przetwarzanie od danego sprzedawcy
$pending = Invoice::pending()
    ->sellerNip('7986711699')
    ->orderBy('created_at', 'desc')
    ->get();
```

## Development

Po sklonowaniu uruchom:

```bash
composer install
composer dump-autoload
```

## Testowanie

Paczka zawiera kompleksowy zestaw testów dla modeli Eloquent.

### Instalacja zależności testowych

```bash
composer install --dev
```

### Uruchamianie testów

```bash
# Wszystkie testy
./vendor/bin/phpunit

# Tylko testy modeli
./vendor/bin/phpunit tests/Unit/Models

# Tylko testy Credential
./vendor/bin/phpunit tests/Unit/Models/CredentialTest.php

# Tylko testy Invoice
./vendor/bin/phpunit tests/Unit/Models/InvoiceTest.php

# Z pokryciem kodu
./vendor/bin/phpunit --coverage-html coverage
```

### Struktura testów

- `tests/TestCase.php` — Bazowa klasa testowa z konfiguracją środowiska testowego
- `tests/Unit/Models/CredentialTest.php` — 17 testów dla modelu Credential
- `tests/Unit/Models/InvoiceTest.php` — 25 testów dla modelu Invoice
- `tests/Unit/Models/PackageInstallationTest.php` — Testy instalacji paczki
- `tests/Fixtures/DataFactory.php` — Fabryka testowych danych ułatwiająca tworzenie instancji testowych

### Używanie DataFactory w testach

Fabryka `DataFactory` zawiera pomocne metody do tworzenia testowych instancji:

```php
use Labapawel\KsefApi\Tests\Fixtures\DataFactory;

// Utwórz jedno poświadczenie
$credential = DataFactory::createCredential();

// Utwórz kilka poświadczeń dla środowiska demo
$credentials = DataFactory::createCredentials(5, 'demo');

// Utwórz jedną fakturę
$invoice = DataFactory::createInvoice();

// Utwórz zaakceptowaną fakturę
$invoice = DataFactory::createAcceptedInvoice();

// Utwórz wygaśnięte poświadczenie
$credential = DataFactory::createExpiredCredential();
```

## Licencja

MIT
