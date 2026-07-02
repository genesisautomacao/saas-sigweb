<?php

namespace App\Services\Pgv;

use Illuminate\Support\Facades\DB;

/**
 * Simulação de IPTU com os novos valores da PGV (itens 237–243).
 * Usa o valor m² calculado da face de quadra mais próxima do lote (fallback:
 * parâmetro do setor fiscal). IPTU atual vem de dados_tributarios.valor_total_imposto.
 */
class PgvSimulacaoIptuService
{
    /**
     * @param  array{aliquota:float, percentual_valor_venal:float, limite_aumento?:float|null, bairros?:array}  $params
     * @return array{linhas:array, totais:array}
     */
    public function simular(int $tenantId, array $params): array
    {
        $aliquota   = (float) ($params['aliquota'] ?? 1.0);          // % (238)
        $pctVenal   = (float) ($params['percentual_valor_venal'] ?? 100.0); // % (239)
        $limite     = isset($params['limite_aumento']) && $params['limite_aumento'] !== null
            ? (float) $params['limite_aumento'] : null;             // % (240)
        $bairros    = $params['bairros'] ?? [];

        $sql = "
            SELECT
                l.id AS lote_id,
                l.numero_lote,
                COALESCE(l.area_geo, 0)::float AS area_terreno,
                COALESCE((SELECT SUM(area_geo) FROM edificacoes e WHERE e.lote_id = l.id), 0)::float AS area_edif,
                -- valor m² terreno: face calculada mais próxima, senão parâmetro do setor
                COALESCE(
                    (SELECT fq.valor_m2_calculado FROM face_quadras fq
                       WHERE fq.tenant_id = l.tenant_id AND fq.deleted_at IS NULL
                         AND fq.valor_m2_calculado IS NOT NULL AND fq.geo IS NOT NULL
                       ORDER BY fq.geo <-> ST_Centroid(l.geo) LIMIT 1),
                    (SELECT p.valor_m2_terreno FROM setores_fiscais s
                        JOIN pgv_parametros p ON p.id = s.pgv_parametro_id
                       WHERE s.tenant_id = l.tenant_id AND ST_Intersects(ST_Centroid(l.geo), s.geo) LIMIT 1),
                    0
                )::float AS valor_m2_terreno,
                COALESCE(
                    (SELECT p.valor_m2_edificacao FROM setores_fiscais s
                        JOIN pgv_parametros p ON p.id = s.pgv_parametro_id
                       WHERE s.tenant_id = l.tenant_id AND ST_Intersects(ST_Centroid(l.geo), s.geo) LIMIT 1),
                    0
                )::float AS valor_m2_edif,
                -- IPTU atual = soma do valor_total_imposto das unidades
                COALESCE((SELECT SUM((u.dados_tributarios->>'valor_total_imposto')::numeric)
                            FROM unidade_imobiliarias u
                           WHERE u.lote_id = l.id AND u.deleted_at IS NULL), 0)::float AS iptu_atual
            FROM lotes l
            WHERE l.tenant_id = ? AND l.deleted_at IS NULL AND l.geo IS NOT NULL
        ";
        $bindings = [$tenantId];

        if (!empty($bairros)) {
            $ph = implode(',', array_fill(0, count($bairros), '?'));
            $sql .= " AND l.quadra_id IN (SELECT id FROM quadras WHERE bairro_id IN ($ph))";
            $bindings = array_merge($bindings, $bairros);
        }

        $rows = DB::select($sql, $bindings);

        $linhas = [];
        $somaAtual = $somaSimulado = $somaVenal = 0.0;

        foreach ($rows as $r) {
            $valorTerreno = $r->area_terreno * $r->valor_m2_terreno;
            $valorEdif    = $r->area_edif * $r->valor_m2_edif;
            $valorVenal   = $valorTerreno + $valorEdif;

            $base = $valorVenal * ($pctVenal / 100);
            $iptuSimulado = $base * ($aliquota / 100);

            // Limitador de aumento em relação ao IPTU atual (240)
            $capado = false;
            if ($limite !== null && $r->iptu_atual > 0) {
                $teto = $r->iptu_atual * (1 + $limite / 100);
                if ($iptuSimulado > $teto) {
                    $iptuSimulado = $teto;
                    $capado = true;
                }
            }

            $delta = $r->iptu_atual > 0 ? (($iptuSimulado - $r->iptu_atual) / $r->iptu_atual) * 100 : null;

            $linhas[] = [
                'lote_id'       => $r->lote_id,
                'numero_lote'   => $r->numero_lote,
                'valor_venal'   => round($valorVenal, 2),
                'iptu_atual'    => round($r->iptu_atual, 2),
                'iptu_simulado' => round($iptuSimulado, 2),
                'delta_pct'     => $delta !== null ? round($delta, 1) : null,
                'capado'        => $capado,
            ];

            $somaAtual     += $r->iptu_atual;
            $somaSimulado  += $iptuSimulado;
            $somaVenal     += $valorVenal;
        }

        return [
            'linhas' => $linhas,
            'totais' => [
                'lotes'          => count($linhas),
                'valor_venal'    => round($somaVenal, 2),
                'iptu_atual'     => round($somaAtual, 2),
                'iptu_simulado'  => round($somaSimulado, 2),
                'variacao_pct'   => $somaAtual > 0 ? round((($somaSimulado - $somaAtual) / $somaAtual) * 100, 1) : null,
            ],
        ];
    }
}
