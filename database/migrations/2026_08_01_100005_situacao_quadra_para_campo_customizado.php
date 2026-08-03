<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — `lotes.situacao_quadra` vira campo customizado.
 *
 * Decisão do usuário (lista aprovada): nada no código ramifica pelo valor e a lista
 * (meio de quadra / esquina / encravado / ...) varia por município. No desmembramento/
 * unificação (T2.2) a sugestão geométrica preenche o slug se o município tiver o campo.
 *
 * `ocupacao` NÃO sai: é binário estrutural (itens 42/51/60 do edital).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lotes', 'situacao_quadra')) {
            return;
        }

        $afetados = DB::update("
            UPDATE lotes
            SET dados_customizados = COALESCE(dados_customizados, '{}'::jsonb)
                || jsonb_build_object('situacao_quadra', situacao_quadra)
            WHERE situacao_quadra IS NOT NULL AND situacao_quadra <> ''
        ");

        echo "  ── lotes.situacao_quadra: {$afetados} registro(s) copiados para dados_customizados" . PHP_EOL;

        Schema::table('lotes', fn (Blueprint $t) => $t->dropColumn('situacao_quadra'));
    }

    public function down(): void
    {
        if (Schema::hasColumn('lotes', 'situacao_quadra')) {
            return;
        }

        Schema::table('lotes', fn (Blueprint $t) => $t->string('situacao_quadra', 30)->nullable());

        DB::statement("
            UPDATE lotes
            SET situacao_quadra = dados_customizados->>'situacao_quadra'
            WHERE dados_customizados->>'situacao_quadra' IS NOT NULL
        ");
    }
};
