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
                            'layer' => $layerName, // <-- Agora o JS vai saber o que é Lote e o que é Bairro!
                            'sigla' => $item->sigla ?? null,
                            'rgb' => $item->rgb ?? '150,150,150'
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
        $termo = (string) $request->query('termo');

        if (!$tenantId || strlen($termo) < 2) {
            return response()->json([]);
        }

        try {
            // Busca Inteligente com JOINs: Procura em Lotes e em Unidades Imobiliárias
            $lotes = \Illuminate\Support\Facades\DB::table('lotes')
                ->leftJoin('quadras', 'lotes.quadra_id', '=', 'quadras.id')
                // 🚨 MUDANÇA 1: Garante que só vai "juntar" com unidades que NÃO estão deletadas
                ->leftJoin('unidade_imobiliarias', function ($join) {
                    $join->on('unidade_imobiliarias.lote_id', '=', 'lotes.id')
                        ->whereNull('unidade_imobiliarias.deleted_at');
                })
                ->where('lotes.tenant_id', $tenantId)
                ->whereNotNull('lotes.geo')
                // 🚨 MUDANÇA 2: Esconde os lotes que sofreram Soft Delete
                ->whereNull('lotes.deleted_at')
                ->where(function ($q) use ($termo) {
                    // Mantive o ilike que estava no seu arquivo, 
                    // mas se você estiver usando a busca exata (sem os %), é só tirar!
                    $q->where('lotes.numero_lote', $termo)
                        ->orWhere('unidade_imobiliarias.inscricao_imobiliaria', $termo)
                        ->orWhere('unidade_imobiliarias.codigo_imovel_tributario', $termo);
                })
                ->selectRaw('
                lotes.id, 
                lotes.numero_lote, 
                quadras.name as quadra_nome, 
                unidade_imobiliarias.codigo_imovel_tributario,
                ST_AsGeoJSON(ST_Centroid(lotes.geo)) as centroide
            ')
                ->limit(30)
                ->get();

            $uniqueKeys = [];
            $results = [];

            foreach ($lotes as $l) {
                $quadra = $l->quadra_nome ?? 'S/I';
                $cod = $l->codigo_imovel_tributario ?? 'S/C';
                $num = $l->numero_lote ?? 'S/N';

                // A MÁGICA: A chave única agora é Lote + Código Tributário.
                // Isso permite listar as múltiplas unidades do mesmo lote!
                $uniqueKey = $l->id . '_' . $cod;

                if (in_array($uniqueKey, $uniqueKeys))
                    continue;
                $uniqueKeys[] = $uniqueKey;

                $centroide = json_decode($l->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords)
                    continue;

                $results[] = [
                    'id' => $l->id,
                    'lote' => $num,
                    'quadra' => $quadra,
                    'codigo' => $cod,
                    'coords' => $coords
                ];
            }

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro DB: ' . $e->getMessage()], 500);
        }
    }

}