<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demografia por setor censitário (docs/piuma.txt, ajustes de 2026-09-04):
 * população, densidade (hab/hectare) e renda média vindas do arquivo
 * "Densidade Demográfica" (Censo 2022 tratado pela Líder), casadas por
 * código IBGE. Valem para qualquer zona, mas o levantamento só traz p/ setor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mob_zonas', function (Blueprint $table) {
            $table->unsignedInteger('populacao')->nullable();
            $table->decimal('densidade', 10, 2)->nullable(); // habitantes por hectare
            $table->decimal('renda', 12, 2)->nullable();     // renda média (R$)
        });
    }

    public function down(): void
    {
        Schema::table('mob_zonas', function (Blueprint $table) {
            $table->dropColumn(['populacao', 'densidade', 'renda']);
        });
    }
};
