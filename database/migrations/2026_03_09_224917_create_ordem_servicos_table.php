<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            // Pode nascer de uma solicitação ou ser criada direto
            $table->foreignId('solicitacao_id')->nullable()->constrained('solicitacoes_manutencao')->nullOnDelete();
            
            // Replicamos o polimorfismo aqui para a OS saber onde é o serviço, mesmo sem solicitação
            $table->morphs('asset'); 

            $table->string('status')->default('aberta'); // aberta, andamento, pausada, concluida, cancelada
            $table->string('prioridade')->default('media');
            
            $table->date('data_prevista')->nullable();
            $table->dateTime('data_inicio')->nullable(); // Quando a equipe deu "Play"
            $table->dateTime('data_fim')->nullable(); // Quando deram "Concluir"
            
            $table->text('descricao_servico')->nullable(); // O que tem que ser feito
            $table->text('laudo_tecnico')->nullable(); // O que o técnico escreveu no final
            
            $table->string('foto_antes')->nullable();
            $table->string('foto_depois')->nullable(); // Prova do serviço feito

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX os_tenant_seq_unique ON ordens_servico (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('ordens_servico'); }
};