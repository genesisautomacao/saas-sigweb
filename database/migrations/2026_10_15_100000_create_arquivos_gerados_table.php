<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R84-4 — arquivos pesados gerados pela fila (PDF detalhado de coletas, exportações).
 * O arquivo fica no bucket privado (ou no disco local da VPS, se o bucket recusar)
 * e some depois de `expira_em` (comando arquivos:limpar-expirados, diário).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivos_gerados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('painel', 20)->default('app');          // app | admin
            $table->string('tipo', 60);                             // coletas_pdf_detalhado | exportacao_dados ...
            $table->string('titulo');
            $table->json('parametros')->nullable();
            $table->string('status', 20)->default('na_fila');       // na_fila | processando | pronto | erro
            $table->unsignedTinyInteger('progresso')->nullable();   // 0..100
            $table->text('mensagem')->nullable();                   // resumo ou erro
            $table->string('disco', 20)->nullable();                // midia | local
            $table->string('caminho')->nullable();
            $table->string('nome_arquivo')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['painel', 'status']);
            $table->index('expira_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos_gerados');
    }
};
