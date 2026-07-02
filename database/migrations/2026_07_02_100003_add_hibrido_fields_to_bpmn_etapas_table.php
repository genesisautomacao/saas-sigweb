<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — fluxo híbrido (decisão #2, processosConceito.md §9.2):
 * cada etapa declara QUEM age e a QUAL setor pertence.
 * - executor: 'solicitante' | 'analista' (quem preenche/age nesta etapa)
 * - setor_id: FK → setores (swimlanes / item 206) — substitui o texto livre
 * - ordem: sequência padrão da etapa (roteamento real continua livre via tramitação)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->string('executor')->default('analista')->after('nome');
            $table->foreignId('setor_id')->nullable()->after('executor')->constrained('setores')->nullOnDelete();
            $table->integer('ordem')->default(0)->after('setor_id');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setor_id');
            $table->dropColumn(['executor', 'ordem']);
        });
    }
};
