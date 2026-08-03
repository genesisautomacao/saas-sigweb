<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — a coleta deixa de ser coluna do lote e vira entidade própria.
 *
 * Motivo (decisão do usuário, 2026-08-01): o cadastrador preenche itens do lote, das
 * unidades E das edificações, e amanhã pode ser necessário coletar outras entidades
 * (árvore, poste...). Coleta não é atributo do lote — é um evento sobre o cadastro.
 *
 * Polimórfica em vez de cabeçalho+itens: a visita continua reconstituível por
 * campanha + coletado_por + coletado_em, e uma camada nova passa a ser coletável sem
 * tocar no schema. Se um dia precisar do agrupamento formal, entra um `grupo_id`.
 *
 * `lotes.status_cadastro` PERMANECE como cache denormalizado da coleta vigente: o mapa
 * colore milhares de polígonos por requisição e não deve pagar um JOIN com subquery de
 * campanha nesse caminho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coleta_imobiliaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Lote | Edificacao | UnidadeImobiliaria | (futuro: Arvore, Poste, ...)
            $table->string('coletavel_type');
            $table->unsignedBigInteger('coletavel_id');

            // Recadastramento entra como campanha nova, sem sobrescrever o histórico.
            $table->string('campanha', 100)->default('inicial');

            $table->string('status', 30)->default('nao_visitado');

            $table->foreignId('coletado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('coletado_em')->nullable();

            $table->text('observacao')->nullable();
            $table->text('inconformidade_descricao')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'coletavel_type', 'coletavel_id', 'campanha'], 'coleta_imob_alvo_index');
            $table->index(['tenant_id', 'campanha', 'status'], 'coleta_imob_campanha_index');
            $table->index(['tenant_id', 'coletado_por_id', 'coletado_em'], 'coleta_imob_produtividade_index');
        });

        // ── Migra o que já existe no lote para a campanha "inicial" ──────────
        // Só lotes que têm algum sinal de coleta; não faz sentido criar 11 mil linhas
        // vazias para lotes nunca visitados (o status vive no cache do próprio lote).
        if (Schema::hasColumn('lotes', 'coletado_em')) {
            $migrados = DB::statement("
                INSERT INTO coleta_imobiliaria
                    (tenant_id, coletavel_type, coletavel_id, campanha, status,
                     coletado_por_id, coletado_em, observacao, inconformidade_descricao,
                     created_at, updated_at)
                SELECT
                    tenant_id,
                    'App\\\\Models\\\\Lote',
                    id,
                    'inicial',
                    COALESCE(status_cadastro, 'nao_visitado'),
                    coletado_por_id,
                    coletado_em,
                    observacao,
                    inconformidade_descricao,
                    NOW(),
                    NOW()
                FROM lotes
                WHERE deleted_at IS NULL
                  AND (
                        coletado_por_id IS NOT NULL
                     OR coletado_em IS NOT NULL
                     OR (observacao IS NOT NULL AND observacao <> '')
                     OR (inconformidade_descricao IS NOT NULL AND inconformidade_descricao <> '')
                     OR (status_cadastro IS NOT NULL AND status_cadastro <> 'nao_visitado')
                  )
            ");

            $total = DB::table('coleta_imobiliaria')->count();
            echo PHP_EOL . "  ── coleta_imobiliaria: {$total} coleta(s) migrada(s) do lote para a campanha 'inicial'" . PHP_EOL;
        }

        // ── Remove as colunas que migraram ───────────────────────────────────
        // status_cadastro NÃO sai: é o cache que colore o mapa.
        Schema::table('lotes', function (Blueprint $table) {
            foreach (['coletado_por_id', 'coletado_em', 'observacao', 'inconformidade_descricao'] as $coluna) {
                if (Schema::hasColumn('lotes', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            if (! Schema::hasColumn('lotes', 'coletado_por_id')) {
                $table->foreignId('coletado_por_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('lotes', 'coletado_em')) {
                $table->timestamp('coletado_em')->nullable();
            }
            if (! Schema::hasColumn('lotes', 'observacao')) {
                $table->text('observacao')->nullable();
            }
            if (! Schema::hasColumn('lotes', 'inconformidade_descricao')) {
                $table->text('inconformidade_descricao')->nullable();
            }
        });

        // Devolve a coleta vigente de cada lote para as colunas.
        DB::statement("
            UPDATE lotes l SET
                coletado_por_id = c.coletado_por_id,
                coletado_em = c.coletado_em,
                observacao = c.observacao,
                inconformidade_descricao = c.inconformidade_descricao
            FROM coleta_imobiliaria c
            WHERE c.coletavel_type = 'App\\\\Models\\\\Lote'
              AND c.coletavel_id = l.id
              AND c.deleted_at IS NULL
        ");

        Schema::dropIfExists('coleta_imobiliaria');
    }
};
