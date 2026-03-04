# Dokumentacja techniczna KSeF API

## 📖 Spis treści

1. [Architektura systemu](#architektura-systemu)
2. [Modele danych](#modele-danych)
3. [Proces autentykacji](#proces-autentykacji)
4. [Szyfrowanie danych](#szyfrowanie-danych)
5. [Przykłady użycia](#przykłady-użycia)
6. [API Reference](#api-reference)

---

## Architektura systemu

### Warstwy aplikacji

```
┌─────────────────────────────────────────┐
│         Controller / Artisan CLI        │  ← Warstwa prezentacji
├─────────────────────────────────────────┤
│            Services Layer               │  ← Logika biznesowa
│  - AuthenticationService                │
│  - InvoiceService (planowane)           │
├─────────────────────────────────────────┤
│          Repository Layer (TODO)        │  ← Abstrakcja dostępu do danych
├─────────────────────────────────────────┤
│          Eloquent Models                │  ← Modele ORM
│  - KsefEnvironment                      │
│  - Credential                           │
│  - Invoice                              │
├─────────────────────────────────────────┤
│            HTTP Clients                 │  ← Komunikacja z API
│  - KsefAuthClient                       │
│  - KsefInvoiceClient (TODO)             │
├─────────────────────────────────────────┤
│            KSeF API 2.0                 │  ← Zewnętrzne API
│  https://ksef.mf.gov.pl                 │
└─────────────────────────────────────────┘
```

### Przepływ danych - autentykacja

```
Aplikacja
    │
    ├─> AuthenticationService::login(nip, environment)
    │       │
    │       ├─> Credential::forEnvironmentAndNip() (ORM)
    │       │       │
    │       │       └─> Database: SELECT * FROM ksef_credentials
    │       │
    │       ├─> KsefAuthClient::authenticate(credentials)
    │       │       │
    │       │       ├─> POST /online/Session/InitializeToken
    │       │       │       Response: { challenge: "abc123..." }
    │       │       │
    │       │       ├─> Sign challenge with private key (TODO: XAdES)
    │       │       │
    │       │       └─> POST /online/Session/Token
    │       │               Response: { token: "eyJhbG...", refreshToken: "..." }
    │       │
    │       └─> Credential::update([access_token => ...])
    │               │
    │               └─> Database: UPDATE ksef_credentials (encrypted)
    │
    └─< AuthenticationResponse
```

---

## Modele danych

### Relacje między tabelami

```sql
┌──────────────────────────┐
│  ksef_environments       │
│  ────────────────────    │
│  id (PK)                 │
│  environment (UNIQUE)    │◄─────┐
│  api_url                 │      │
│  description             │      │
│  is_active               │      │
└──────────────────────────┘      │
                                  │
                           FK: ksef_environment_id
                                  │
┌──────────────────────────┐      │
│  ksef_credentials        │      │
│  ────────────────────    │      │
│  id (PK)                 │      │
│  ksef_environment_id ────┼──────┘
│  nip                     │
│  *_encrypted (6 pól)     │  🔒 Szyfrowane AES-256-CBC
│  company_* (11 pól)      │  📝 Dane firmy (plain text)
│  token_expires_at        │
│  scopes (JSON)           │
│  UNIQUE(env_id, nip)     │
└──────────────────────────┘
                                  
┌──────────────────────────┐
│  ksef_invoices           │
│  ────────────────────    │
│  id (PK)                 │
│  direction (sale/purch)  │
│  invoice_number          │
│  seller_nip              │
│  buyer_nip               │
│  ksef_number (UNIQUE)    │
│  xml_encrypted           │  🔒 Szyfrowane
│  signature_encrypted     │  🔒 Szyfrowane
│  status (enum)           │
│  meta (JSON)             │
└──────────────────────────┘
```

### Cykl życia tokenu

```
Challenge Token (10 min)
    │
    ├─ challenge_token_received_at: timestamp
    ├─ challenge_token_expires_at: timestamp + 10 min
    │
    └─> Użyty do autoryzacji → Access Token (24h)
            │
            ├─ access_token_encrypted: JWT token
            ├─ token_expires_at: timestamp + 24h
            ├─ refresh_token_encrypted: JWT refresh token
            │
            └─> Po wygaśnięciu → Refresh → nowy Access Token
                    │
                    └─> Refresh wygasł → Nowe logowanie (challenge token)
```

---

## Proces autentykacji

### Faza 1: Initialize Session

**Endpoint:** `POST /online/Session/InitializeToken`

**Request:**
```json
{
  "contextIdentifier": {
    "type": "onip",
    "identifier": "7986711699"
  }
}
```

**Response:**
```json
{
  "timestamp": "2026-03-04T10:30:00Z",
  "referenceNumber": "20260304-1030-ABC123-DEF456",
  "challenge": "abc123def456ghi789..."
}
```

**Zadania pakietu:**
1. Sprawdź czy istnieje ważny challenge token w bazie
2. Jeśli nie - wyślij request do `/InitializeToken`
3. Zapisz challenge token z timestampem wygaśnięcia (`+10 min`)

### Faza 2: Authorize Session

**Endpoint:** `POST /online/Session/Token`

**Request:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<ns3:InitSessionTokenRequest xmlns:ns3="http://ksef.mf.gov.pl/schema/gtw/svc/online/auth/request/2021/10/01/0001">
    <ns3:ContextIdentifier>
        <ns2:Identifier xmlns:ns2="http://ksef.mf.gov.pl/schema/gtw/svc/types/2021/10/01/0001">7986711699</ns2:Identifier>
    </ns3:ContextIdentifier>
    <ns3:Token>
        <ns2:SignedChallenge xmlns:ns2="http://ksef.mf.gov.pl/schema/gtw/svc/types/2021/10/01/0001">
            <!-- XAdES signed challenge token -->
        </ns2:SignedChallenge>
    </ns3:Token>
</ns3:InitSessionTokenRequest>
```

**Response:**
```json
{
  "sessionToken": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expiry": "2026-03-05T10:30:00Z"
  },
  "refreshToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Zadania pakietu:**
1. Pobierz challenge token z bazy
2. Podpisz challenge kluczem prywatnym (XAdES) ⚠️ TODO
3. Wyślij request z podpisanym tokenem
4. Zapisz `access_token`, `refresh_token`, `token_expires_at` do bazy (zaszyfrowane)

---

## Szyfrowanie danych

### Algorytm: AES-256-CBC

**Klucz:** `APP_KEY` z Laravel (32 bajty dla AES-256)

**Implementacja:**
```php
// Laravel automatycznie szyfruje/deszyfruje przez casts
protected $casts = [
    'access_token_encrypted' => 'encrypted',
    'certificate_encrypted' => 'encrypted',
    // ...
];
```

**Pod maską (Laravel Encryption):**
```php
use Illuminate\Support\Facades\Crypt;

// Szyfrowanie
$encrypted = Crypt::encryptString($plainText);
// Format: base64(iv + encrypted_data + mac)

// Deszyfrowanie
$decrypted = Crypt::decryptString($encrypted);
```

### HMAC Integrity Check

Każde zaszyfrowane pole ma automatyczny HMAC (SHA-256):
```
encrypted_value = base64(
    iv (16 bytes) + 
    aes256_cbc_encrypt(data) + 
    hmac_sha256(iv + encrypted_data)
)
```

**Weryfikacja przy odczycie:**
- Jeśli HMAC się nie zgadza → Exception `DecryptException`
- Chroni przed modyfikacją danych w bazie

### Rotacja kluczy

⚠️ **Uwaga:** Zmiana `APP_KEY` wymaga ponownego zaszyfrowania wszystkich danych!

**Bezpieczna procedura:**
```bash
# 1. Backup bazy
mysqldump -u root -p laravel > backup.sql

# 2. Eksport danych (nowe polecenie artisan - TODO)
php artisan ksef:export --decrypt > credentials.json

# 3. Zmiana klucza
php artisan key:generate --force

# 4. Import z nowym szyfrowaniem
php artisan ksef:import --encrypt < credentials.json
```

---

## Przykłady użycia

### 1. Logowanie z automatycznym odświeżaniem tokenu

```php
use Labapawel\KsefApi\Services\AuthenticationService;
use Labapawel\KsefApi\Exceptions\KsefAuthenticationException;

$authService = app(AuthenticationService::class);

try {
    // Pakiet automatycznie:
    // - sprawdzi czy challenge token jest ważny
    // - jeśli nie, pobierze nowy
    // - autoryzuje i zapisze access token
    
    $response = $authService->login('7986711699', 'test');
    
    $accessToken = $response->sessionToken->token;
    $expiresAt = $response->sessionToken->expiry; // Carbon instance
    
    echo "Zalogowano! Token wygasa: {$expiresAt->diffForHumans()}";
    
} catch (KsefAuthenticationException $e) {
    Log::error('KSeF auth failed', [
        'nip' => '7986711699',
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
}
```

### 2. Sprawdzanie statusu poświadczeń

```php
use Labapawel\KsefApi\Models\Credential;

$credential = Credential::forEnvironmentAndNip('test', '7986711699')->first();

if ($credential->isTokenValid()) {
    echo "Access token jest ważny do: {$credential->token_expires_at}";
} else {
    echo "Token wygasł - wymagane ponowne logowanie";
    $authService->login('7986711699', 'test');
}

if ($credential->isChallengeTokenValid()) {
    echo "Challenge token jest ważny (można użyć do ponownej autoryzacji)";
}
```

### 3. Wysyłanie faktury (przykład - TODO: pełna implementacja)

```php
use Labapawel\KsefApi\Models\Invoice;
use Labapawel\KsefApi\Models\Credential;

// Załaduj XML faktury
$xmlContent = file_get_contents(storage_path('invoices/FA_2026_03_001.xml'));

// Utwórz rekord faktury (XML zostanie automatycznie zaszyfrowany)
$invoice = Invoice::create([
    'direction' => 'sale',
    'invoice_number' => 'FV/2026/03/001',
    'invoice_date' => '2026-03-04',
    'seller_nip' => '7986711699',
    'seller_name' => 'Moja Firma Sp. z o.o.',
    'buyer_nip' => '5471740555',
    'buyer_name' => 'Klient ABC Sp. z o.o.',
    'environment' => 'test',
    'xml_encrypted' => $xmlContent, // ← automatic encryption
    'status' => 'pending',
    'meta' => [
        'gross_amount' => 123.00,
        'net_amount' => 100.00,
        'vat_amount' => 23.00,
        'currency' => 'PLN',
    ],
]);

// TODO: Wysłanie faktury przez KsefInvoiceClient
// $invoiceClient->sendInvoice($invoice);
```

### 4. Raportowanie faktur

```php
use Labapawel\KsefApi\Models\Invoice;
use Illuminate\Support\Facades\DB;

// Statystyki miesięczne
$monthlyStats = Invoice::sale()
    ->whereBetween('invoice_date', ['2026-03-01', '2026-03-31'])
    ->selectRaw('
        COUNT(*) as total_invoices,
        SUM(JSON_EXTRACT(meta, "$.gross_amount")) as total_gross,
        SUM(JSON_EXTRACT(meta, "$.vat_amount")) as total_vat
    ')
    ->first();

echo "Faktury w marcu: {$monthlyStats->total_invoices}";
echo "Suma brutto: {$monthlyStats->total_gross} PLN";
echo "VAT: {$monthlyStats->total_vat} PLN";

// Top 5 klientów
$topClients = Invoice::sale()
    ->selectRaw('
        buyer_nip,
        buyer_name,
        COUNT(*) as invoice_count,
        SUM(JSON_EXTRACT(meta, "$.gross_amount")) as total_amount
    ')
    ->groupBy('buyer_nip', 'buyer_name')
    ->orderByDesc('total_amount')
    ->limit(5)
    ->get();

foreach ($topClients as $client) {
    echo "{$client->buyer_name} ({$client->buyer_nip}): {$client->total_amount} PLN\n";
}
```

### 5. Obsługa błędów KSeF

```php
use Labapawel\KsefApi\Models\Invoice;

$rejectedInvoices = Invoice::rejected()->get();

foreach ($rejectedInvoices as $invoice) {
    $errors = $invoice->error_details; // JSON array
    
    echo "Faktura {$invoice->invoice_number} odrzucona:\n";
    
    foreach ($errors as $error) {
        echo "  - Kod: {$error['code']}\n";
        echo "    Opis: {$error['message']}\n";
        
        // Możliwe kody błędów KSeF:
        // - INVALID_SIGNATURE - nieprawidłowy podpis XAdES
        // - INVALID_SCHEMA - XML nie zgadza się ze schematem XSD
        // - DUPLICATE_INVOICE - faktura o tym numerze już istnieje
        // - INVALID_NIP - nieprawidłowy NIP sprzedawcy/nabywcy
    }
}
```

---

## API Reference

### Model: `Credential`

#### Properties (zaszyfrowane)
- `ksef_token_encrypted: ?string` - Challenge token
- `access_token_encrypted: ?string` - JWT access token
- `refresh_token_encrypted: ?string` - JWT refresh token
- `certificate_encrypted: ?string` - Certyfikat X.509 (PEM)
- `private_key_encrypted: ?string` - Klucz prywatny RSA (PEM)
- `certificate_password_encrypted: ?string` - Hasło do certyfikatu

#### Properties (plain text)
- `company_name: ?string` - Nazwa firmy
- `company_nip: ?string` - NIP firmy (wyszukiwalny)
- `company_regon: ?string` - REGON firmy
- `street: ?string` - Ulica
- `street_number: ?string` - Numer domu
- `apartment_number: ?string` - Numer mieszkania
- `postal_code: ?string` - Kod pocztowy
- `city: ?string` - Miasto (wyszukiwalne)
- `email: ?string` - Email
- `phone: ?string` - Telefon
- `bank_account: ?string` - IBAN/NRB

#### Methods
```php
isTokenValid(): bool
isTokenExpired(): bool
isChallengeTokenValid(): bool
isChallengeTokenExpired(): bool
```

#### Scopes
```php
Credential::forEnvironmentAndNip(string $env, string $nip)
Credential::forEnvironmentIdAndNip(int $envId, string $nip)
Credential::validToken()
Credential::validChallengeToken()
Credential::withCertificate()
```

---

### Model: `Invoice`

#### Constants
```php
const DIRECTION_SALE = 'sale';
const DIRECTION_PURCHASE = 'purchase';

const STATUS_PENDING = 'pending';
const STATUS_PROCESSING = 'processing';
const STATUS_ACCEPTED = 'accepted';
const STATUS_REJECTED = 'rejected';
```

#### Scopes
```php
Invoice::sale()
Invoice::purchase()
Invoice::pending()
Invoice::processing()
Invoice::accepted()
Invoice::rejected()
Invoice::sellerNip(string $nip)
Invoice::buyerNip(string $nip)
Invoice::ksefNumber(string $number)
Invoice::environment(string $env)
Invoice::signed() / ::unsigned()
```

#### Methods
```php
isAccepted(): bool
isRejected(): bool
isPending(): bool
isProcessing(): bool
isSigned(): bool
```

---

### Service: `AuthenticationService`

```php
class AuthenticationService
{
    /**
     * Zaloguj do KSeF i uzyskaj access token.
     *
     * @throws KsefAuthenticationException
     */
    public function login(
        string $nip, 
        ?string $environment = null
    ): AuthenticationResponse;
    
    /**
     * Wyloguj z KSeF (anuluj sesję).
     */
    public function logout(string $sessionToken): void;
    
    /**
     * Sprawdź czy poświadczenia są ważne.
     */
    public function hasValidCredentials(
        string $nip, 
        ?string $environment = null
    ): bool;
    
    /**
     * Pobierz access token (jeśli ważny).
     */
    public function getAccessToken(
        string $nip, 
        ?string $environment = null
    ): ?string;
}
```

---

**Dokument:** Dokumentacja techniczna v1.0  
**Autor:** labapawel  
**Data:** 2026-03-04
