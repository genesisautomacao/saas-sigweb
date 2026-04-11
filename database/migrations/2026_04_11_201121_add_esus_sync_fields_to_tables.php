<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Atualizando a tabela de Pessoas
        Schema::table('pessoas', function (Blueprint $blueprint) {
            // CNS é a chave mestre da saúde no Brasil
            $blueprint->string('cns', 15)->nullable()->after('cpf');
            // ID único do cidadão no e-SUS para sincronia (UUID ou Inteiro)
            $blueprint->string('esus_id')->nullable()->after('cns');
            $blueprint->index('cns');
            $blueprint->index('esus_id');
        });

        // 2. Atualizando a tabela de Cadastros Sociais (Famílias)
        Schema::table('cadastros_sociais', function (Blueprint $blueprint) {
            // ID único da ficha domiciliar/familiar no e-SUS
            $blueprint->string('esus_familia_id')->nullable()->after('nis');
            $blueprint->index('esus_familia_id');
        });

        // 3. Criando a tabela de Condições de Saúde (O "Item 6" exige isso)
        Schema::create('pessoa_saude_condicoes', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $blueprint->foreignId('pessoa_id')->constrained('pessoas')->onDelete('cascade');
            
            // Campos booleanos típicos do e-SUS AB
            $blueprint->boolean('is_hipertenso')->default(false);
            $blueprint->boolean('is_diabetico')->default(false);
            $blueprint->boolean('is_gestante')->default(false);
            $blueprint->boolean('is_fumante')->default(false);
            $blueprint->boolean('is_pcd')->default(false);
            $blueprint->boolean('is_acamado')->default(false);
            
            $blueprint->timestamp('ultima_sincronizacao')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_saude_condicoes');
        Schema::table('pessoas', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['cns', 'esus_id']);
        });
        Schema::table('cadastros_sociais', function (Blueprint $blueprint) {
            $blueprint->dropColumn('esus_familia_id');
        });
    }
};