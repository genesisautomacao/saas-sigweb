<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PD-5 — exigência de aprovação de anexos configurável por etapa (do analista):
 * - nao_exige: aprova a etapa mesmo com anexos pendentes de análise;
 * - novos:     exige aprovar os anexos enviados DESDE A ÚLTIMA etapa de análise
 *              (ex.: comprovante de pagamento na volta ao Financeiro);
 * - todos:     exige o checklist completo do processo (default = comportamento atual).
 * Documento REPROVADO bloqueia a aprovação da etapa em qualquer modo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->string('aprovacao_anexos')->default('todos')->after('exige_requerimento');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->dropColumn('aprovacao_anexos');
        });
    }
};
