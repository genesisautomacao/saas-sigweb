<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            $table->string('logradouro_nome')->nullable()->after('inscricao_imobiliaria');
            $table->string('numero_imovel')->nullable()->after('logradouro_nome');
        });
    }

    public function down(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            $table->dropColumn(['logradouro_nome', 'numero_imovel']);
        });
    }
};