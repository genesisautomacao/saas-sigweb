<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R83-7 (2026-10-13): flag Ativo nas Configurações de APIs do /admin. Linha inativa
 * vale como ausente para todos os consumidores (sem precisar apagar a credencial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_settings', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('api_settings', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
    }
};
