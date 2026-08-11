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

    /**
     * Ângulo máximo (graus) entre o segmento do perímetro e o eixo da rua para
     * o segmento contar como testada. Correção de 2026-08-06 (teste do usuário):
     * o corte por distância pura deixava as LATERAIS do lote (perpendiculares à
     * rua, mas dentro da faixa de 15 m) entrarem na testada — o "L" visto no
     * desmembramento/unificação. Paralelo (≤35°) = frente; perpendicular = lateral.
     */
    private const ANGULO_MAX_TESTADA = 35.0;

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
     * Item 60 via CAD (correção 2026-08-06): o "Unir" do CAD soft-deleta os lotes
     * de origem e cria um lote NOVO — que nascia genérico, sem herdar
     * unidades/edificações e sem pós-processo (ocupação/situação/testadas erradas,
     * relatado pelo usuário). Este método dá ao caminho CAD o MESMO motor do
     * remembramento formal: migra os filhos das origens e roda o pós-processo.
     *
     * Guard: só herda de origem que INTERSECTA a nova geometria — um id perdido
     * no estado da página (ex.: modal cancelado) vira no-op inofensivo.
     */
    public function herdarLotesUnidos(Lote $loteNovo, array $origemIds): array
    {
        $origemIds = array_values(array_filter(array_map('intval', (array) $origemIds)));
        $herdadas = ['unidades' => 0, 'edificacoes' => 0];

        if ($origemIds !== []) {
            $validas = DB::table('lotes as origem')
                ->whereIn('origem.id', $origemIds)
                ->where('origem.tenant_id', $loteNovo->tenant_id)
                ->whereRaw('ST_Intersects(origem.geo::geometry, (SELECT geo::geometry FROM lotes WHERE id = ?))', [$loteNovo->id])
                ->pluck('origem.id')
                ->all();

            if ($validas !== []) {
                $herdadas['unidades'] = \App\Models\UnidadeImobiliaria::withoutGlobalScopes()
                    ->whereIn('lote_id', $validas)->whereNull('deleted_at')
                    ->update(['lote_id' => $loteNovo->id]);

                $herdadas['edificacoes'] = Edificacao::withoutGlobalScopes()
                    ->whereIn('lote_id', $validas)->whereNull('deleted_at')
                    ->update(['lote_id' => $loteNovo->id]);
            }
        }

        return array_merge($this->atualizarAposEdicaoGeometrica($loteNovo->fresh()), $herdadas);
    }

    /**
     * Substitui as testadas do lote (a geometria antiga não vale mais) pelas derivadas
     * da nova geometria. Retorna a lista criada (logradouro_id, secao_id, comprimento).
     */
    public function recalcularTestadas(Lote $lote): array
    {
        // As testadas antigas descrevem o polígono ANTERIOR — soft delete (recuperável).
        LoteTestada::query()->where('lote_id', $lote->id)->delete();

        // 1ª escolha: seções de logradouro (item 42 — "Logradouro e Seção de cada testada");
        // fallback: eixo dos logradouros (município sem seções). Ambos com filtro por ÂNGULO.
        $segmentos = $this->segmentosParalelosARua($lote, 'secoes');

        if (empty($segmentos)) {
            $segmentos = $this->segmentosParalelosARua($lote, 'logradouros');
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

    /**
     * Segmentos do PERÍMETRO do lote que são testada de verdade: perto da rua
     * (faixa) E aproximadamente PARALELOS ao eixo dela (ângulo ≤ ANGULO_MAX).
     *
     * Como funciona: o perímetro é decomposto em segmentos (vértice a vértice);
     * cada segmento casa com a rua mais próxima do seu ponto médio; o azimute do
     * segmento é comparado ao azimute local da rua (trecho em torno do ponto mais
     * próximo). Laterais (perpendiculares) e fundos fora da faixa ficam de fora —
     * correção do "L" na testada pós-desmembramento/unificação (2026-08-06).
     *
     * @param  string  $fonte  'secoes' | 'logradouros'
     * @return array<object{secao_id: ?int, logradouro_id: int, seg_json: string, comprimento: float}>
     */
    protected function segmentosParalelosARua(Lote $lote, string $fonte): array
    {
        $faixa = self::FAIXA_TESTADA_M;
        $angulo = self::ANGULO_MAX_TESTADA;

        $ruasCte = $fonte === 'secoes'
            ? "SELECT s.id AS secao_id, s.logradouro_id,
                      (ST_Dump(ST_LineMerge(s.geo::geometry))).geom AS rua
               FROM secoes_logradouro s, lote l
               WHERE s.tenant_id = l.tenant_id AND s.deleted_at IS NULL AND s.geo IS NOT NULL
                 AND ST_DWithin(l.g::geography, s.geo::geography, {$faixa})"
            : "SELECT NULL::bigint AS secao_id, lg.id AS logradouro_id,
                      (ST_Dump(ST_LineMerge(lg.geo::geometry))).geom AS rua
               FROM logradouros lg, lote l
               WHERE lg.tenant_id = l.tenant_id AND lg.deleted_at IS NULL AND lg.geo IS NOT NULL
                 AND ST_DWithin(l.g::geography, lg.geo::geography, {$faixa})";

        return DB::select("
            WITH lote AS (
                SELECT geo::geometry AS g, tenant_id FROM lotes WHERE id = ?
            ),
            bordas AS (
                SELECT (ST_Dump(ST_Boundary(l.g))).geom AS linha FROM lote l
            ),
            segs AS (
                SELECT ST_MakeLine(ST_PointN(b.linha, i), ST_PointN(b.linha, i + 1)) AS seg
                FROM bordas b, generate_series(1, ST_NPoints(b.linha) - 1) AS i
            ),
            segs_validos AS (
                SELECT seg,
                       ST_LineInterpolatePoint(seg, 0.5) AS meio,
                       degrees(ST_Azimuth(ST_StartPoint(seg), ST_EndPoint(seg))) AS az_seg
                FROM segs
                WHERE ST_Length(seg::geography) > 0.05
            ),
            ruas AS ({$ruasCte}),
            casados AS (
                -- cada segmento casa com UMA rua: a mais próxima do seu ponto médio
                SELECT DISTINCT ON (sv.seg)
                    r.secao_id, r.logradouro_id, sv.seg, sv.az_seg, r.rua,
                    ST_LineLocatePoint(r.rua, ST_ClosestPoint(r.rua, sv.meio)) AS frac
                FROM segs_validos sv
                JOIN ruas r ON ST_DWithin(sv.meio::geography, r.rua::geography, {$faixa})
                ORDER BY sv.seg, ST_Distance(sv.meio::geography, r.rua::geography)
            ),
            com_angulo AS (
                SELECT c.secao_id, c.logradouro_id, c.seg, c.az_seg,
                    degrees(ST_Azimuth(
                        ST_LineInterpolatePoint(c.rua, GREATEST(c.frac - 0.02, 0.0)),
                        ST_LineInterpolatePoint(c.rua, LEAST(c.frac + 0.02, 1.0))
                    )) AS az_rua
                FROM casados c
                WHERE GREATEST(c.frac - 0.02, 0.0) < LEAST(c.frac + 0.02, 1.0)
            ),
            paralelos AS (
                SELECT secao_id, logradouro_id, seg
                FROM com_angulo
                WHERE az_rua IS NOT NULL AND az_seg IS NOT NULL
                  AND LEAST(
                        MOD(ABS(az_seg - az_rua)::numeric, 180),
                        180 - MOD(ABS(az_seg - az_rua)::numeric, 180)
                      ) <= {$angulo}
            )
            SELECT secao_id, logradouro_id,
                   ST_AsGeoJSON(ST_Multi(ST_LineMerge(ST_Collect(seg)))) AS seg_json,
                   ST_Length(ST_Collect(seg)::geography) AS comprimento
            FROM paralelos
            GROUP BY secao_id, logradouro_id
        ", [$lote->id]);
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
