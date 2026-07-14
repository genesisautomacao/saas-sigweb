<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — requerimento assinado (PD-1).
 * Fluxos com `exige_requerimento` seguram o processo em 'aguardando_solicitante' após a
 * abertura até o cidadão gerar o requerimento (PDF a partir do `template_requerimento`,
 * com placeholders), assinar e anexar o PDF assinado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->boolean('exige_requerimento')->default(false)->after('modo_imovel');
            $table->longText('template_requerimento')->nullable()->after('exige_requerimento');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->dropColumn(['exige_requerimento', 'template_requerimento']);
        });
    }
};
