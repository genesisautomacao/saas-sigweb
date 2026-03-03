<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerimetroUrbano;
use App\Models\Zona;
use App\Models\Bairro;
use App\Models\Quadra;
use App\Models\Logradouro;
use App\Models\Lote;
use App\Models\Edificacao;
use App\Models\UnidadeImobiliaria;

class MapDataController extends Controller
{
    public function getMapData(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $layer = $request->query('layer');

        if (!$tenantId || !$layer) {
            return response()->json(['error' => 'Parâmetros inválidos'], 400);
        }

        $buildFeatureCollection = function ($items, $layerName) {
            $features = [];
            foreach ($items as $item) {
                if (!empty($item->geo_json) && !empty($item->geo_json->coordinates)) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $item->id,
                            'name' => $item->name ?? $item->numero_lote ?? $item->codigo_imovel_tributario ?? 'S/N',
                            'codigo' => $item->code,
                            'layer' => $layerName // <-- Agora o JS vai saber o que é Lote e o que é Bairro!
                        ],
                        'geometry' => $item->geo_json
                    ];
                }
            }
            return ['type' => 'FeatureCollection', 'features' => $features];
        };

        $data = [];

        // Substitua os chamados no switch para passar o nome da camada:
        switch ($layer) {
            case 'perimetros':
                $data = $buildFeatureCollection(PerimetroUrbano::where('tenant_id', $tenantId)->get(), 'perimetros');
                break;
            case 'zonas':
                $data = $buildFeatureCollection(Zona::where('tenant_id', $tenantId)->get(), 'zonas');
                break;
            case 'bairros':
                $data = $buildFeatureCollection(Bairro::where('tenant_id', $tenantId)->get(), 'bairros');
                break;
            case 'quadras':
                $data = $buildFeatureCollection(Quadra::where('tenant_id', $tenantId)->get(), 'quadras');
                break;
            case 'logradouros':
                $data = $buildFeatureCollection(Logradouro::where('tenant_id', $tenantId)->get(), 'logradouros');
                break;
            case 'lotes':
                $lotes = Lote::where('tenant_id', $tenantId)->select('id', 'numero_lote', 'geo')->get();
                $data = $buildFeatureCollection($lotes, 'lotes');
                break;
            case 'edificacoes':
                $data = $buildFeatureCollection(Edificacao::where('tenant_id', $tenantId)->get(), 'edificacoes');
                break;
            default:
                return response()->json(['error' => 'Camada não encontrada'], 404);
        }

        return response()->json($data);
    }

    public function searchLote(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $numeroLote = $request->query('numero');

        if (!$tenantId || !$numeroLote) {
            return response()->json(['error' => 'Parâmetros insuficientes'], 400);
        }

        // Busca o lote e retorna o centroide em formato GeoJSON (Ponto)
        $lote = \App\Models\Lote::where('tenant_id', $tenantId)
            ->where('numero_lote', $numeroLote)
            ->selectRaw('id, numero_lote, ST_AsGeoJSON(ST_Centroid(geo)) as centroide')
            ->first();

        // 1ª Trava: Lote não existe na base
        if (!$lote) {
            return response()->json(['message' => 'Lote não encontrado na base de dados.'], 404);
        }

        // 2ª Trava: Lote existe, mas a coluna 'geo' está vazia (NULL)
        if (empty($lote->centroide)) {
            return response()->json(['message' => 'Lote encontrado, mas não possui desenho no mapa.'], 404);
        }

        $centroideData = json_decode($lote->centroide);

        // 3ª Trava: Falha ao decodificar o GeoJSON
        if (!$centroideData || !isset($centroideData->coordinates)) {
            return response()->json(['message' => 'Erro ao processar as coordenadas deste lote.'], 500);
        }

        return response()->json([
            'id' => $lote->id,
            'numero' => $lote->numero_lote,
            'coords' => $centroideData->coordinates
        ]);
    }
}