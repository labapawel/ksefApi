# Komponent KSeF API dla Laravel

## Opis komponentu

Pakiet Laravel do integracji z polskim systemem KSeF (Krajowy System e-Faktur). Zapewnia kompleksową obsługę:
- Autentykacji w systemie KSeF (challenge tokens + access tokens)
- Zarządzania poświadczeniami (certyfikaty, klucze, tokeny)
- Przechowywania faktur z automatycznym szyfrowaniem
- Wysyłania i pobierania faktur z KSeF API
- Weryfikacji statusu faktur
- opis dokumentu readme.md w języku polskim
- komentarze do dokumentacji w kodzie w języku polskim

**Namespace:** `Labapawel\KsefApi\`  
**Package:** `labapawel/ksef-api`  
**PHP:** ^8.2  
**Laravel:** ^10 | ^11 | ^12

---

## Architektura komponentu

### Struktura katalogów

```
src/
├── Clients/              # Klienty HTTP do komunikacji z API KSeF
│   ├── KsefAuthClient.php
│   └── KsefInvoiceClient.php
├── Contracts/            # Interfejsy i kontrakty
├── DTO/                  # Data Transfer Objects
│   ├── Credentials.php
│   └── AuthenticationResponse.php
├── Exceptions/           # Wyjątki specyficzne dla KSeF
│   ├── KsefApiException.php
│   └── KsefAuthenticationException.php
├── Models/               # Modele Eloquent
│   ├── KsefEnvironment.php # Środowiska (test, demo, prod)
│   ├── Credential.php    # Poświadczenia (NIP + środowisko)
│   └── Invoice.php       # Faktury
├── Repositories/         # Warstwa biznesowa nad modelami
│   ├── CredentialRepository.php
│   └── InvoiceRepository.php
├── Services/             # Serwisy wysokiego poziomu
│   └── AuthenticationService.php
├── Support/              # Narzędzia pomocnicze
│   ├── EncryptionService.php
│   └── XmlInvoiceParser.php
└── KsefServiceProvider.php
```

### Warstwy aplikacji

1. **Warstwa API** - `Clients/` - komunikacja HTTP z KSeF
2. **Warstwa biznesowa** - `Services/`, `Repositories/` - logika biznesowa
3. **Warstwa danych** - `Models/` - ORM Eloquent z automatycznym szyfrowaniem
4. **Warstwa DTO** - `DTO/` - transfer danych między warstwami
5. **Warstwa pomocnicza** - `Support/` - narzędzia (parsowanie XML, szyfrowanie)

---

## Baza danych

### Tabela `ksef_environments`

Przechowuje konfiguracje środowisk KSeF (test, demo, prod).

**Pola:**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | bigint | ID autoinkrementowane (PK) |
| `environment` | string(50) | Unikatowy identyfikator: `test`, `demo`, `prod` |
| `api_url` | string | URL endpointa API KSeF dla danego środowiska |
| `description` | string | Opcjonalny opis środowiska (np. "Demonstracyjne") |
| `is_active` | boolean | Czy środowisko jest aktywne (domyślnie: `true`) |
| `created_at` | timestamp | Data utworzenia |
| `updated_at` | timestamp | Data ostatniej modyfikacji |

**Indeksy:**
- UNIQUE: `environment` - unikalne identyfikatory środowisk
- INDEX: `is_active` - filtracja środowisk aktywnych

**Domyślne środowiska (załadowywane przez seeder):**

| environment | api_url |
|-------------|---------|
| `test` | `https://api-test.ksef.mf.gov.pl/v2` |
| `demo` | `https://api-demo.ksef.mf.gov.pl/v2` |
| `prod` | `https://ksef.mf.gov.pl/api/v2` |

---

### Tabela `ksef_credentials`

Przechowuje zaszyfrowane poświadczenia KSeF dla kombinacji `ksef_environment_id + nip`.

**Pola:**

| Kolumna | Typ | Szyfrowane | Opis |
|---------|-----|------------|------|
| `id` | bigint | ❌ | ID autoinkrementowane |
| `ksef_environment_id` | bigint (FK) | ❌ | Foreign Key do `ksef_environments` |
| `environment` | string(20) | ❌ | Legacy: Środowisko (dla backward compatibility) |
| `nip` | string(20) | ❌ | NIP podatnika |
| `api_url` | string | ❌ | Legacy: URL endpointa API (dla backward compatibility) |
| `ksef_token_encrypted` | longText | ✅ | Challenge token z API |
| `access_token_encrypted` | longText | ✅ | JWT access token |
| `refresh_token_encrypted` | longText | ✅ | JWT refresh token |
| `certificate_encrypted` | longText | ✅ | Certyfikat X.509 (PEM) |
| `private_key_encrypted` | longText | ✅ | Klucz prywatny RSA (PEM) |
| `certificate_password_encrypted` | longText | ✅ | Hasło do certyfikatu |
| `challenge_token_received_at` | timestamp | ❌ | Kiedy otrzymano challenge token |
| `challenge_token_expires_at` | timestamp | ❌ | Kiedy challenge token wygasa |
| `token_expires_at` | timestamp | ❌ | Kiedy access token wygasa |
| `scopes` | json | ❌ | Zakresy uprawnień (InvoiceWrite, InvoiceRead) |
| `permissions` | json | ❌ | Szczegółowe uprawnienia |
| `meta` | json | ❌ | Dodatkowe metadane |
| `created_at` | timestamp | ❌ | Data utworzenia |
| `updated_at` | timestamp | ❌ | Data ostatniej modyfikacji |

**Indeksy:**
- UNIQUE: `(ksef_environment_id, nip)` - jedna para poświadczeń na środowisko+NIP
- FOREIGN KEY: `ksef_environment_id` → `ksef_environments.id` (onDelete: RESTRICT)
- INDEX: `nip`

**Uwagi:**
- Wszystkie `*_encrypted` kolumny są automatycznie szyfrowane/deszyfrowane przez Laravel Encryption
- Klucz szyfrowania pochodzi z `APP_KEY` aplikacji Laravel
- Challenge token jest ważny przez 10 minut (konfigurowalny przez `KSEF_CHALLENGE_TOKEN_LIFETIME`)
- Legacy pola `environment` i `api_url` są opcjonalne - preferuj użycie relacji `environment()->api_url`
- Foreign key `ksef_environment_id` nie pozwala usuwać środowiska (RESTRICT) aby zapobiec sieroceniu poświadczeń

### Tabela `ksef_invoices`

Przechowuje metadane faktur (jawne, wyszukiwalne) oraz zaszyfrowany XML.

**Pola:**

| Kolumna | Typ | Szyfrowane | Opis |
|---------|-----|------------|------|
| `id` | bigint | ❌ | ID autoinkrementowane |
| `direction` | enum('sale','purchase') | ❌ | Kierunek: sprzedaż/kupno |
| `invoice_number` | string | ❌ | Numer faktury (wyszukiwalny) |
| `invoice_date` | date | ❌ | Data wystawienia faktury |
| `seller_nip` | string(20) | ❌ | NIP sprzedawcy |
| `seller_name` | string | ❌ | Nazwa sprzedawcy |
| `buyer_nip` | string(20) | ❌ | NIP nabywcy |
| `buyer_name` | string | ❌ | Nazwa nabywcy |
| `environment` | string(20) | ❌ | Środowisko KSeF (test/demo/prod) |
| `ksef_number` | string | ❌ | Numer nadany przez KSeF |
| `reference_number` | string | ❌ | Numer referencyjny KSeF |
| `session_id` | string | ❌ | ID sesji wysyłki wsadowej |
| `xml_encrypted` | longText | ✅ | Pełna zawartość faktury XML |
| `xml_hash` | string(64) | ❌ | SHA-256 hash XML (weryfikacja) |
| `signature_encrypted` | longText | ✅ | Podpis XAdES faktury |
| `status` | enum | ❌ | pending/processing/accepted/rejected |
| `is_signed` | boolean | ❌ | Czy faktura jest podpisana |
| `error_details` | json | ❌ | Szczegóły błędów z KSeF |
| `meta` | json | ❌ | Metadane (kwoty, ilość pozycji) |
| `submitted_at` | timestamp | ❌ | Kiedy wysłano do KSeF |
| `processed_at` | timestamp | ❌ | Kiedy KSeF przetworzył |
| `created_at` | timestamp | ❌ | Data utworzenia w bazie |
| `updated_at` | timestamp | ❌ | Data ostatniej modyfikacji |

**Indeksy:**
- UNIQUE: `ksef_number` - unikalne numery KSeF
- INDEX: `direction`
- INDEX: `invoice_date`
- INDEX: `seller_nip`
- INDEX: `buyer_nip`
- INDEX: `status`
- INDEX: `environment`

**Uwagi:**
- `xml_encrypted` zawiera kompletną fakturę XML - zaszyfrowaną automatycznie
- `signature_encrypted` przechowuje podpis XAdES (jeśli faktura podpisana)
- Metadane w `meta` mogą zawierać: kwoty brutto/netto/VAT, liczbę pozycji, walutę
- `error_details` przechowuje pełne informacje o błędach z API KSeF (kody, komunikaty)

---

## Szyfrowanie danych

### Zaszyfrowane pola

**Model Credential (6 pól):**
- `ksef_token_encrypted`
- `access_token_encrypted`
- `refresh_token_encrypted`
- `certificate_encrypted`
- `private_key_encrypted`
- `certificate_password_encrypted`

**Model Invoice (2 pola):**
- `xml_encrypted`
- `signature_encrypted`

### Mechanizm szyfrowania

1. **Algorytm:** AES-256-CBC (Laravel Encryption)
2. **Klucz:** `APP_KEY` z konfiguracji Laravel
3. **Automatyzacja:** Szyfrowanie/deszyfrowanie przy zapisie/odczycie przez Eloquent
4. **Weryfikacja:** Integrity check przez Laravel (HMAC)

### Przykład użycia

```php
// Zapis - automatyczne szyfrowanie
$credential = Credential::create([
    'access_token_encrypted' => 'eyJhbGciOiJIUzI1NiIs...', // zostanie zaszyfrowane
]);

// Odczyt - automatyczne deszyfrowanie
$token = $credential->access_token_encrypted; // zdekodowany token w formie jawnej
```

⚠️ **Bezpieczeństwo:**
- **Nigdy** nie przechowuj `APP_KEY` w repozytorium
- Używaj różnych kluczy dla różnych środowisk
- Rotacja kluczy wymaga ponownego zaszyfrowania wszystkich danych
- Backup bazy danych zachowuje dane w postaci zaszyfrowanej

---

## Autentykacja w KSeF

### Przepływ autentykacji

1. **InitializeSession** (faza 1)
   - Wysłanie certyfikatu + NIP do KSeF
   - Otrzymanie `challenge token` (timestamp, ważny 10 min)
   - Zapis challenge tokena w bazie z `challenge_token_received_at`

2. **AuthorizeSession** (faza 2)
   - Podpisanie challenge tokena kluczem prywatnym (XAdES)
   - Wysłanie podpisanego tokena do KSeF
   - Otrzymanie `access_token` (JWT, ważny 24h) + `refresh_token`

3. **Token Lifecycle**
   - Challenge token wygasa po `KSEF_CHALLENGE_TOKEN_LIFETIME` minutach (domyślnie 10)
   - Access token wygasa po 24 godzinach
   - Refresh token służy do przedłużenia sesji

### Challenge Token Management

Pakiet **automatycznie** zarządza challenge tokenami:

```php
// Przy logowaniu:
1. Sprawdź czy challenge token istnieje w bazie
2. Sprawdź czy nie wygasł (challenge_token_expires_at > now())
3. Jeśli ważny → użyj go do autoryzacji
4. Jeśli wygasły/brak → pobierz nowy z InitializeSession
```

### Konfiguracja lifetime

```env
KSEF_CHALLENGE_TOKEN_LIFETIME=10  # minuty
```

---

## Modele Eloquent

### Model `Credential`

**Scopes:**
- `environment(string $env)` - filtruj po środowisku
- `nip(string $nip)` - filtruj po NIP
- `apiUrl(string $url)` - filtruj po URL API
- `forEnvironmentAndNip(string $env, string $nip)` - filtruj po obu
- `validToken()` - tylko z ważnym access tokenem
- `validChallengeToken()` - tylko z ważnym challenge tokenem

**Metody:**
- `isTokenValid(): bool` - czy access token jest ważny
- `isTokenExpired(): bool` - czy access token wygasł
- `isChallengeTokenValid(): bool` - czy challenge token ważny
- `isChallengeTokenExpired(): bool` - czy challenge token wygasł

**Casts:**
- `challenge_token_received_at` → Carbon
- `challenge_token_expires_at` → Carbon
- `token_expires_at` → Carbon
- `scopes` → array
- `permissions` → array
- `meta` → array

### Model `Invoice`

**Stałe (Enums):**
- Directions: `DIRECTION_SALE`, `DIRECTION_PURCHASE`
- Statuses: `STATUS_PENDING`, `STATUS_PROCESSING`, `STATUS_ACCEPTED`, `STATUS_REJECTED`

**Scopes:**
- `direction(string $dir)` - filtruj po kierunku
- `sale()` - tylko sprzedaż
- `purchase()` - tylko kupno
- `status(string $status)` - filtruj po statusie
- `pending()` - oczekujące
- `processing()` - w trakcie
- `accepted()` - zaakceptowane
- `rejected()` - odrzucone
- `sellerNip(string $nip)` - po NIP sprzedawcy
- `buyerNip(string $nip)` - po NIP nabywcy
- `ksefNumber(string $number)` - po numerze KSeF
- `environment(string $env)` - po środowisku
- `signed()` - tylko podpisane
- `unsigned()` - tylko niepodpisane

**Metody:**
- `isAccepted(): bool`
- `isRejected(): bool`
- `isPending(): bool`
- `isProcessed(): bool`

**Casts:**
- `invoice_date` → date
- `submitted_at` → datetime
- `processed_at` → datetime
- `meta` → array
- `error_details` → array
- `is_signed` → boolean

---

## Serwisy i klienty

### `AuthenticationService` (wysokopoziomowy)

Fasada do zarządzania autentykacją.

**Metody:**
- `login(certificatePath, privateKeyPath, certificatePassword, nip, environment = 'demo'): AuthenticationResponse`
- `getCredentials(nip, environment = 'demo'): ?Credential`
- `hasValidCredentials(nip, environment = 'demo'): bool`
- `getAccessToken(nip, environment = 'demo'): ?string`
- `logout(nip, environment = 'demo'): bool`

### `KsefAuthClient` (niskopoziomowy)

Bezpośrednia komunikacja z KSeF API.

**Metody:**
- `authenticate(Credentials $credentials, string $nip): AuthenticationResponse`
- `initializeSession(Credentials $credentials): array`
- `authorizeSession(string $challengeToken, Credentials $credentials): array`
- `refreshToken(string $refreshToken): array`

### `KsefInvoiceClient` (planowany)

**Metody:**
- `sendInvoice(Invoice $invoice, Credential $credential): array`
- `getInvoiceStatus(string $ksefNumber, Credential $credential): array`
- `getInvoiceDetails(string $ksefNumber, Credential $credential): array`
- `downloadInvoice(string $ksefNumber, Credential $credential): string`
- `downloadInvoicePdf(string $ksefNumber, Credential $credential): string`

---

## Konfiguracja

### Plik `config/ksef.php`

```php
return [
    'environment' => env('KSEF_ENV', 'demo'),
    'api_url' => env('KSEF_URL', 'https://api-demo.ksef.mf.gov.pl/v2'),
    'challenge_token_lifetime' => env('KSEF_CHALLENGE_TOKEN_LIFETIME', 10), // minuty
    'api_timeout' => env('KSEF_API_TIMEOUT', 30), // sekundy
    'credentials_table' => env('KSEF_CREDENTIALS_TABLE', 'ksef_credentials'),
    'invoices_table' => env('KSEF_INVOICES_TABLE', 'ksef_invoices'),
];
```

### Zmienne środowiskowe (.env)

**Wymagane:**
```env
KSEF_ENV=demo                                      # test | demo | prod
KSEF_URL=https://api-demo.ksef.mf.gov.pl/v2      # URL API
```

**Opcjonalne:**
```env
KSEF_CHALLENGE_TOKEN_LIFETIME=10                  # minuty (domyślnie 10)
KSEF_API_TIMEOUT=30                               # sekundy (domyślnie 30)
KSEF_CREDENTIALS_TABLE=ksef_credentials           # nazwa tabeli
KSEF_INVOICES_TABLE=ksef_invoices                 # nazwa tabeli
```

**Środowiska KSeF:**
- **TEST:** `https://api-test.ksef.mf.gov.pl/v2`
- **DEMO:** `https://api-demo.ksef.mf.gov.pl/v2`
- **PROD:** `https://api.ksef.mf.gov.pl/v2`
- **PROD:** `https://api.ksef.mf.gov.pl/v2`

---

## Komendy Artisan

Pakiet udostępnia własne komendy konsolowe do zarządzania konfiguracją.

### `ksef:generate-key`

Generuje losowy, silny klucz szyfrowania APP_KEY (AES-256-CBC).

**Składnia:**
```bash
php artisan ksef:generate-key [--show] [--force]
```

**Opcje:**
- `--show` - Wyświetl wygenerowany klucz bez zapisywania do pliku .env
- `--force` - Wymuś nadpisanie istniejącego klucza (UWAGA: zaszyfrowane dane staną się nieosiągalne!)

**Przykłady:**

```bash
# Wygeneruj i zapisz klucz do .env
php artisan ksef:generate-key

# Tylko wyświetl wygenerowany klucz (bez modyfikacji .env)
php artisan ksef:generate-key --show

# Nadpisz istniejący klucz (użyj ostrożnie!)
php artisan ksef:generate-key --force
```

**Wyjście (przykład):**
```
Klucz szyfrowania został wygenerowany pomyślnie.

UWAGA: Zachowaj kopię zapasową tego klucza w bezpiecznym miejscu!
UWAGA: Zmiana klucza po zaszyfrowaniu danych uniemożliwi ich odczyt.
```

**Alternatywa Laravel:**
Możesz także użyć standardowej komendy Laravel:
```bash
php artisan key:generate
```

---

## Bezpieczeństwo

### Wytyczne

1. **Klucze szyfrujące:**
   - Używaj silnego `APP_KEY` (32+ znaków losowych)
   - Różne klucze dla test/demo/prod
   - Przechowuj klucze w bezpiecznym magazynie (Vault, AWS Secrets Manager)
   - **Nigdy** nie commituj kluczy do git

2. **Certyfikaty i klucze prywatne:**
   - Przechowuj zaszyfrowane w bazie (automatyczne)
   - Klucze prywatne nigdy nie opuszczają serwera
   - Używaj silnych haseł do certyfikatów

3. **Separacja środowisk:**
   - TEST, DEMO, PROD - całkowicie oddzielne bazy danych
   - Różne certyfikaty dla każdego środowiska
   - Nigdy nie mieszaj tokenów między środowiskami

4. **Dostęp do bazy:**
   - Ogranicz dostęp do tabel `ksef_*` tylko dla aplikacji
   - Monitoruj logi dostępu do zaszyfrowanych danych
   - Regularnie audytuj uprawnienia użytkowników DB

5. **Backup i recovery:**
   - Backupuj dane zaszyfrowane
   - Zachowaj kopie kluczy szyfrujących (offline, safe)
   - Testuj procedury odzyskiwania

### Rotacja kluczy

Przy zmianie `APP_KEY`:
1. Odczytaj wszystkie rekordy (Laravel deszyfuje starym kluczem)
2. Zmień `APP_KEY` w .env
3. Zapisz wszystkie rekordy (Laravel szyfruje nowym kluczem)

---

## Testowanie

### Struktura testów

```
tests/
├── TestCase.php                      # Bazowa klasa testowa
├── Fixtures/
│   └── DataFactory.php               # Fabryka danych testowych
└── Unit/
    ├── Models/
    │   ├── CredentialTest.php        # 30+ testów
    │   ├── InvoiceTest.php           # 53+ testów
    │   └── PackageInstallationTest.php
    └── Services/
        └── AuthenticationServiceTest.php  # 9 testów
```

### Uruchomienie testów

```bash
composer install --dev
./vendor/bin/phpunit
```

### DataFactory - metody pomocnicze

- `createCredential(array $overrides = []): Credential`
- `createCredentials(int $count = 3, string $env = 'demo'): Collection`
- `createExpiredCredential(array $overrides = []): Credential`
- `createExpiredChallengeToken(array $overrides = []): Credential`
- `createSoonToExpireCredential(array $overrides = []): Credential`
- `createInvoice(array $overrides = []): Invoice`
- `createInvoices(int $count = 5, string $direction = 'sale', string $status = 'pending'): Collection`
- `createAcceptedInvoice(array $overrides = []): Invoice`
- `createRejectedInvoice(array $overrides = []): Invoice`
- `createSignedInvoice(array $overrides = []): Invoice`

---

## Planowane funkcje

### W trakcie implementacji
- [ ] XAdES signing dla challenge tokenów
- [ ] Wysyłanie faktur do KSeF (`KsefInvoiceClient`)
- [ ] Pobieranie faktur z KSeF
- [ ] Pobieranie UPO (Urzędowe Potwierdzenie Odbioru)
- [ ] Wsadowe wysyłanie faktur

### Przyszłe rozszerzenia
- [ ] Walidacja XML względem schematów FA
- [ ] Parser XML do PHP DTO
- [ ] Generator XML z PHP obiektów
- [ ] Webhook callbacks dla statusów faktur
- [ ] Cache dla tokenów w Redis
- [ ] Queue jobs dla długich operacji

---

## Wsparcie i kontakt

**Repository:** [labapawel/ksef-api](https://github.com/labapawel/ksef-api)  
**Dokumentacja KSeF:** https://www.gov.pl/web/kas/ksef

---

## Licencja

MIT License - zobacz plik `LICENSE` w repozytorium.
