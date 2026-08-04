<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correção do Boletim de Coleta (feedback do usuário, 2026-08-04): a obrigatoriedade
 * no BOLETIM é independente da obrigatoriedade no sistema web — um campo pode ser
 * opcional no cadastro e obrigatório em campo (e vice-versa). O campo padrão
 * (campo_dominios) já tinha `obrigatorio_coleta`; o customizado usava um único
 * `obrigatorio` para os dois mundos.
 *
 * Backfill = valor atual de `obrigatorio` — preserva o comportamento do app hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_customizados', function (Blueprint $table) {
            $table->boolean('obrigatorio_coleta')->default(false);
        });

        DB::statement('UPDATE campos_customizados SET obrigatorio_coleta = obrigatorio');
    }

    public function down(): void
    {
        Schema::table('campos_customizados', function (Blueprint $table) {
            $table->dropColumn('obrigatorio_coleta');
        });
    }
};
