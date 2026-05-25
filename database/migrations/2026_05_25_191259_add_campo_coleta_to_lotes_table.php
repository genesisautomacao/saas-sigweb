<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->enum('status_cadastro', ['nao_visitado', 'coletado', 'pendente', 'inconformidade'])
                  ->default('nao_visitado')
                  ->after('observacao');
            $table->enum('ocupacao', ['baldio', 'construido'])->nullable()->after('status_cadastro');
            $table->enum('situacao_quadra', ['meio_quadra', 'esquina', 'encravado'])->nullable()->after('ocupacao');
            $table->string('foto_lateral_esq')->nullable()->after('foto_frontal');
            $table->string('foto_lateral_dir')->nullable()->after('foto_lateral_esq');
            $table->text('inconformidade_descricao')->nullable()->after('foto_lateral_dir');
            $table->json('dados_vistoria')->nullable()->after('inconformidade_descricao');
            $table->foreignId('coletado_por_id')->nullable()->constrained('users')->nullOnDelete()->after('dados_vistoria');
            $table->timestamp('coletado_em')->nullable()->after('coletado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropForeign(['coletado_por_id']);
            $table->dropColumn([
                'status_cadastro', 'ocupacao', 'situacao_quadra',
                'foto_lateral_esq', 'foto_lateral_dir',
                'inconformidade_descricao', 'dados_vistoria',
                'coletado_por_id', 'coletado_em',
            ]);
        });
    }
};
