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

            // Unencrypted searchable metadata.
            $table->string('direction', 20)->index(); // sale|purchase
            $table->string('invoice_number', 128)->index();
            $table->date('invoice_date')->index();
            $table->string('seller_nip', 20)->nullable()->index();
            $table->string('seller_name')->nullable();
            $table->string('buyer_nip', 20)->nullable()->index();
            $table->string('buyer_name')->nullable();

            $table->string('ksef_number', 128)->nullable()->unique();
            $table->string('reference_number', 128)->nullable()->index();
            $table->string('status', 40)->default('pending')->index();

            // Full XML invoice content is encrypted in DB.
            $table->longText('xml_encrypted');
            $table->string('xml_hash', 128)->nullable()->index();

            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_invoices');
    }
};
