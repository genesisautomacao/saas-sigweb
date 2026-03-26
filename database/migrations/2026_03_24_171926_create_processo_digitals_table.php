<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processos_digitais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            $table->string('codigo_processo')->unique(); // Ex: REURB-2026-0001
            
            $table->foreignId('requerente_id')->constrained('users')->cascadeOnDelete(); // O cidadão
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete(); // A mágica do mapa!
            $table->foreignId('bpmn_fluxo_id')->constrained('bpmn_fluxos')->restrictOnDelete();
            $table->foreignId('etapa_atual_id')->nullable()->constrained('bpmn_etapas')->nullOnDelete();
            
            $table->string('status')->default('rascunho'); // rascunho, em_andamento, concluido, cancelado
            $table->json('dados_formulario')->nullable(); // As respostas do cidadão

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX proc_digitais_active_unique ON processos_digitais (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('processos_digitais');
    }
};