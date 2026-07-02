<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Distingue usuário da PREFEITURA (equipe) do CIDADÃO.
 * Cidadão (auto-cadastro no painel do cidadão) NÃO pode acessar o painel `app`
 * nem aparecer na lista da Equipe. Ver User::canAccessPanel e UserResource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tipo')->default('prefeitura')->after('email'); // prefeitura | cidadao
        });

        // Backfill: um usuário SEM nenhum papel é um CIDADÃO (auto-cadastro). Toda a equipe da
        // prefeitura (Master, Manager, analistas) tem papel atribuído — este é o sinal confiável,
        // inclusive para cidadãos criados ANTES do vínculo User↔Pessoa (que não têm pessoas.user_id).
        // Assim Master/Manager/analistas (com papel) permanecem 'prefeitura' e não são bloqueados.
        //
        // ⚠️ PRODUÇÃO: se existir algum usuário-EQUIPE sem papel atribuído, ele será marcado como
        // 'cidadao' e perderá o acesso ao painel. Revise a lista resultante e ajuste esses casos
        // (atribua um papel e/ou corrija o `tipo`) antes/depois do deploy.
        DB::statement("
            UPDATE users SET tipo = 'cidadao'
            WHERE id NOT IN (
                SELECT DISTINCT model_id FROM model_has_roles WHERE model_type = 'App\\\\Models\\\\User'
            )
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
