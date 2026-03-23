<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setores_fiscais', function (Blueprint $table) {
            // Adiciona a coluna de área logo após a descrição
            $table->decimal('area_geo', 15, 2)->default(0)->after('descricao');
        });
    }

    public function down(): void
    {
        Schema::table('setores_fiscais', function (Blueprint $table) {
            $table->dropColumn('area_geo');
        });
    }
};