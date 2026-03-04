# Przykładowe pliki faktur KSeF

Ten katalog zawiera przykładowe faktury XML zgodne z formatami KSeF.

## 📄 Dostępne przykłady

### sample-invoice-fa3.xml
Podstawowa faktura VAT w formacie **FA(3)** v1-0E.

**Dane:**
- **Sprzedawca:** Testowa Firma Sp. z o.o. (NIP: 7986711699)
- **Nabywca:** Klient ABC Sp. z o.o. (NIP: 5471740555)
- **Data:** 2026-03-04
- **Numer:** FV/2026/03/001
- **Wartość netto:** 1000.00 PLN
- **VAT (23%):** 230.00 PLN
- **Wartość brutto:** 1230.00 PLN

**Pozycje:**
1. Konsultacja IT - analiza systemu (10 godz. × 100.00 PLN)

## 🔍 Formaty faktur KSeF

### FA(2) - Faktura uproszczona
Dla faktur do 100 EUR (ok. 450 PLN).  
**Schema:** `schemat_FA(2)_v1-0E.xsd`

### FA(3) - Faktura standardowa
Najczęściej używany format.  
**Schema:** `schemat_FA(3)_v1-0E.xsd`

### KOR(3) - Faktura korygująca
Korekta do faktury FA(3).  
**Schema:** `Schemat_PEF_KOR(3)_v2-1.xsd`

### RR(1) - Rozliczenie różnic
Faktury rozliczeniowe.  
**Schema:** `schemat_RR(1)_v1-0E.xsd`

## 📚 Użycie w aplikacji

### Laravel - tworzenie faktury

```php
use Labapawel\KsefApi\Models\Invoice;

$xmlContent = file_get_contents(
    base_path('vendor/labapawel/ksef-api/resources/examples/sample-invoice-fa3.xml')
);

$invoice = Invoice::create([
    'direction' => 'sale',
    'invoice_number' => 'FV/2026/03/001',
    'invoice_date' => '2026-03-04',
    'seller_nip' => '7986711699',
    'seller_name' => 'Testowa Firma Sp. z o.o.',
    'buyer_nip' => '5471740555',
    'buyer_name' => 'Klient ABC Sp. z o.o.',
    'environment' => 'test',
    'xml_encrypted' => $xmlContent, // automatic encryption
    'status' => 'pending',
    'meta' => [
        'gross_amount' => 1230.00,
        'net_amount' => 1000.00,
        'vat_amount' => 230.00,
        'currency' => 'PLN',
    ],
]);
```

### PHP - wczytanie faktury

```php
$xmlPath = __DIR__ . '/../../vendor/labapawel/ksef-api/resources/examples/sample-invoice-fa3.xml';
$xml = simplexml_load_file($xmlPath);

echo "Numer faktury: " . $xml->Fa->P_2; // FV/2026/03/001
echo "Wartość brutto: " . $xml->Fa->P_15 . " PLN"; // 1230.00 PLN
```

## 🧪 Walidacja XML względem XSD

```bash
# Wymaga xmllint (Linux/macOS)
xmllint --noout --schema ../schemas/FA/schemat_FA_v1-0E.xsd sample-invoice-fa3.xml

# Windows (PowerShell)
[xml]$invoice = Get-Content sample-invoice-fa3.xml
$invoice.Schemas.Add("http://crd.gov.pl/wzor/2025/06/25/13775/", "../schemas/FA/schemat_FA_v1-0E.xsd")
$invoice.Validate({Write-Host "Błąd walidacji: $_"})
```

## 🔗 Dokumentacja schematów

Pełna dokumentacja schematów XSD dostępna w:
- GitHub MF: [ministerstwo-finansow/ksef-docs](https://github.com/ministerstwo-finansow/ksef-docs)
- Lokalna kopia: `../schemas/`

## ⚠️ Uwagi

1. **Dane testowe** - wszystkie NIP-y i nazwy firm są fikcyjne
2. **Środowisko test** - te faktury są przeznaczone dla `KSEF_ENVIRONMENT=test`
3. **Nie wysyłaj na prod** - przed wysłaniem do produkcji zmień:
   - NIP-y na rzeczywiste
   - Nazwy firm
   - Numery faktur
   - Daty

## 📖 Przydatne linki

- [Dokumentacja formatów faktur (MF)](https://www.gov.pl/web/kas/struktury-dokumentow-elektronicznych)
- [Schematy XSD](../schemas/)
- [Dokumentacja API KSeF](https://github.com/ministerstwo-finansow/ksef-docs)

---

**Wersja:** 1.0  
**Data:** 2026-03-04
