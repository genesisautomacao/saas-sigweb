<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importação em massa de panorâmicas 360 (Bom Princípio — Líder):
 * metadados de captura vindos do GeoJSON de imageamento. O azimuth alimenta o
 * northOffset do Pannellum (bússola correta); trajectory identifica o lote/dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pontos_panoramicos', function (Blueprint $table) {
            $table->decimal('azimuth', 6, 2)->nullable()->after('data_captura');
            $table->decimal('altitude', 8, 3)->nullable()->after('azimuth');
            $table->string('trajectory', 50)->nullable()->after('altitude');

            // Dedup da importação em massa (idempotência por image_name/titulo)
            $table->index(['tenant_id', 'titulo'], 'pontos_panoramicos_tenant_titulo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pontos_panoramicos', function (Blueprint $table) {
            $table->dropIndex('pontos_panoramicos_tenant_titulo_idx');
            $table->dropColumn(['azimuth', 'altitude', 'trajectory']);
        });
    }
};
