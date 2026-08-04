<?php

namespace App\Services\Gis;

use App\Models\Edificacao;
use App\Models\Lote;
use App\Models\LoteTestada;
use App\Services\Coleta\CampoCustomizadoService;
use Illuminate\Support\Facades\DB;

/**
 * T2.2 (PoC Tangará, itens 2.7-59/60) — pós-processamento cadastral de um lote cuja
 * geometria mudou (desmembramento, unificação).
 *
 * 1) TESTADAS: recalculadas pela interseção do PERÍMETRO do lote com o buffer das
 *    seções de logradouro (fallback: logradouros, p/ município sem seções). A maior
 *    vira `principal` e alimenta `lotes.main_facade_length`.
 * 2) OCUPAÇÃO: derivada de fato — tem edificação vinculada = construído; senão baldio.
 * 3) SITUAÇÃO NA QUADRA: campo customizado (D6) — o sistema apenas SUGERE pelo nº de
 *    logradouros distintos com testada (0=encravado, 1=meio de quadra, 2+=esquina) e
 *    grava a sugestão; o usuário ajusta na ficha (decisão do usuário, 2026-07-31).
 */
class PosEdicaoLoteService
{
    /** Faixa (m) entre o perímetro do lote e a seção/eixo para considerar testada. */
    private const FAIXA_TESTADA_M = 15.0;

    /** Comprimento mínimo (m) para um segmento contar como testada. */
    private const COMPRIMENTO_MINIMO_M = 1.0;

    /** Rótulos padrão da sugestão (o kit municipal pode ter renomeado — casa por comparável). */
    private const SUGESTOES = [
        0 => 'Encravado',
        1 => 'Meio de Quadra',
        2 => 'Esquina',
    ];

    public function atualizarAposEdicaoGeometrica(Lote $lote): array
    {
        $testadas = $this->recalcularTestadas($lote);
        $ocupacao = $this->derivarOcupacao($lote);
        $situacao = $this->sugerirSituacaoQuadra($lote, $testadas);

        return [
            'testadas' => count($testadas),
            'ocupacao' => $ocupacao,
            'situacao_sugerida' => $situacao,
        ];
    }

    /**
     * Substitui as testadas do lote (a geometria antiga não vale mais) pelas derivadas
     * da nova geometria. Retorna a lista criada (logradouro_id, secao_id, comprimento).
     */
    public function recalcularTestadas(Lote $lote): array
    {
        // As testadas antigas descrevem o polígono ANTERIOR — soft delete (recuperável).
        LoteTestada::query()->where('lote_id', $lote->id)->delete();

        $faixa = self::FAIXA_TESTADA_M;

        // 1ª escolha: seções de logradouro (item 42 — "Logradouro e Seção de cada testada")
        $segmentos = DB::select("
            SELECT
                s.id AS secao_id,
                s.logradouro_id,
                ST_AsGeoJSON(ST_CollectionExtract(ST_Intersection(
                    ST_Boundary(l.geo::geometry),
                    ST_Buffer(s.geo::geography, {$faixa})::geometry
                ), 2)) AS seg_json,
                ST_Length(ST_CollectionExtract(ST_Intersection(
                    ST_Boundary(l.geo::geometry),
                    ST_Buffer(s.geo::geography, {$faixa})::geometry
                ), 2)::geography) AS comprimento
            FROM lotes l
            JOIN secoes_logradouro s
              ON s.tenant_id = l.tenant_id
             AND s.deleted_at IS NULL
             AND s.geo IS NOT NULL
             AND ST_DWithin(ST_Boundary(l.geo::geometry)::geography, s.geo::geography, {$faixa})
            WHERE l.id = ?
        ", [$lote->id]);

        // Fallback: município sem seções — deriva pelo eixo dos logradouros
        if (empty($segmentos)) {
            $segmentos = DB::select("
                SELECT
                    NULL::bigint AS secao_id,
                    lg.id AS logradouro_id,
                    ST_AsGeoJSON(ST_CollectionExtract(ST_Intersection(
                        ST_Boundary(l.geo::geometry),
                        ST_Buffer(lg.geo::geography, {$faixa})::geometry
                    ), 2)) AS seg_json,
                    ST_Length(ST_CollectionExtract(ST_Intersection(
                        ST_Boundary(l.geo::geometry),
                        ST_Buffer(lg.geo::geography, {$faixa})::geometry
                    ), 2)::geography) AS comprimento
                FROM lotes l
                JOIN logradouros lg
                  ON lg.tenant_id = l.tenant_id
                 AND lg.deleted_at IS NULL
                 AND lg.geo IS NOT NULL
                 AND ST_DWithin(ST_Boundary(l.geo::geometry)::geography, lg.geo::geography, {$faixa})
                WHERE l.id = ?
            ", [$lote->id]);
        }

        $criadas = [];
        $ordenados = collect($segmentos)
            ->filter(fn ($s) => (float) $s->comprimento >= self::COMPRIMENTO_MINIMO_M)
            ->sortByDesc(fn ($s) => (float) $s->comprimento)
            ->values();

        foreach ($ordenados as $indice => $seg) {
            $geom = json_decode($seg->seg_json, true);

            $criadas[] = LoteTestada::create([
                'tenant_id' => $lote->tenant_id,
                'lote_id' => $lote->id,
                'logradouro_id' => $seg->logradouro_id,
                'secao_logradouro_id' => $seg->secao_id,
                'tipo' => $indice === 0 ? 'principal' : 'secundaria',
                'comprimento' => round((float) $seg->comprimento, 2),
                'geo' => ($geom && ! empty($geom['coordinates'])) ? $geom : null,
            ]);
        }

        // Testada principal alimenta o cache do lote (era somado "no braço" na unificação)
        try {
            DB::table('lotes')->where('id', $lote->id)->update([
                'main_facade_length' => isset($ordenados[0]) ? round((float) $ordenados[0]->comprimento, 2) : null,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("PosEdicaoLote: falha ao atualizar main_facade_length do lote {$lote->id}: ".$e->getMessage());
        }

        return $criadas;
    }

    /** Item 60: "Ocupação do Lote (Baldio ou Construído)" — derivada de fato, não chutada. */
    public function derivarOcupacao(Lote $lote): string
    {
        $ocupacao = Edificacao::query()->where('lote_id', $lote->id)->exists()
            ? 'construido'
            : 'baldio';

        DB::table('lotes')->where('id', $lote->id)->update(['ocupacao' => $ocupacao]);

        return $ocupacao;
    }

    /**
     * Situação na quadra é campo do MUNICÍPIO (customizado) — o sistema sugere pelo
     * nº de logradouros distintos com testada e grava a sugestão para o usuário
     * confirmar/ajustar na ficha. Sem o campo no kit do tenant, não grava nada.
     */
    public function sugerirSituacaoQuadra(Lote $lote, array $testadas): ?string
    {
        $def = CampoCustomizadoService::definicoes('lote', $lote->tenant_id)
            ->firstWhere('slug', 'situacao_quadra');

        if (! $def) {
            return null;
        }

        $logradourosDistintos = collect($testadas)->pluck('logradouro_id')->filter()->unique()->count();
        $sugestaoPadrao = self::SUGESTOES[min($logradourosDistintos, 2)];

        // Usa o rótulo DO KIT do município quando houver equivalente (comparável sem caixa/acento)
        $sugestao = collect($def->opcoes ?? [])
            ->first(fn ($o) => \App\Services\Coleta\CampoDominioService::comparavel((string) $o)
                === \App\Services\Coleta\CampoDominioService::comparavel($sugestaoPadrao))
            ?? $sugestaoPadrao;

        $dados = (array) $lote->fresh()->dados_customizados;
        $dados['situacao_quadra'] = $sugestao;

        DB::table('lotes')->where('id', $lote->id)->update([
            'dados_customizados' => json_encode($dados, JSON_UNESCAPED_UNICODE),
        ]);

        return $sugestao;
    }
}
