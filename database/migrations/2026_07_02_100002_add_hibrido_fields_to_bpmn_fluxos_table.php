<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — decisões #4 e #5 (processosConceito.md §9.4 / §9.5):
 * - perfis_autorizados: quem pode abrir/ver o fluxo (itens 121/207)
 * - exige_imovel: se o fluxo exige seleção de imóvel no mapa (130/221)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->json('perfis_autorizados')->nullable()->after('ativo');
            $table->boolean('exige_imovel')->default(true)->after('perfis_autorizados');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->dropColumn(['perfis_autorizados', 'exige_imovel']);
        });
    }
};
