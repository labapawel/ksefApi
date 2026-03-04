<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ksef_credentials', function (Blueprint $table): void {
            $table->id();
            
            // Foreign Key do środowiska KSeF
            $table->foreignId('ksef_environment_id')
                ->constrained('ksef_environments')
                ->onDelete('restrict'); // Nie pozwalaj usuwać środowiska które jest w użyciu
            
            // Identyfikatory: środowisko (legacy string) + NIP podatnika
            $table->string('environment', 20)->index(); // Legacy pole dla backward compatibility
            $table->string('nip', 20)->index();
            $table->string('api_url')->nullable(); // Legacy pole dla backward compatibility

            // === ZASZYFROWANE DANE WRAŻLIWE ===
            // Wszystkie poświadczenia muszą być przechowywane zaszyfrowane za pomocą klucza Laravel
            $table->longText('ksef_token_encrypted')->nullable(); // Token wyzwania KSeF
            $table->longText('access_token_encrypted')->nullable(); // JWT token dostępu
            $table->longText('refresh_token_encrypted')->nullable(); // JWT token odświeżający
            $table->longText('certificate_encrypted')->nullable(); // Certyfikat X.509
            $table->longText('private_key_encrypted')->nullable(); // Klucz prywatny RSA
            $table->longText('certificate_password_encrypted')->nullable(); // Hasło do certyfikatu

            // Cykl życia tokenów
            $table->timestamp('challenge_token_received_at')->nullable(); // Kiedy otrzymaliśmy challenge token z API
            $table->timestamp('challenge_token_expires_at')->nullable(); // Kiedy challenge token wygasa
            $table->timestamp('token_expires_at')->nullable(); // Data wygaśnięcia access_token
            
            // === DANE FIRMY WYSTAWIAJĄCEJ FAKTURY ===
            $table->string('company_name')->nullable()->index(); // Nazwa firmy
            $table->string('company_nip', 20)->nullable()->index(); // NIP firmy (może być inny niż nip w credentials)
            $table->string('company_regon', 20)->nullable(); // REGON firmy
            $table->string('street')->nullable(); // Ulica
            $table->string('street_number')->nullable(); // Numer domu/budynku
            $table->string('apartment_number')->nullable(); // Numer mieszkania/lokalu
            $table->string('postal_code', 10)->nullable(); // Kod pocztowy
            $table->string('city')->nullable()->index(); // Miasto
            $table->string('email')->nullable(); // Email (poczta)
            $table->string('phone')->nullable(); // Numer telefonu
            $table->string('bank_account')->nullable(); // Numer konta bankowego (IBAN/NRB)
            
            // Informacje o zakreśach i uprawnieniach
            $table->json('scopes')->nullable(); // Lista zakreśów (InvoiceWrite, InvoiceRead, itp.)
            $table->json('permissions')->nullable(); // Przydział uprawnień
            
            // Dodatkowe metadane
            $table->json('meta')->nullable(); // Dodatkowe informacje (wystawca, temat, itp.)
            $table->timestamps(); // created_at, updated_at

            // Unikalny warunek: jeden rekord poświadczeń na parę (ksef_environment_id, nip)
            $table->unique(['ksef_environment_id', 'nip']);
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_credentials');
    }
};
