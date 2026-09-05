<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Papéis deixam de ser especiais pelo NOME (docs/Modulos_Permissoes.txt, D7):
 *  - papel_sistema: null | 'master' | 'manager' — o que hoje é decidido por
 *    roles.name = 'Master'/'Manager' (Gate::before, map-permissions, região do
 *    coletor, sync de módulos, Delegar Manager). Nome fica livre p/ renomear.
 *  - todos_modulos: papel que recebe automaticamente as permissões de qualquer
 *    módulo ligado depois (o Manager nasce com true; qualquer papel pode ter).
 * Backfill: quem se chama Master/Manager hoje recebe a flag — nada muda p/ ninguém.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('papel_sistema', 20)->nullable()->index();
            $table->boolean('todos_modulos')->default(false);
        });

        DB::table('roles')->where('name', 'Master')->whereNull('tenant_id')->update(['papel_sistema' => 'master']);
        DB::table('roles')->where('name', 'Manager')->whereNotNull('tenant_id')->update(['papel_sistema' => 'manager', 'todos_modulos' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['papel_sistema', 'todos_modulos']);
        });
    }
};
