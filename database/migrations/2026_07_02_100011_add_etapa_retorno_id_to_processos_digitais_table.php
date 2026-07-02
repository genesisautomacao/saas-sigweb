<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — marcador de retorno da reprova.
 * Quando um analista reprova e devolve o processo para uma etapa específica (cidadão ou setor
 * anterior), grava aqui a etapa de QUEM REPROVOU. Assim, ao resolver a pendência, o processo
 * volta direto para essa etapa (não repassa linearmente pelas etapas intermediárias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processos_digitais', function (Blueprint $table) {
            $table->foreignId('etapa_retorno_id')->nullable()->after('etapa_atual_id')->constrained('bpmn_etapas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('processos_digitais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('etapa_retorno_id');
        });
    }
};
