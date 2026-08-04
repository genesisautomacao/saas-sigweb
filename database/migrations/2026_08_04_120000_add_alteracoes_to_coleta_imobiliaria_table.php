<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 8 (App de Coletas) — Frente D1: o push grava o antes→depois de cada
 * campo alterado pelo coletor de rua ({"lote.ocupacao": {"de": x, "para": y}, ...}).
 * É a base auditável do Relatório de Validação da Coleta (passo 8 do fluxo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coleta_imobiliaria', function (Blueprint $table) {
            $table->jsonb('alteracoes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coleta_imobiliaria', function (Blueprint $table) {
            $table->dropColumn('alteracoes');
        });
    }
};
