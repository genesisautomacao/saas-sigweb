<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoteNearestController extends Controller
{
    /**
     * Retorna o lote não visitado mais próximo da posição do cadastrador.
     * Usa ST_Distance com geografia (metros reais) para precisão.
     *
     * GET /api/lotes/nearest?lat={lat}&lon={lon}
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        $tenantId = $request->user()->tenants()->first()->id;
        $lat      = (float) $request->input('lat');
        $lon      = (float) $request->input('lon');

        $lote = DB::selectOne("
            SELECT
                code,
                numero_lote,
                sequential_id,
                status_cadastro,
                ST_AsGeoJSON(geo, 6) AS geo_json,
                ST_Distance(
                    geo::geography,
                    ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography
                ) AS distancia_metros
            FROM lotes
            WHERE tenant_id = :tenant_id
              AND status_cadastro = 'nao_visitado'
              AND geo IS NOT NULL
              AND deleted_at IS NULL
            ORDER BY geo::geography <-> ST_SetSRID(ST_MakePoint(:lon2, :lat2), 4326)::geography
            LIMIT 1
        ", [
            'lat'       => $lat,
            'lon'       => $lon,
            'lat2'      => $lat,
            'lon2'      => $lon,
            'tenant_id' => $tenantId,
        ]);

        if (!$lote) {
            return response()->json(['message' => 'Nenhum lote pendente encontrado.'], 404);
        }

        return response()->json([
            'id'              => $lote->code,
            'numero_lote'     => $lote->numero_lote,
            'sequential_id'   => $lote->sequential_id,
            'status_cadastro' => $lote->status_cadastro,
            'distancia_metros'=> round((float) $lote->distancia_metros, 1),
            'geo_json'        => json_decode($lote->geo_json),
        ]);
    }
}
