<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PD-1 (ajuste) — a etapa em que o requerimento é exigido passa a ser configurável:
 * o gate dispara ao concluir a etapa (do solicitante) marcada com `exige_requerimento`.
 * Sem nenhuma etapa marcada, vale a 1ª etapa do fluxo (comportamento original).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->boolean('exige_requerimento')->default(false)->after('campos_formulario');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->dropColumn('exige_requerimento');
        });
    }
};
