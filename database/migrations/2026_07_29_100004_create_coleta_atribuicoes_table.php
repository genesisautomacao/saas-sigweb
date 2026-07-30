<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R67-4 — região de trabalho por cadastrador (quadras e/ou bairros) com período.
 * O pull do app passa a devolver SÓ os lotes das quadras atribuídas ao usuário
 * (sem atribuição vigente → nenhum lote; Master/Manager baixam tudo), evitando
 * carregar a base inteira e travar o app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coleta_atribuicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable(); // null = sem fim definido
            $table->json('quadra_ids')->nullable();
            $table->json('bairro_ids')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coleta_atribuicoes');
    }
};
