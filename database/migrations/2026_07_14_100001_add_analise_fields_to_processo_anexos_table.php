<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — checklist de análise por anexo (PD-2).
 * - campo_slug: vínculo robusto anexo ↔ campo 'arquivo' do formulário da etapa (Str::slug do label)
 * - status_analise: pendente | aprovado | reprovado (decisão do analista POR ANEXO)
 * - observacao_analise: motivo da reprovação do item
 * - analisado_por_id / analisado_em: auditoria de quem marcou
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processo_anexos', function (Blueprint $table) {
            $table->string('campo_slug')->nullable()->after('etapa_id');
            $table->string('status_analise')->default('pendente')->after('tipo_anexo');
            $table->text('observacao_analise')->nullable()->after('status_analise');
            $table->foreignId('analisado_por_id')->nullable()->after('observacao_analise')->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable()->after('analisado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('processo_anexos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analisado_por_id');
            $table->dropColumn(['campo_slug', 'status_analise', 'observacao_analise', 'analisado_em']);
        });
    }
};
