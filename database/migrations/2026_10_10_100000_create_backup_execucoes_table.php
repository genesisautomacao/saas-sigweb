<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INF-3 — registro de cada execução do backup diário dos bancos de produção.
 * Global (sem tenant): quem grava é o script do servidor via `backup:registrar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_execucoes', function (Blueprint $table) {
            $table->id();
            $table->string('sistema', 30);          // webgis | ferramenta
            $table->string('status', 20);           // sucesso | falha
            $table->string('arquivo')->nullable();  // bucket/caminho no R2
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->text('erro')->nullable();
            $table->string('servidor', 100)->nullable();
            $table->timestamps();

            $table->index(['sistema', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_execucoes');
    }
};
