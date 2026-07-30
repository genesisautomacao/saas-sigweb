<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R67-2 — campos padrão "white-label": cada município define o RÓTULO e a LISTA DE VALORES
 * dos campos taxonômicos (ocupacao, situacao_quadra, tipo, tp_construcao,
 * caracteristica_construcao, estado_conservacao, pavimento), além de poder ocultar o que
 * não usa. A COLUNA no banco não muda — só a apresentação —, então relatórios, PGV, mapa e
 * o contrato do app continuam estáveis. Sem linha aqui = padrão do sistema (fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campo_dominios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entidade');            // lote | edificacao | unidade
            $table->string('campo');               // nome da coluna real (ex.: tp_construcao)
            $table->string('label')->nullable();   // null = rótulo padrão do sistema
            $table->json('opcoes')->nullable();    // null/vazio = lista padrão do sistema
            $table->boolean('visivel')->default(true);
            $table->boolean('na_coleta')->default(true);
            $table->boolean('obrigatorio_coleta')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'entidade', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campo_dominios');
    }
};
