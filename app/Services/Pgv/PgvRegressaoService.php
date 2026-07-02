<?php

namespace App\Services\Pgv;

use App\Models\PgvAmostra;
use Illuminate\Support\Facades\DB;

/**
 * Motor estatístico da PGV (itens 230–234).
 * Regressão linear simples (mínimos quadrados): valor_m2 = a + b · distancia_ao_polo.
 * Distância calculada em metros ao pólo valorizante mais próximo (PostGIS).
 */
class PgvRegressaoService
{
    /**
     * Retorna os pontos (amostras não espúrias) com distância ao pólo + a equação.
     *
     * @return array{
     *   pontos: array<int, array{amostra_id:int, distancia:float, valor:float, espuria:bool}>,
     *   equacao: array{a:float, b:float, r2:float, n:int}|null
     * }
     */
    public function calcular(int $tenantId): array
    {
        // distância (m) de cada amostra ao pólo mais próximo
        $rows = DB::select("
            SELECT a.id AS amostra_id,
                   a.valor_m2::float AS valor,
                   a.espuria,
                   (SELECT MIN(ST_Distance(a.geo::geography, p.geo::geography))
                      FROM pgv_polos p
                     WHERE p.tenant_id = a.tenant_id AND p.deleted_at IS NULL AND p.geo IS NOT NULL
                   ) AS distancia
            FROM pgv_amostras a
            WHERE a.tenant_id = ? AND a.deleted_at IS NULL AND a.geo IS NOT NULL
        ", [$tenantId]);

        $pontos = [];
        foreach ($rows as $r) {
            if ($r->distancia === null) {
                continue; // sem pólo cadastrado ainda
            }
            $pontos[] = [
                'amostra_id' => (int) $r->amostra_id,
                'distancia'  => round((float) $r->distancia, 2),
                'valor'      => round((float) $r->valor, 2),
                'espuria'    => (bool) $r->espuria,
            ];
        }

        $validos = array_values(array_filter($pontos, fn($p) => !$p['espuria']));
        $equacao = $this->minimosQuadrados($validos);

        return ['pontos' => $pontos, 'equacao' => $equacao];
    }

    /**
     * Mínimos quadrados sobre pares (x=distancia, y=valor). Retorna a, b, R², n.
     */
    public function minimosQuadrados(array $pontos): ?array
    {
        $n = count($pontos);
        if ($n < 2) {
            return null;
        }

        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($pontos as $p) {
            $x = $p['distancia'];
            $y = $p['valor'];
            $sx += $x;
            $sy += $y;
            $sxy += $x * $y;
            $sxx += $x * $x;
        }

        $den = ($n * $sxx) - ($sx * $sx);
        if (abs($den) < 1e-9) {
            return null; // todos na mesma distância
        }

        $b = (($n * $sxy) - ($sx * $sy)) / $den;   // inclinação
        $a = ($sy - ($b * $sx)) / $n;              // intercepto

        // R² (coeficiente de determinação)
        $mediaY = $sy / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($pontos as $p) {
            $prev = $a + $b * $p['distancia'];
            $ssRes += ($p['valor'] - $prev) ** 2;
            $ssTot += ($p['valor'] - $mediaY) ** 2;
        }
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 1.0;

        return [
            'a'  => round($a, 4),
            'b'  => round($b, 6),
            'r2' => round($r2, 4),
            'n'  => $n,
        ];
    }

    /** Marca/desmarca amostra como espúria (item 232) e devolve o recálculo. */
    public function toggleEspuria(int $tenantId, int $amostraId): array
    {
        $amostra = PgvAmostra::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->find($amostraId);
        if ($amostra) {
            $amostra->espuria = !$amostra->espuria;
            $amostra->save();
        }
        return $this->calcular($tenantId);
    }
}
