<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lote;

class CidadaoMapController extends Controller
{
    public function getLotes(Request $request)
    {
        $tenantId = $request->query('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID não informado'], 400);
        }

        // Busca apenas os lotes da cidade específica que possuem geometria
        $lotes = Lote::where('tenant_id', $tenantId)
            ->whereNotNull('geo')
            ->select('id', 'numero_lote', 'geo')
            ->get();

        $features = [];

        foreach ($lotes as $lote) {
            if (!empty($lote->geo_json)) {
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $lote->id,
                        'numero_lote' => $lote->numero_lote ?? 'S/N',
                    ],
                    // Envia a geometria pura
                    'geometry' => $lote->geo_json
                ];
            }
        }

        // Devolve no formato rigoroso do GeoJSON (FeatureCollection)
        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features
        ]);
    }
}