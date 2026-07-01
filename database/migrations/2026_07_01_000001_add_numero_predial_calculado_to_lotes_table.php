<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Numeração predial gerada/salva pelo processo de numeração no mapa.
            // Guardada separada do cadastro atual (unidade_imobiliarias.numero_imovel)
            // para permitir o comparativo de divergências (itens PoC 108 e 109).
            $table->string('numero_predial_calculado')->nullable()->after('numero_lote');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('numero_predial_calculado');
        });
    }
};
