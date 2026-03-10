<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitacoes_manutencao', function (Blueprint $table) {
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_manutencao', function (Blueprint $table) {
            $table->dropForeign(['pessoa_id']);
            $table->dropColumn('pessoa_id');
        });
    }
};