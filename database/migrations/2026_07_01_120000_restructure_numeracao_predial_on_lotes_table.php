<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Renomeia: o campo passa a guardar o número ANTES da nova numeração gerada.
            $table->renameColumn('numero_predial_calculado', 'numero_predial_antigo');
        });

        Schema::table('lotes', function (Blueprint $table) {
            // Endereço herdado do JSON dados_tributarios da unidade imobiliária
            // (propagado no save de UnidadeImobiliaria). Facilita busca no cadastro.
            $table->string('tipo_logradouro')->nullable()->after('numero_lote');
            $table->string('logradouro')->nullable()->after('tipo_logradouro');
            // numero_logradouro = número predial ATUAL do lote. Herdado do tributário
            // e sobrescrito pelo gerador de numeração predial.
            $table->string('numero_logradouro')->nullable()->after('logradouro');
            $table->string('cep')->nullable()->after('numero_logradouro');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['tipo_logradouro', 'logradouro', 'numero_logradouro', 'cep']);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->renameColumn('numero_predial_antigo', 'numero_predial_calculado');
        });
    }
};
