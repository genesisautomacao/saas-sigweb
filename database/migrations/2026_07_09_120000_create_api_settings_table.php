<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações globais de APIs de terceiros (super usuário / painel Admin).
 *
 * Guarda credenciais globais (não escopadas por tenant) num par name → data (JSON KeyValue).
 * Ex.: name = "Resend", data = { RESEND_API_KEY, MAIL_FROM_ADDRESS, MAIL_FROM_NAME }.
 * O AppServiceProvider injeta essa config dinamicamente no boot() para todas as tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_settings');
    }
};
