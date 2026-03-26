<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processo_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            
            $table->foreignId('processo_digital_id')->constrained('processos_digitais')->cascadeOnDelete();
            $table->foreignId('etapa_id')->nullable()->constrained('bpmn_etapas')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete(); // Quem subiu
            
            $table->string('nome_arquivo');
            $table->string('caminho_arquivo');
            $table->string('tipo_anexo')->default('original'); // original ou com_anotacoes (rabiscado)
            
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX proc_anexos_active_unique ON processo_anexos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('processo_anexos');
    }
};