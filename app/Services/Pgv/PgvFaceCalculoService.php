<?php

namespace App\Services\Pgv;

use App\Models\FaceQuadra;
use App\Models\PgvConfiguracao;
use Illuminate\Support\Facades\DB;

/**
 * Calcula o valor do m² por face de quadra (itens 233/234) a partir da
 * equação da regressão (a + b·distancia ao pólo) + fatores de homogeneização (230).
 */
class PgvFaceCalculoService
{
    public function __construct(private PgvRegressaoService $regressao) {}

    /**
     * Recalcula todas as faces do tenant. Retorna resumo.
     *
     * @return array{faces:int, equacao: array|null}
     */
    public function recalcularTodas(int $tenantId): array
    {
        $res = $this->regressao->calcular($tenantId);
        $eq = $res['equacao'];
        if (!$eq) {
            return ['faces' => 0, 'equacao' => null];
        }

        $config = PgvConfiguracao::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        $fatorGlobal = $this->fatorHomogeneizacao($config);

        // distância (m) de cada face ao pólo mais próximo + o pólo/setor
        $rows = DB::select("
            SELECT f.id AS face_id,
                   p.polo_id,
                   p.distancia,
                   (SELECT s.id FROM setores_fiscais s
                     WHERE s.tenant_id = f.tenant_id AND s.deleted_at IS NULL
                       AND ST_Intersects(ST_LineInterpolatePoint(ST_LineMerge(f.geo), 0.5), s.geo)
                     LIMIT 1) AS setor_id
            FROM face_quadras f
            CROSS JOIN LATERAL (
                SELECT p.id AS polo_id,
                       ST_Distance(f.geo::geography, p.geo::geography) AS distancia
                  FROM pgv_polos p
                 WHERE p.tenant_id = f.tenant_id AND p.deleted_at IS NULL AND p.geo IS NOT NULL
                 ORDER BY f.geo <-> p.geo
                 LIMIT 1
            ) p
            WHERE f.tenant_id = ? AND f.deleted_at IS NULL AND f.geo IS NOT NULL
        ", [$tenantId]);

        $n = 0;
        foreach ($rows as $r) {
            $dist = (float) $r->distancia;
            $valor = ($eq['a'] + $eq['b'] * $dist) * $fatorGlobal;
            if ($valor < 0) {
                $valor = 0;
            }

            DB::table('face_quadras')->where('id', $r->face_id)->update([
                'distancia_polo'     => round($dist, 2),
                'pgv_polo_id'        => $r->polo_id,
                'setor_fiscal_id'    => $r->setor_id,
                'valor_m2_calculado' => round($valor, 2),
                'updated_at'         => now(),
            ]);
            $n++;
        }

        return ['faces' => $n, 'equacao' => $eq];
    }

    /** Fator global de homogeneização a partir dos fatores configurados (item 230). */
    private function fatorHomogeneizacao(?PgvConfiguracao $config): float
    {
        if (!$config || empty($config->fatores) || !is_array($config->fatores)) {
            return 1.0;
        }
        // Produto dos fatores configurados (ex.: pavimentação, topografia, esquina)
        $fator = 1.0;
        foreach ($config->fatores as $f) {
            $v = (float) ($f['valor'] ?? $f ?? 1);
            if ($v > 0) {
                $fator *= $v;
            }
        }
        return $fator > 0 ? $fator : 1.0;
    }
}
