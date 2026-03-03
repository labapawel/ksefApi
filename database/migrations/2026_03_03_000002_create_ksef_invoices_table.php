<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ksef_invoices', function (Blueprint $table): void {
            $table->id();

            // === METADANE BIZNESOWE (wyszukiwalne, niezaszyfrowane) ===
            // Kierunek: czy sprzedaliśmy czy kupiliśmy fakturę
            $table->string('direction', 20)->index(); // Enum: 'sale' | 'purchase'
            
            // Identyfikacja faktury
            $table->string('invoice_number', 128)->index(); // Numer faktury (np. TEST/2026/03/03/1234)
            $table->date('invoice_date')->index(); // Data faktury
            
            // Dane kontrahentów (wyszukiwalne)
            $table->string('seller_nip', 20)->nullable()->index(); // NIP sprzedawcy (do szybkiego wyszukania)
            $table->string('seller_name')->nullable(); // Nazwa firmy sprzedawcy
            $table->string('buyer_nip', 20)->nullable()->index(); // NIP nabywcy (do szybkiego wyszukania)
            $table->string('buyer_name')->nullable(); // Nazwa firmy nabywcy

            // === POLA INTEGRACYJNE Z KSeF ===
            $table->string('ksef_number', 128)->nullable()->unique(); // Identyfikator faktury przydzielony przez KSeF
            $table->string('reference_number', 128)->nullable()->index(); // Numer referencyjny sesji z KSeF
            
            // Status przetwarzania
            $table->string('status', 40)->default('pending')->index(); // Enum: pending | processing | accepted | rejected | itp.

            // === ZASZYFROWANA ZAWARTOŚĆ FAKTURY ===
            // Pełny XML faktury zaszyfrowany kluczem aplikacji
            $table->longText('xml_encrypted'); // Kompletny XML faktury (zaszyfrowany)
            $table->string('xml_hash', 128)->nullable()->index(); // Hash SHA-256 do sprawdzenia integralności

            // Metadane i cykl życia
            $table->json('meta')->nullable(); // Dane podatkowe, kwoty, podsumowanie pozycji
            $table->timestamp('processed_at')->nullable(); // Kiedy KSeF przetworzył tę fakturę
            $table->timestamps(); // created_at, updated_at
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_invoices');
    }
};
