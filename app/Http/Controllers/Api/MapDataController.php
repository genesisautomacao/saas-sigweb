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
use App\Models\Poste;
use App\Models\Arvore;
use App\Models\Cemiterio;

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
                            'rgb' => $item->rgb ?? '150,150,150',
                            'structural_condition' => $item->structural_condition ?? null,
                            'sequential_id' => $item->sequential_id ?? null,
                            'phytosanitary_condition' => $item->phytosanitary_condition ?? null,
                            'size' => $item->size ?? null,
                            'tem_chamado' => (bool) ($item->tem_chamado ?? false),
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

            case 'postes':
                $postes = Poste::where('tenant_id', $tenantId)
                    ->select('id', 'sequential_id', 'geo', 'structural_condition', 'code')
                    ->withExists([
                        'solicitacoesManutencao as tem_chamado' => function ($query) {
                            $query->whereIn('status', ['pendente', 'analise', 'aprovada_os']);
                        }
                    ])
                    ->get();
                $data = $buildFeatureCollection($postes, 'postes');
                break;

            case 'arvores': // 🛑 NOVO CASE
                $arvores = Arvore::where('tenant_id', $tenantId)
                    ->select('id', 'geo', 'botanical_species', 'phytosanitary_condition', 'size', 'sequential_id')
                    ->withExists([
                        'solicitacoesManutencao as tem_chamado' => function ($query) {
                            $query->whereIn('status', ['pendente', 'analise', 'aprovada_os']);
                        }
                    ])
                    ->get();
                $data = $buildFeatureCollection($arvores, 'arvores');
                break;

            case 'cemiterios': // <-- NOVO BLOCO
                $cemiterios = Cemiterio::where('tenant_id', $tenantId)->select('id', 'name', 'code', 'geo')->get();
                $data = $buildFeatureCollection($cemiterios, 'cemiterios');
                break;

            case 'quadras_cemiterio':
                $quadras = \App\Models\QuadraCemiterio::where('tenant_id', $tenantId)->select('id', 'name', 'code', 'geo')->get();
                $data = $buildFeatureCollection($quadras, 'quadras_cemiterio');
                break;

            case 'logradouros_cemiterio':
                $logradouros = \App\Models\LogradouroCemiterio::where('tenant_id', $tenantId)->select('id', 'name', 'code', 'geo')->get();
                $data = $buildFeatureCollection($logradouros, 'logradouros_cemiterio');
                break;

            case 'jazigos':
                // Enviamos o código em vez de name para exibir na label
                $jazigos = \App\Models\Jazigo::where('tenant_id', $tenantId)->select('id', 'codigo as name', 'code', 'geo')->get();
                $data = $buildFeatureCollection($jazigos, 'jazigos');
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

        if (!$tenantId || strlen($termo) < 1) {
            return response()->json([]);
        }

        try {
            $results = [];

            // --- 1. BUSCA DE LOTES E UNIDADES ---
            $lotes = \Illuminate\Support\Facades\DB::table('lotes')
                ->leftJoin('quadras', 'lotes.quadra_id', '=', 'quadras.id')
                ->leftJoin('unidade_imobiliarias', function ($join) {
                    $join->on('unidade_imobiliarias.lote_id', '=', 'lotes.id')
                        ->whereNull('unidade_imobiliarias.deleted_at');
                })
                ->where('lotes.tenant_id', $tenantId)
                ->whereNotNull('lotes.geo')
                ->whereNull('lotes.deleted_at')
                ->where(function ($q) use ($termo) {
                    $q->where('lotes.numero_lote', $termo)
                        ->orWhere('unidade_imobiliarias.inscricao_imobiliaria', $termo)
                        ->orWhere('unidade_imobiliarias.codigo_imovel_tributario', $termo)
                        // Busca apenas pelo nome da rua
                        ->orWhere('unidade_imobiliarias.logradouro_nome', 'ilike', "%{$termo}%")
                        // 🛑 A MÁGICA: Junta Rua e Número COM vírgula (Ex: "Rua X, 100")
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ', ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"])
                        // 🛑 A MÁGICA 2: Junta Rua e Número SEM vírgula (Ex: "Rua X 100")
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ' ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"]);
                })
                ->selectRaw('
                    lotes.id, 
                    lotes.numero_lote, 
                    quadras.name as quadra_nome, 
                    unidade_imobiliarias.codigo_imovel_tributario,
                    ST_AsGeoJSON(ST_Centroid(lotes.geo)) as centroide
                ')
                ->limit(20)
                ->get();

            $uniqueKeys = [];
            foreach ($lotes as $l) {
                $quadra = $l->quadra_nome ?? 'S/I';
                $cod = $l->codigo_imovel_tributario ?? 'S/C';
                $num = $l->numero_lote ?? 'S/N';

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
                    'tipo' => 'lote', // Identificador para o ícone no front
                    'titulo' => "Lote: $num | Quadra: $quadra",
                    'subtitulo' => "Cód Tributário: $cod",
                    'coords' => $coords
                ];
            }

            // --- 2. BUSCA DE LOGRADOUROS ---
            $logradouros = \Illuminate\Support\Facades\DB::table('logradouros')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->where('name', 'ilike', "%{$termo}%")
                ->selectRaw('
                    id, 
                    name, 
                    ST_AsGeoJSON(ST_PointOnSurface(geo::geometry)) as centroide
                ')
                ->limit(10)
                ->get();

            foreach ($logradouros as $log) {
                $centroide = json_decode($log->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords)
                    continue;

                $results[] = [
                    'id' => $log->id,
                    'tipo' => 'logradouro', // Identificador para o ícone no front
                    'titulo' => $log->name,
                    'subtitulo' => 'Logradouro (Rua/Avenida)',
                    'coords' => $coords
                ];
            }

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro DB: ' . $e->getMessage()], 500);
        }
    }

}