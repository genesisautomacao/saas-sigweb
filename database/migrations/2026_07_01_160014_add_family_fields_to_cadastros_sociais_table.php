<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cadastros_sociais', function (Blueprint $table) {
            // item 094
            $table->enum('situacao_cadastro', ['cadastrado', 'beneficiado', 'aprovado', 'sorteado', 'nao_localizado', 'apresentou_documentos'])
                ->default('cadastrado')->after('nis');
            $table->foreignId('empreendimento_id')->nullable()->after('unidade_imobiliaria_id')->constrained('empreendimentos')->nullOnDelete();

            // item 095 — terreno próprio
            $table->boolean('possui_terreno')->default(false);
            $table->foreignId('terreno_loteamento_id')->nullable()->constrained('loteamentos')->nullOnDelete();
            $table->foreignId('terreno_quadra_id')->nullable()->constrained('quadras')->nullOnDelete();
            $table->foreignId('terreno_lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->enum('terreno_titularidade', ['proprio', 'posse', 'cedido', 'irregular'])->nullable();

            // item 096 — índice de vulnerabilidade calculado
            $table->integer('indice_vulnerabilidade')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cadastros_sociais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empreendimento_id');
            $table->dropConstrainedForeignId('terreno_loteamento_id');
            $table->dropConstrainedForeignId('terreno_quadra_id');
            $table->dropConstrainedForeignId('terreno_lote_id');
            $table->dropColumn(['situacao_cadastro', 'possui_terreno', 'terreno_titularidade', 'indice_vulnerabilidade']);
        });
    }
};
