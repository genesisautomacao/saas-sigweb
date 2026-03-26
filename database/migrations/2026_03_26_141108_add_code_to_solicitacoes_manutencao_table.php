<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes_manutencao', function (Blueprint $table) {
            // Adicionamos o UUID que virá do celular. 
            // Colocamos nullable() caso você já tenha registros de teste no banco para não dar erro na migration.
            $table->uuid('code')->nullable()->unique()->after('sequential_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_manutencao', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};