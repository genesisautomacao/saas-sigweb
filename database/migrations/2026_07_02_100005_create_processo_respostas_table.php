<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — respostas por etapa (fluxo híbrido, processosConceito.md §9.2).
 * Cada envio de formulário (cidadão ou analista) grava UMA linha, sem sobrescrever.
 * Alimenta o histórico (item 216) e o "quem preencheu o quê".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processo_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('processo_digital_id')->constrained('processos_digitais')->cascadeOnDelete();
            $table->foreignId('bpmn_etapa_id')->nullable()->constrained('bpmn_etapas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processo_respostas');
    }
};
