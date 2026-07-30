<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R67-1 — valores dos campos customizados por município.
 * `edificacoes` ganha aqui sua primeira coluna JSON.
 */
return new class extends Migration
{
    private const TABELAS = ['lotes', 'edificacoes', 'unidade_imobiliarias'];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->json('dados_customizados')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('dados_customizados');
            });
        }
    }
};
