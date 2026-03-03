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
            $table->string('environment', 20)->index();
            $table->string('nip', 20)->index();

            // Sensitive KSeF data must be stored encrypted.
            $table->longText('ksef_token_encrypted')->nullable();
            $table->longText('access_token_encrypted')->nullable();
            $table->longText('refresh_token_encrypted')->nullable();
            $table->longText('certificate_encrypted')->nullable();
            $table->longText('private_key_encrypted')->nullable();
            $table->longText('certificate_password_encrypted')->nullable();

            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'nip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_credentials');
    }
};
