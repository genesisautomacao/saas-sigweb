<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refinamento item 5: o acesso à etapa passa a vir do SETOR (item 1). O campo de perfis
 * fixos da etapa é substituído por `usuarios_autorizados` (subconjunto dos usuários do setor;
 * vazio = todos os usuários do setor). Não confundir com bpmn_fluxos.perfis_autorizados
 * (quem pode ABRIR o fluxo — mantido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->json('usuarios_autorizados')->nullable()->after('setor_id');
        });

        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->dropColumn('perfis_autorizados');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->json('perfis_autorizados')->nullable()->after('tempo_medio_minutos');
        });

        Schema::table('bpmn_etapas', function (Blueprint $table) {
            $table->dropColumn('usuarios_autorizados');
        });
    }
};
