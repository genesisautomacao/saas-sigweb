<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boletim de Coleta com 3 estados por campo (spec do usuário, 2026-08-04):
 * "Não usar" / "Apenas leitura" / "Preencher" (+ obrigatório quando Preencher).
 *
 * Modelo: na_coleta = aparece no app (qualquer modo) · leitura_coleta = true →
 * somente leitura (o coletor vê o valor atual e não edita) · obrigatorio_coleta
 * só vale no modo Preencher. Default false = comportamento atual preservado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campo_dominios', function (Blueprint $table) {
            $table->boolean('leitura_coleta')->default(false);
        });

        Schema::table('campos_customizados', function (Blueprint $table) {
            $table->boolean('leitura_coleta')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('campo_dominios', function (Blueprint $table) {
            $table->dropColumn('leitura_coleta');
        });

        Schema::table('campos_customizados', function (Blueprint $table) {
            $table->dropColumn('leitura_coleta');
        });
    }
};
