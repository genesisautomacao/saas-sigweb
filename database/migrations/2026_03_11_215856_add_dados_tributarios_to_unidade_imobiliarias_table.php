<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            $table->json('dados_tributarios')->nullable()->after('geo');
        });
    }

    public function down(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            $table->dropColumn('dados_tributarios');
        });
    }
};