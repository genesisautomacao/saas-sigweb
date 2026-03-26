<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processo_tramitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            
            $table->foreignId('processo_digital_id')->constrained('processos_digitais')->cascadeOnDelete();
            $table->foreignId('etapa_origem_id')->nullable()->constrained('bpmn_etapas')->nullOnDelete();
            $table->foreignId('etapa_destino_id')->nullable()->constrained('bpmn_etapas')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete(); // Quem moveu o processo
            
            $table->text('parecer')->nullable(); // O texto do analista
            $table->string('status_parecer')->nullable(); // aprovado, reprovado, encaminhado

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX proc_tramit_active_unique ON processo_tramitacoes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('processo_tramitacoes');
    }
};