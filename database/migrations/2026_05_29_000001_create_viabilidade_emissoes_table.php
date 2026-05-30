<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('viabilidade_emissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Protocolo legível impresso no PDF (ex: VIA-20260529-A3F1)
            $table->string('protocolo', 32)->unique();

            // Hash SHA-256 do snapshot — protege contra forjar resposta de validação
            $table->string('hash_seguranca', 64)->index();

            // Tipo: viabilidade (uso/funcionamento) | parcelamento | unificacao
            $table->string('tipo', 20)->index();

            // Resultado da análise — permitido / permissivel / proibido / aprovado / etc
            $table->string('status', 30)->nullable();

            // Referências do imóvel analisado
            $table->string('numero_lote')->nullable();
            $table->string('inscricao_imobiliaria')->nullable();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->foreignId('unidade_imobiliaria_id')->nullable()->constrained('unidade_imobiliarias')->nullOnDelete();

            // Snapshot completo da análise (JSON livre — schema do ViabilidadeService)
            $table->jsonb('dados_snapshot')->nullable();

            // Quem emitiu
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viabilidade_emissoes');
    }
};
