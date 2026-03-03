# Testy dla pakietu Labapawel\KsefApi

Katalog zawiera testy jednostkowe dla modeli `Credential` i `Invoice`.

## Struktura

```
tests/
├── TestCase.php                          # Bazowa klasa testowa
├── Unit/
│   └── Models/
│       ├── CredentialTest.php           # Testy modelu Credential (17 testów)
│       ├── InvoiceTest.php              # Testy modelu Invoice (25 testów)
│       └── PackageInstallationTest.php  # Testy instalacji paczki
└── Fixtures/
    └── DataFactory.php                  # Fabryka do tworzenia testowych danych
```

## Uruchamianie testów

```bash
# Wszystkie testy
./vendor/bin/phpunit

# Konkretny plik testu
./vendor/bin/phpunit tests/Unit/Models/CredentialTest.php

# Konkretna metoda testu
./vendor/bin/phpunit --filter="test_credential_can_be_created"
```

## Pokrycie kodu

```bash
./vendor/bin/phpunit --coverage-html coverage
```

Raporty będą dostępne w katalog `coverage/index.html`.

## Co jest testowane?

### Model Credential

- ✅ Tworzenie poświadczenia
- ✅ Casting atrybutów (datetime, json)
- ✅ Query scopes (environment, nip, forEnvironmentAndNip)
- ✅ Metody sprawdzające token (isTokenExpired, isTokenValid)
- ✅ Szyfrowanie pól wrażliwych
- ✅ Warunek unikalności (environment + nip)
- ✅ Wielokrotne poświadczenia dla tego samego NIP z różnymi środowiskami

### Model Invoice

- ✅ Tworzenie faktury
- ✅ Casting atrybutów (date, datetime, json)
- ✅ Query scopes (direction, status, sellerNip, buyerNip, ksefNumber, itp.)
- ✅ Metody sprawdzające status (isPending, isAccepted, isRejected, isProcessed)
- ✅ Szyfrowanie pola XML
- ✅ Unikalny numer KSeF
- ✅ Domyślny status (pending)
- ✅ Kombinowanie scopes

## Tworzenie nowych testów

Każdy nowy test powinien:

1. Rozszerzać klasę `TestCase` z katalogu `tests/`
2. Posiadać dokumentacyjny komentarz opisujący co testuje
3. Używać `DataFactory` do tworzenia testowych danych
4. Assertions powinny być czytelne i specyficzne

Przykład:

```php
<?php

namespace Labapawel\KsefApi\Tests\Unit\Models;

use Labapawel\KsefApi\Models\Invoice;
use Labapawel\KsefApi\Tests\Fixtures\DataFactory;
use Labapawel\KsefApi\Tests\TestCase;

class MyNewTest extends TestCase
{
    /**
     * Test: Opis czego testujemy.
     */
    public function test_something_works(): void
    {
        $invoice = DataFactory::createInvoice(['status' => 'accepted']);
        
        $this->assertTrue($invoice->isAccepted());
    }
}
```
