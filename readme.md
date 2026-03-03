# KsefApi

Komponent Laravel do integracji z API KSeF.

Repozytorium/paczka: `labapawel/ksef-api`

## Zakres

Aktualny szkielet paczki zawiera:

- provider Laravel z publikacją konfiguracji i migracji
- plik konfiguracyjny paczki: `config/ksef.php`
- migracje dla tabel z poświadczeniami i fakturami
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

## Planowane API (szkielet gotowy)

- `Labap\\KsefApi\\Clients\\KsefAuthClient`
- `Labap\\KsefApi\\Clients\\KsefInvoiceClient`
- `Labap\\KsefApi\\Repositories\\CredentialRepository`
- `Labap\\KsefApi\\Repositories\\InvoiceRepository`
- `Labap\\KsefApi\\Support\\EncryptionService`
- `Labap\\KsefApi\\Support\\XmlInvoiceParser`

## Uwagi bezpieczeństwa

- Nie zapisuj tokenów/certyfikatów/kluczy prywatnych w jawnych kolumnach.
- Używaj dedykowanego klucza aplikacyjnego do szyfrowania danych komponentu.
- Rotuj klucze szyfrujące z kontrolowaną strategią migracji danych.
- Trzymaj środowiska KSeF (`test`, `demo`, `prod`) całkowicie rozdzielone.

## Development

Po sklonowaniu uruchom:

```bash
composer install
composer dump-autoload
```

## Licencja

MIT
