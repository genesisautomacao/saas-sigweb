<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitacoes_manutencao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            // 🛑 A MÁGICA DO POLIMORFISMO: Vai guardar "App\Models\Poste" e o ID "15"
            $table->morphs('asset'); 

            $table->string('tipo_servico'); // Ex: Lâmpada Apagada, Poda de Limpeza
            $table->string('prioridade')->default('media'); // baixa, media, alta, critica
            $table->string('status')->default('pendente'); // pendente, analise, aprovada_os, rejeitada
            
            $table->string('solicitante_nome')->nullable(); // Caso um munícipe tenha ligado reclamando
            $table->text('observacao')->nullable();
            
            $table->string('foto_ocorrencia')->nullable(); // Foto do problema relatado

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX sol_manut_tenant_seq_unique ON solicitacoes_manutencao (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('solicitacoes_manutencao'); }
};