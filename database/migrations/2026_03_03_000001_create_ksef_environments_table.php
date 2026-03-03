<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ksef_environments', function (Blueprint $table): void {
            $table->id();
            
            // Identyfikator środowiska: test, demo, prod
            $table->string('environment', 50)->unique()->index();
            
            // URL endpointa API KSeF dla danego środowiska
            $table->string('api_url');
            
            // Opis środowiska
            $table->string('description')->nullable();
            
            // Status: aktywne czy archiwalne
            $table->boolean('is_active')->default(true)->index();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_environments');
    }
};
