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
            
            // Identyfikatory: środowisko (test/demo/prod) + NIP podatnika
            $table->string('environment', 20)->index();
            $table->string('nip', 20)->index();
            $table->string('api_url')->nullable(); // URL endpointa API KSeF

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
            
            // Informacje o zakreśach i uprawnieniach
            $table->json('scopes')->nullable(); // Lista zakreśów (InvoiceWrite, InvoiceRead, itp.)
            $table->json('permissions')->nullable(); // Przydział uprawnień
            
            // Dodatkowe metadane
            $table->json('meta')->nullable(); // Dodatkowe informacje (wystawca, temat, itp.)
            $table->timestamps(); // created_at, updated_at

            // Unikalny warunek: jeden rekord poświadczeń na parę środowisko+nip
            $table->unique(['environment', 'nip']);
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_credentials');
    }
};
