<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Tests\Unit\Models;

use Labapawel\KsefApi\Models\Invoice;
use Labapawel\KsefApi\Tests\TestCase;

class InvoiceTest extends TestCase
{
    /**
     * Test: Sprawdzenie czy model Invoice może być utworzony.
     */
    public function test_invoice_can_be_created(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'TEST/2026/03/03/001',
            'invoice_date' => '2026-03-03',
            'seller_nip' => '7986711699',
            'seller_name' => 'Moja Firma Sp. z o.o.',
            'buyer_nip' => '5471740555',
            'buyer_name' => 'Odbiorca Sp. z o.o.',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('ksef_invoices', [
            'invoice_number' => 'TEST/2026/03/03/001',
            'direction' => 'sale',
        ]);

        $this->assertIsInt($invoice->id);
    }

    /**
     * Test: Sprawdzenie czy invoice_date jest konwertowany na Carbon (date).
     */
    public function test_invoice_date_is_cast_to_date(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV001',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoice = $invoice->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->invoice_date);
        $this->assertEquals('2026-03-03', $invoice->invoice_date->format('Y-m-d'));
    }

    /**
     * Test: Sprawdzenie czy processed_at jest konwertowany na Carbon (datetime).
     */
    public function test_processed_at_is_cast_to_datetime(): void
    {
        $processedAt = now();

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV002',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'processed_at' => $processedAt,
        ]);

        $invoice = $invoice->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->processed_at);
    }

    /**
     * Test: Sprawdzenie czy meta jest konwertowane na JSON.
     */
    public function test_meta_is_cast_to_json(): void
    {
        $meta = [
            'gross_amount' => 1234.56,
            'tax_amount' => 286.66,
            'net_amount' => 947.90,
            'line_items_count' => 2,
        ];

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV003',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'meta' => $meta,
        ]);

        $invoice = $invoice->fresh();

        $this->assertIsArray($invoice->meta);
        $this->assertEquals($meta, $invoice->meta);
    }

    /**
     * Test: Scope "direction" dla sale.
     */
    public function test_scope_direction_sale(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'SALE001',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'purchase',
            'invoice_number' => 'PURCHASE001',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $sales = Invoice::direction('sale')->get();

        $this->assertCount(1, $sales);
        $this->assertEquals('sale', $sales->first()->direction);
    }

    /**
     * Test: Scope "sale".
     */
    public function test_scope_sale(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'SALE002',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'purchase',
            'invoice_number' => 'PURCHASE002',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $sales = Invoice::sale()->get();

        $this->assertCount(1, $sales);
        $this->assertEquals('sale', $sales->first()->direction);
    }

    /**
     * Test: Scope "purchase".
     */
    public function test_scope_purchase(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'SALE003',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'purchase',
            'invoice_number' => 'PURCHASE003',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $purchases = Invoice::purchase()->get();

        $this->assertCount(1, $purchases);
        $this->assertEquals('purchase', $purchases->first()->direction);
    }

    /**
     * Test: Scope "status".
     */
    public function test_scope_status(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV004',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'pending',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV005',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $pending = Invoice::status('pending')->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /**
     * Test: Scope "pending".
     */
    public function test_scope_pending(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV006',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'pending',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV007',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $pending = Invoice::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    /**
     * Test: Scope "processing".
     */
    public function test_scope_processing(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV008',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'processing',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV009',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $processing = Invoice::processing()->get();

        $this->assertCount(1, $processing);
        $this->assertEquals('processing', $processing->first()->status);
    }

    /**
     * Test: Scope "accepted".
     */
    public function test_scope_accepted(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV010',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV011',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'rejected',
        ]);

        $accepted = Invoice::accepted()->get();

        $this->assertCount(1, $accepted);
        $this->assertEquals('accepted', $accepted->first()->status);
    }

    /**
     * Test: Scope "rejected".
     */
    public function test_scope_rejected(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV012',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'rejected',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV013',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $rejected = Invoice::rejected()->get();

        $this->assertCount(1, $rejected);
        $this->assertEquals('rejected', $rejected->first()->status);
    }

    /**
     * Test: Scope "sellerNip".
     */
    public function test_scope_seller_nip(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV014',
            'invoice_date' => '2026-03-03',
            'seller_nip' => '7986711699',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV015',
            'invoice_date' => '2026-03-03',
            'seller_nip' => '5471740555',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoices = Invoice::sellerNip('7986711699')->get();

        $this->assertCount(1, $invoices);
        $this->assertEquals('7986711699', $invoices->first()->seller_nip);
    }

    /**
     * Test: Scope "buyerNip".
     */
    public function test_scope_buyer_nip(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV016',
            'invoice_date' => '2026-03-03',
            'buyer_nip' => '1234567890',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV017',
            'invoice_date' => '2026-03-03',
            'buyer_nip' => '9876543210',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoices = Invoice::buyerNip('1234567890')->get();

        $this->assertCount(1, $invoices);
        $this->assertEquals('1234567890', $invoices->first()->buyer_nip);
    }

    /**
     * Test: Scope "ksefNumber".
     */
    public function test_scope_ksef_number(): void
    {
        $ksefNumber = '20260303-EE-3FAFFEF000-BC85F1B486-58';

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV018',
            'invoice_date' => '2026-03-03',
            'ksef_number' => $ksefNumber,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV019',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoices = Invoice::ksefNumber($ksefNumber)->get();

        $this->assertCount(1, $invoices);
        $this->assertEquals($ksefNumber, $invoices->first()->ksef_number);
    }

    /**
     * Test: Kombinacja scopes — sale + accepted.
     */
    public function test_combined_scopes_sale_and_accepted(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV020',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV021',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'pending',
        ]);

        Invoice::create([
            'direction' => 'purchase',
            'invoice_number' => 'INV022',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $invoices = Invoice::sale()->accepted()->get();

        $this->assertCount(1, $invoices);
        $this->assertEquals('sale', $invoices->first()->direction);
        $this->assertEquals('accepted', $invoices->first()->status);
    }

    /**
     * Test: Metoda "isProcessed" — faktura przetworzenia.
     */
    public function test_is_processed_returns_true_when_processed_at_set(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV023',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'processed_at' => now(),
        ]);

        $this->assertTrue($invoice->isProcessed());
    }

    /**
     * Test: Metoda "isProcessed" — faktura nie przetworzenia.
     */
    public function test_is_processed_returns_false_when_processed_at_null(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV024',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'processed_at' => null,
        ]);

        $this->assertFalse($invoice->isProcessed());
    }

    /**
     * Test: Metoda "isPending".
     */
    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV025',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'pending',
        ]);

        $this->assertTrue($invoice->isPending());
    }

    /**
     * Test: Metoda "isAccepted".
     */
    public function test_is_accepted_returns_true_for_accepted_status(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV026',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'accepted',
        ]);

        $this->assertTrue($invoice->isAccepted());
    }

    /**
     * Test: Metoda "isRejected".
     */
    public function test_is_rejected_returns_true_for_rejected_status(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV027',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            'status' => 'rejected',
        ]);

        $this->assertTrue($invoice->isRejected());
    }

    /**
     * Test: Szyfrowanie pola xml_encrypted.
     */
    public function test_xml_encrypted_is_encrypted(): void
    {
        $plainXml = '<?xml version="1.0"?><invoice><total>100.00</total></invoice>';

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV028',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => $plainXml,
        ]);

        // Sprawdź że w bazie jest zaszyfrowany
        $encrypted = \DB::table('ksef_invoices')
            ->where('id', $invoice->id)
            ->first()
            ->xml_encrypted;

        $this->assertNotEquals($plainXml, $encrypted);

        // Sprawdź że model zwraca zdeszyfowany XML
        $invoice = $invoice->fresh();
        $this->assertEquals($plainXml, $invoice->xml_encrypted);
    }

    /**
     * Test: Unikalny numer KSeF.
     */
    public function test_ksef_number_is_unique(): void
    {
        $ksefNumber = '20260303-EE-UNIQUE-NUMBER';

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV029',
            'invoice_date' => '2026-03-03',
            'ksef_number' => $ksefNumber,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        // Próba utworzenia duplikatu powinna rzucić wyjątek
        $this->expectException(\Illuminate\Database\QueryException::class);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV030',
            'invoice_date' => '2026-03-03',
            'ksef_number' => $ksefNumber,
            'xml_encrypted' => '<invoice></invoice>',
        ]);
    }

    /**
     * Test: Wiele faktur z tym samym numerem faktury ale różnymi NIP.
     */
    public function test_multiple_invoices_with_same_number_different_sellers(): void
    {
        $invoiceNumber = 'TEST/2026/03/03/001';

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => $invoiceNumber,
            'invoice_date' => '2026-03-03',
            'seller_nip' => '1111111111',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => $invoiceNumber,
            'invoice_date' => '2026-03-03',
            'seller_nip' => '2222222222',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoices = Invoice::where('invoice_number', $invoiceNumber)->get();

        $this->assertCount(2, $invoices);
    }

    /**
     * Test: Domyślny status to 'pending'.
     */
    public function test_default_status_is_pending(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV031',
            'invoice_date' => '2026-03-03',
            'xml_encrypted' => '<invoice></invoice>',
            // Bez podawania 'status'
        ]);

        $this->assertEquals('pending', $invoice->status);
    }

    /**
     * Test: Pole environment jest przechowywane.
     */
    public function test_environment_field_is_stored(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV032',
            'invoice_date' => '2026-03-03',
            'environment' => 'demo',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $this->assertEquals('demo', $invoice->environment);
    }

    /**
     * Test: Pole session_id jest przechowywane i indeksowane.
     */
    public function test_session_id_field_is_stored(): void
    {
        $sessionId = 'SESSION-2026-03-03-001';

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV033',
            'invoice_date' => '2026-03-03',
            'session_id' => $sessionId,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $found = Invoice::where('session_id', $sessionId)->first();
        $this->assertNotNull($found);
    }

    /**
     * Test: Pole is_signed jest konwertowane do boolean.
     */
    public function test_is_signed_is_cast_to_boolean(): void
    {
        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV034',
            'invoice_date' => '2026-03-03',
            'is_signed' => true,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoice = $invoice->fresh();

        $this->assertIsBool($invoice->is_signed);
        $this->assertTrue($invoice->is_signed);
    }

    /**
     * Test: Szyfrowanie pola signature_encrypted.
     */
    public function test_signature_encrypted_is_encrypted(): void
    {
        $plainSignature = '<?xml version="1.0"?><xades>signature</xades>';

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV035',
            'invoice_date' => '2026-03-03',
            'signature_encrypted' => $plainSignature,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        // Sprawdź że w bazie jest zaszyfrowany
        $encrypted = \DB::table('ksef_invoices')
            ->where('id', $invoice->id)
            ->first()
            ->signature_encrypted;

        $this->assertNotEquals($plainSignature, $encrypted);

        // Sprawdź że model zwraca zdeszyfowany podpis
        $invoice = $invoice->fresh();
        $this->assertEquals($plainSignature, $invoice->signature_encrypted);
    }

    /**
     * Test: Pole error_details jest konwertowane do JSON.
     */
    public function test_error_details_is_cast_to_json(): void
    {
        $errorDetails = [
            'error_code' => 'INVALID_SIGNATURE',
            'error_message' => 'Podpis XAdES jest nieprawidłowy',
            'details' => ['field' => 'signature', 'reason' => 'nie zatwierdzony'],
        ];

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV036',
            'invoice_date' => '2026-03-03',
            'error_details' => $errorDetails,
            'status' => 'rejected',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoice = $invoice->fresh();

        $this->assertIsArray($invoice->error_details);
        $this->assertEquals($errorDetails, $invoice->error_details);
    }

    /**
     * Test: Pola submitted_at i processed_at są konwertowane do datetime.
     */
    public function test_submitted_at_and_processed_at_are_datetime(): void
    {
        $submittedAt = now();
        $processedAt = now()->addMinutes(5);

        $invoice = Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV037',
            'invoice_date' => '2026-03-03',
            'submitted_at' => $submittedAt,
            'processed_at' => $processedAt,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $invoice = $invoice->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->submitted_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->processed_at);
    }

    /**
     * Test: Filtrowanie faktur po environment.
     */
    public function test_filter_invoices_by_environment(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV038',
            'invoice_date' => '2026-03-03',
            'environment' => 'demo',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV039',
            'invoice_date' => '2026-03-03',
            'environment' => 'test',
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $demoInvoices = Invoice::where('environment', 'demo')->get();

        $this->assertCount(1, $demoInvoices);
        $this->assertEquals('demo', $demoInvoices->first()->environment);
    }

    /**
     * Test: Filtrowanie faktur po is_signed.
     */
    public function test_filter_invoices_by_is_signed(): void
    {
        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV040',
            'invoice_date' => '2026-03-03',
            'is_signed' => true,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        Invoice::create([
            'direction' => 'sale',
            'invoice_number' => 'INV041',
            'invoice_date' => '2026-03-03',
            'is_signed' => false,
            'xml_encrypted' => '<invoice></invoice>',
        ]);

        $signedInvoices = Invoice::where('is_signed', true)->get();

        $this->assertCount(1, $signedInvoices);
        $this->assertTrue($signedInvoices->first()->is_signed);
    }
}
