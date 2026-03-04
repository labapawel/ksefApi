# KsefApi

Komponent Laravel do integracji z API KSeF.

Repozytorium/paczka: `labapawel/ksef-api`

## Quick Start

```bash
# 1. Instalacja pakietu
composer require labapawel/ksef-api

# 2. Wygeneruj klucz szyfrowania (jeśli nie masz APP_KEY)
php artisan key:generate

# 3. Uruchom migracje
php artisan migrate

# 4. (Opcjonalnie) Załaduj domyślne środowiska do bazy
php artisan db:seed --class=Labapawel\\KsefApi\\Database\\Seeders\\KsefEnvironmentSeeder

# 5. Zapisz poświadczenia (certyfikat + NIP + środowisko) w tabeli ksef_credentials

# Gotowe! Możesz teraz korzystać z modeli KsefEnvironment, Credential i Invoice
```

## Zakres

Aktualny szkielet paczki zawiera:

- provider Laravel z publikacją konfiguracji i migracji
- plik konfiguracyjny paczki: `config/ksef.php`
- migracje dla tabel: `ksef_environments`, `ksef_credentials`, `ksef_invoices`
- seeder dla domyślnych środowisk KSeF: `KsefEnvironmentSeeder`
- **Modele Eloquent**: `KsefEnvironment`, `Credential` i `Invoice` z Scopes i metodami pomocniczymi
- bazowe klasy placeholder: kontrakty/klienty/repozytoria/DTO
- serwisy wysokiego poziomu: `AuthenticationService`

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

## Generowanie klucza szyfrowania

Pakiet używa standardowego mechanizmu Laravel Encryption z kluczem `APP_KEY`.

```bash
php artisan key:generate
```

⚠️ **UWAGA:** Klucz `APP_KEY` służy do szyfrowania poświadczeń. Po zaszyfrowaniu danych zmiana klucza uniemożliwi ich odczyt!

⚠️ **Ostrzeżenie:** Zmiana klucza `APP_KEY` po zaszyfrowaniu danych w bazie uniemożliwi ich odczyt! Zawsze twórz backup klucza przed jego zmianą.

## Zmienne środowiskowe

Przykładowe wartości `.env`:

```dotenv
# Opcjonalne (z domyślnymi wartościami)
KSEF_CHALLENGE_TOKEN_LIFETIME=10
KSEF_API_TIMEOUT=30
KSEF_CREDENTIALS_TABLE=ksef_credentials
KSEF_ENVIRONMENTS_TABLE=ksef_environments
KSEF_INVOICES_TABLE=ksef_invoices
```

Konfiguracje środowisk (environment + api_url) są przechowywane w tabeli `ksef_environments`, co pozwala na obsługę wielu środowisk bez potrzeby zmian w `.env`. Poświadczenia są przechowywane w `ksef_credentials` z certyfikatem i innymi danymi wrażliwymi (kolumny `certificate_encrypted`, `private_key_encrypted`, `certificate_password_encrypted`).

### Opis parametrów

| Parametr | Wymagany | Domyślna wartość | Opis |
|----------|----------|------------------|------|
| `KSEF_CHALLENGE_TOKEN_LIFETIME` | ❌ | `10` | Czas ważności challenge tokena w minutach. Po tym czasie wymagane ponowne logowanie. |
| `KSEF_API_TIMEOUT` | ❌ | `30` | Timeout dla żądań HTTP do API KSeF w sekundach. |
| `KSEF_CREDENTIALS_TABLE` | ❌ | `ksef_credentials` | Nazwa tabeli w bazie danych dla poświadczeń KSeF. |
| `KSEF_ENVIRONMENTS_TABLE` | ❌ | `ksef_environments` | Nazwa tabeli w bazie danych dla konfiguracji środowisk KSeF. |
| `KSEF_INVOICES_TABLE` | ❌ | `ksef_invoices` | Nazwa tabeli w bazie danych dla faktur. |

### Środowiska KSeF

Pakiet przechowuje konfiguracje środowisk w tabeli `ksef_environments`, co pozwala na obsługę wielu środowisk (test, demo, prod) bez potrzeby konfigurowania wartości w `.env`.

#### Domyślne środowiska

Po uruchomieniu migracji i seedera, w bazie będą dostępne trzy środowiska:

```php
use Labapawel\KsefApi\Models\KsefEnvironment;

// Pobierz środowisko po identyfikatorze
$demo = KsefEnvironment::findByEnvironment('demo');

// Pobierz tylko aktywne środowiska
$active = KsefEnvironment::active()->get();

// Dostęp do danych
echo $demo->api_url;  // https://api-demo.ksef.mf.gov.pl/v2
```

#### Seeder

Aby załadować domyślne środowiska do bazy:

```bash
php artisan db:seed --class=Labapawel\\KsefApi\\Database\\Seeders\\KsefEnvironmentSeeder
```

Lub dodaj do `DatabaseSeeder`:

```php
$this->call(KsefEnvironmentSeeder::class);
```

#### Ręczne dodanie środowiska

```php
KsefEnvironment::create([
    'environment' => 'staging',
    'api_url' => 'https://api-staging.ksef.mf.gov.pl/v2',
    'description' => 'Środowisko staging',
    'is_active' => true,
]);
```

#### Powiązanie poświadczeń ze środowiskiem

Każde poświadczenie jest powiązane z jednym środowiskiem przez `ksef_environment_id`:

```php
use Labapawel\KsefApi\Models\Credential;
use Labapawel\KsefApi\Models\KsefEnvironment;

// Pobierz środowisko
$demo = KsefEnvironment::findByEnvironment('demo');

// Utwórz poświadczenia dla tego środowiska
Credential::create([
    'ksef_environment_id' => $demo->id,  // Foreign key do środowiska
    'nip' => '1234567890',
    'certificate_encrypted' => $certificatePem,
    'private_key_encrypted' => $privateKeyPem,
    'certificate_password_encrypted' => $certificatePassword,
]);

// Lub szybciej — korzystając ze scope
$cred = Credential::forEnvironmentAndNip('demo', '1234567890')->first();
echo $cred->environment->api_url;  // https://api-demo.ksef.mf.gov.pl/v2
```

## Autentykacja

### Logowanie do KSeF

Paczka automatycznie zarządza challenge tokenami i access tokenami.

#### Używając AuthenticationService (rekomendowane)

```php
use Labapawel\KsefApi\Services\AuthenticationService;

$auth = new AuthenticationService();

// Zaloguj się (pobiera certyfikat z bazy)
$response = $auth->login(
    nip: '1234567890',
    environment: 'demo' // opcjonalnie, domyślnie z konfigu
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

$authResponse = $client->authenticate($credentials, '1234567890', 'demo');

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

// Sprawdź ważność access tokena
if ($credential->isTokenValid()) {
    // Access token nie wygasł
}

if ($credential->isTokenExpired()) {
    // Access token wygasł, wymagane odświeżenie
}

// Sprawdź ważność challenge tokena
if ($credential->isChallengeTokenValid()) {
    // Challenge token jest jeszcze ważny
}

if ($credential->isChallengeTokenExpired()) {
    // Challenge token wygasł, wymagany nowy
}

// Lifecycle timestamps
$challengeReceivedAt = $credential->challenge_token_received_at;
$challengeExpiresAt = $credential->challenge_token_expires_at;
$accessExpiresAt = $credential->token_expires_at;
```

## Model danych

### Diagram relacji

```
ksef_environments (1)
      ↑
      │ (1:N)
      │ ksef_environment_id
      │
ksef_credentials (N)  ←→  ksef_invoices (N)
```

### `ksef_environments`

Tabela przechowuje konfiguracje środowisk (test, demo, prod):

- `environment`: Unikatowy identyfikator środowiska (`test`, `demo`, `prod`)
- `api_url`: URL endpointa API KSeF dla tego środowiska
- `description`: Opis środowiska (opcjonalnie)
- `is_active`: Status aktywności środowiska

**Relacja:** Jedno środowisko może mieć wiele poświadczeń (1:N)

### `ksef_credentials`

Tabela przechowuje zaszyfrowane dane wrażliwe KSeF dla pary `ksef_environment_id + nip`:

- identyfikatory: `ksef_environment_id` (foreign key do `ksef_environments`), `nip`
- legacy pola: `environment`, `api_url` (dla backward compatibility)
- zaszyfrowane: token KSeF (challenge), access token, refresh token
- zaszyfrowane: certyfikat, klucz prywatny, hasło do certyfikatu
- lifecycle: `challenge_token_received_at`, `challenge_token_expires_at`, `token_expires_at`
- uprawnienia: `scopes`, `permissions` (json)
- metadane: dodatkowe `meta` (json)

### `ksef_invoices`

Tabela przechowuje metadane biznesowe faktury oraz zaszyfrowany XML:

- jawne/wyszukiwalne: kierunek, numer i data faktury, NIP/nazwa sprzedawcy i nabywcy
- pola integracyjne: numer KSeF, numer referencyjny, status
- zaszyfrowany payload: pełny XML (`xml_encrypted`)

## Modele Eloquent

Paczka udostępnia modele Eloquent z potężnymi scopes i metodami pomocniczymi.

### Model `KsefEnvironment`

Przechowuje konfiguracje środowisk KSeF.

```php
use Labapawel\KsefApi\Models\KsefEnvironment;

// Pobierz środowisko
$demo = KsefEnvironment::findByEnvironment('demo');
$prod = KsefEnvironment::findActiveByEnvironment('prod');

// Dostępne scopes
KsefEnvironment::active()->get();                    // tylko aktywne
KsefEnvironment::byEnvironment('demo')->first();     // po ID

// Dostęp do danych
echo $demo->api_url;        // https://api-demo.ksef.mf.gov.pl/v2

// Relacja do poświadczeń
$credentials = $demo->credentials()->get();  // wszystkie kredencje dla demo
```

### Model `Credential`

Przechowuje poświadczenia KSeF dla pary `environment + nip`.

```php
use Labapawel\KsefApi\Models\Credential;

// Szukaj poświadczeń (nowe podejście — przez string environment)
$credential = Credential::forEnvironmentAndNip('demo', '1234567890')
    ->withCertificate()
    ->orderByDesc('updated_at')
    ->first();

// Lub przez ID środowiska (szybciej)
$envId = KsefEnvironment::findByEnvironment('demo')->id;
$credential = Credential::forEnvironmentIdAndNip($envId, '1234567890')
    ->withCertificate()
    ->orderByDesc('updated_at')
    ->first();

// Dostępne scopes
Credential::environment('demo')->get();                          // legacy
Credential::nip('1234567890')->get();
Credential::forEnvironmentAndNip('demo', '1234567890')->get();   // ze string environment
Credential::forEnvironmentIdAndNip($envId, '1234567890')->get(); // z foreign key (szybciej)
Credential::forEnvironmentId($envId)->get();                     // wszystkie kredencje dla środowiska
Credential::withCertificate()->get();                            // z kompletnym certyfikatem
Credential::validToken()->get();                                 // z ważnym access tokenem
Credential::validChallengeToken()->get();                        // z ważnym challenge tokenem

// Relacja do środowiska
echo $credential->environment->api_url;

// Sprawdzenie ważności access tokena
if ($credential->isTokenValid()) {
    // access token jest jeszcze ważny
}

if ($credential->isTokenExpired()) {
    // access token wygasł, wymagane odświeżenie
}

// Sprawdzenie ważności challenge tokena
if ($credential->isChallengeTokenValid()) {
    // challenge token jest jeszcze ważny
}

if ($credential->isChallengeTokenExpired()) {
    // challenge token wygasł, wymagany nowy
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

Modele `Credential` i `Invoice` automatycznie szyfrują wrażliwe dane za pomocą **Laravel Encryption** (AES-256-CBC) używając klucza `APP_KEY` z głównej konfiguracji aplikacji.

**Klucz szyfrowania:** `APP_KEY` z pliku `.env` Twojej aplikacji Laravel

**Algorytm:** AES-256-CBC z HMAC SHA-256 (Laravel Encryption)

**Model `Credential` szyfruje:**
- `ksef_token_encrypted` — Token wyzwania KSeF
- `access_token_encrypted` — JWT token dostępu
- `refresh_token_encrypted` — JWT token odświeżający
- `certificate_encrypted` — Certyfikat X.509
- `private_key_encrypted` — Klucz prywatny RSA
- `certificate_password_encrypted` — Hasło do certyfikatu

**Model `Invoice` szyfruje:**
- `xml_encrypted` — Pełna zawartość faktury w formacie XML
- `signature_encrypted` — Podpis XAdES faktury

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

⚠️ **Wymagania:**
- Aplikacja Laravel musi mieć wygenerowany `APP_KEY` (automatycznie przez `php artisan key:generate`)
- Nie zmieniaj `APP_KEY` po zapisaniu zaszyfrowanych danych (dane staną się niedostępne)
- Backup klucza `APP_KEY` w bezpiecznym miejscu (poza repozytorium)

## Uwagi bezpieczeństwa

### Klucz szyfrowania (`APP_KEY`)
- **Generowanie:** Użyj `php artisan key:generate` do wygenerowania silnego klucza
- **Przechowywanie:** Nigdy nie commituj `APP_KEY` do repozytorium git
- **Backup:** Przechowuj kopię zapasową klucza w bezpiecznym magazynie (Vault, AWS Secrets Manager, itp.)
- **Różne środowiska:** Używaj osobnych kluczy dla test/demo/prod
- **Rotacja:** Zmiana `APP_KEY` wymaga ponownego zaszyfrowania wszystkich danych w bazie

### Certyfikaty i klucze prywatne
- Przechowuj zaszyfrowane w bazie danych (automatyczne przez pakiet)
- Klucze prywatne nigdy nie opuszczają serwera
- Używaj silnych haseł do certyfikatów (min. 16 znaków)
- Osobne certyfikaty dla każdego środowiska KSeF

### Separacja środowisk
- TEST, DEMO, PROD - całkowicie oddzielne bazy danych
- Różne certyfikaty dla każdego środowiska
- Nigdy nie mieszaj tokenów między środowiskami
- Różne klucze `APP_KEY` dla każdego środowiska

### Dostęp do bazy danych
- Ogranicz dostęp do tabel `ksef_credentials` i `ksef_invoices`
- Monitoruj logi dostępu do zaszyfrowanych danych
- Regularnie audytuj uprawnienia użytkowników bazy danych

### Backup i recovery
- Backupuj dane w postaci zaszyfrowanej
- Zachowaj bezpieczne kopie klucza `APP_KEY` (offline, w sejfie/vault)
- Testuj procedury odzyskiwania danych
- Dokumentuj proces rotacji kluczy

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
$acceptedInvoice = DataFactory::createInvoice(['status' => 'accepted']);

// Utwórz fakturę dla konkretnego sprzedawcy
$sellerInvoice = DataFactory::createInvoice([
    'seller_nip' => '7986711699',
    'seller_name' => 'Acme Corp',
]);
```

## Szyfrowanie i Bezpieczeństwo

### Dane zaszyfrowane

Pakiet automatycznie szyfruje wszystkie wrażliwe dane za pomocą Laravel Encryption (AES-256-CBC):

**Model Credential:**
- `ksef_token_encrypted` — Challenge token z KSeF API
- `access_token_encrypted` — JWT access token
- `refresh_token_encrypted` — JWT refresh token
- `certificate_encrypted` — Certyfikat X.509
- `private_key_encrypted` — Klucz prywatny RSA
- `certificate_password_encrypted` — Hasło do certyfikatu

**Model Invoice:**
- `xml_encrypted` — Pełny XML faktury
- `signature_encrypted` — Podpis XAdES

### Klucz szyfrowania

- **Algorytm:** AES-256-CBC
- **Klucz:** `APP_KEY` z konfiguracji Laravel
- **Inicjalizacja:** `php artisan key:generate`

### Bezpieczeństwo producyjnego

⚠️ **Krytyczne:**

1. **Nigdy** nie commituj `APP_KEY` do repozytorium — używaj `.env`
2. **Zawsze** konfiguruj `APP_KEY` w `.env.production`
3. **Backup** klucza przed rotacją — zmiana klucza uczyni dane niezrozumiałymi
4. **HTTPS** — komunikacja z API KSeF zawsze po HTTPS
5. **Certifikaty** — przechowuj certyfikaty KSeF bezpiecznie poza repozytorium
6. **Database credentials** — chroni dostęp do bazy z poświadczeniami

### Przykładowe bezpieczne środowisko

```bash
# .env.production
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
KSEF_CHALLENGE_TOKEN_LIFETIME=10
KSEF_API_TIMEOUT=30

# Database
DB_CONNECTION=mysql
DB_HOST=secure-db-server.internal
DB_DATABASE=ksef_production
DB_USERNAME=ksef_user
DB_PASSWORD=secure_password_here
```

## Architektura

### Warstwa aplikacji

```
┌─────────────────────────────────────────────────────────┐
│ Warstwa aplikacji (Laravel Controller/Service)          │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ Services (High-level API)                               │
│ - AuthenticationService.php                             │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ Repositories (Business Logic)                           │
│ - CredentialRepository.php                              │
│ - InvoiceRepository.php                                 │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ Models (Eloquent ORM)                                    │
│ - KsefEnvironment.php / Credential.php / Invoice.php   │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ Clients (HTTP Communication)                            │
│ - KsefAuthClient.php (Authentication)                   │
│ - KsefInvoiceClient.php (Invoice Operations)            │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ KSeF REST API                                            │
│ https://api-demo.ksef.mf.gov.pl/v2                      │
└─────────────────────────────────────────────────────────┘
```

### Przepływ autentykacji

```
1. Pobranie poświadczeń z bazy (ksef_credentials)
   ↓
2. InitializeSession (wysłanie certyfikatu + NIP do API)
   ↓
3. Otrzymanie challenge_token (ważny 10 minut)
   ↓
4. Podpisanie challenge_token certyfikatem
   ↓
5. AuthorizeSession (wymiana na access/refresh tokeny)
   ↓
6. Zapis tokenów w bazie (ksef_credentials)
   ↓
7. Użycie access_token do żądań biznesowych
   ↓
8. Automatyczne odświeżenie przy wygaśnięciu
```

## Troubleshooting

### Problem: "Brak poświadczeń w bazie dla NIP XXX"

**Przyczyna:** Poświadczenia nie zostały zapisane w bazie danych.

**Rozwiązanie:**

```php
use Labapawel\KsefApi\Models\KsefEnvironment;
use Labapawel\KsefApi\Models\Credential;

// Pobierz środowisko
$env = KsefEnvironment::findByEnvironment('demo');

// Wczytaj certyfikat z pliku
$cert = file_get_contents('/path/to/cert.pem');
$key = file_get_contents('/path/to/key.pem');

// Utwórz nowe poświadczenia
Credential::create([
    'ksef_environment_id' => $env->id,
    'nip' => '1234567890',
    'certificate_encrypted' => $cert,
    'private_key_encrypted' => $key,
    'certificate_password_encrypted' => 'hasło_do_certyfikatu',
]);
```

### Problem: "Zmiana klucza APP_KEY uczyni dane niezrozumiałymi"

**Przyczyna:** Zmieniłeś `APP_KEY` v1 na v2 — istniejące dane były szyfrowane kluczem v1.

**Rozwiązanie:**

```bash
# Backup starego klucza (ZAWSZE!)
cp .env .env.backup

# Ponowne szyfrowanie danych nowym kluczem
php artisan ksef:migrate-encryption --old-key=base64:oldkey=

# Lub ręcznie:
# 1. Pobierz dane z stary kluczem
# 2. Zmień APP_KEY na nowy
# 3. Zapisz dane ponownie
```

### Problem: "cURL error 60: SSL certificate problem"

**Przyczyna:** Weryfikacja SSL w `KsefAuthClient` jest wyłączona (`'verify' => false`).

**Rozwiązanie (production):**

```php
// src/Clients/KsefAuthClient.php - zmodyfikuj constructor
$this->httpClient = new Client([
    'timeout' => $timeout,
    'verify' => true, // Włącz weryfikację
    'cert' => ['/path/to/ca-bundle.crt'], // Ścieżka do CA bundle
]);
```

### Problem: "UNIQUE constraint failed: ksef_credentials.ksef_environment_id, nip"

**Przyczyna:** Próbujesz stworzyć drugi rekord dla tej samej pary (środowisko + nip).

**Rozwiązanie:**

```php
// Zamiast create() — użyj firstOrCreate()
Credential::firstOrCreate(
    [
        'ksef_environment_id' => $env->id,
        'nip' => '1234567890',
    ],
    [
        'certificate_encrypted' => $cert,
        'private_key_encrypted' => $key,
        'certificate_password_encrypted' => $password,
    ]
);
```

## FAQ

### P: Czy mogę używać różne certyfikaty dla tego samego NIP w różnych środowiskach?

**O:** Tak! Jeśli masz:
- Certyfikat A → demo (nip + demo + cert_A)
- Certyfikat B → prod (nip + prod + cert_B)

System automatycznie wybierze poprawny rekord na podstawie środowiska.

### P: Czy access_token jest automatycznie odświeżany?

**O:** Nie w obecnej wersji. Musisz ręcznie wywoływać `$auth->login()` gdy token wygaśnie. Jeśli challenge token jest jeszcze ważny, logowanie jest szybkie.

### P: Co zrobić ze starymi poświadczeniami (legacy environment/api_url)?

**O:** Pola `environment` i `api_url` w `ksef_credentials` są opcjonalne dla backward compatibility. Nowe projekty powinny używać relacji:

```php
$cred = Credential::with('environment')->find($id);
echo $cred->environment->api_url; // Pobranie URL z KsefEnvironment
```

### P: Czy mogę migrować istniejące kredencje z kolumn na foreign key?

**O:** Tak, wykonaj artisan command (jeśli istnieje) lub ręcznie:

```php
Credential::all()->each(function($cred) {
    $env = KsefEnvironment::byEnvironment($cred->environment)->first();
    if($env) {
        $cred->update(['ksef_environment_id' => $env->id]);
    }
});
```

### P: Jaka jest maksymalna wielkość faktury (XML)?

**O:** Praktycznie bez limitu — kolumna `xml_encrypted` to `longText` (4GB w MySQL). Realistycznie: faktury XML są zwykle < 1MB.

### P: Czy mogę wyłączyć automatyczne szyfrowanie pól?

**O:** Nie z modelu Eloquent — szyfrowanie jest wbudowane. Jeśli chcesz przechowywać dane niezaszyfrowane, musisz zmienić migracje i usunąć `$encrypted` z modeli.

### P: Czy paczka obsługuje offline mode (offline KSeF)?

**O:** Nie w obecnej wersji. Offline mode wymaga lokalnego certyfikatu i jest obsługiwany przez dedykowany KSeF offline API.

## Licencja

MIT License — patrz plik [LICENSE](LICENSE)

## Wkład (Contributing)

Zapraszamy do współtworzenia! Zgłaszaj issues i pull requests na:
https://github.com/labapawel/ksef-api

## Kontakt i Wsparcie

- **Issues:** https://github.com/labapawel/ksef-api/issues
- **Email:** labapawel@gmail.com
- **GitHub:** https://github.com/labapawel

---

**Ostatnia aktualizacja:** 2026-03-04  
**Wersja:** 1.0.0  
**Status:** Stable Release
$invoice = DataFactory::createA