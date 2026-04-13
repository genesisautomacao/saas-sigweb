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
use App\Models\RuralLocalidade;
use App\Models\RuralPropriedade;
use App\Models\RuralEstrada;
use App\Models\RuralHidrografia;
use App\Models\RuralPonte;
use App\Models\RuralPontoInteresse;
use App\Models\PontoPanoramico;

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
                            'name' => $item->nome ?? $item->nome_propriedade ?? $item->nome_referencia ?? $item->name ?? 'S/N',

                            'categoria' => $item->categoria ?? null, // Pontos de Interesse
                            'tipo' => $item->tipo ?? null, // Localidades e Hidrografia
                            'tipo_pavimento' => $item->tipo_pavimento ?? null, // Estradas
                            'estado_conservacao' => $item->estado_conservacao ?? null, // Pontos e Pontes
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

            case 'loteamentos':
                $itens = \App\Models\Loteamento::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'loteamentos');
                break;

            case 'quadras':
                $data = $buildFeatureCollection(Quadra::where('tenant_id', $tenantId)->get(), 'quadras');
                break;

            case 'logradouros':
                $data = $buildFeatureCollection(Logradouro::where('tenant_id', $tenantId)->get(), 'logradouros');
                break;

            case 'lotes':
                // 🛑 A MÁGICA: Buscamos os lotes e já trazemos a contagem de vulnerabilidades sociais!
                $lotes = Lote::where('lotes.tenant_id', $tenantId)
                    ->select('lotes.id', 'lotes.numero_lote', 'lotes.geo', 'lotes.code')
                    ->withExists([
                        // Verifica se existe alguma Unidade no Lote que tenha um Cadastro Social em Área de Risco
                        'unidadesImobiliarias as tem_area_risco' => function ($query) {
                            $query->join('cadastros_sociais', 'unidade_imobiliarias.id', '=', 'cadastros_sociais.unidade_imobiliaria_id')
                                ->where('cadastros_sociais.em_area_de_risco', true)
                                ->whereNull('cadastros_sociais.deleted_at');
                        },
                        // Verifica se existe alguém recebendo benefício
                        'unidadesImobiliarias as tem_beneficio' => function ($query) {
                            $query->join('cadastros_sociais', 'unidade_imobiliarias.id', '=', 'cadastros_sociais.unidade_imobiliaria_id')
                                ->where('cadastros_sociais.recebe_beneficios', true)
                                ->whereNull('cadastros_sociais.deleted_at');
                        },
                        // Verifica PCD
                        'unidadesImobiliarias as tem_pcd' => function ($query) {
                            $query->join('cadastros_sociais', 'unidade_imobiliarias.id', '=', 'cadastros_sociais.unidade_imobiliaria_id')
                                ->where('cadastros_sociais.possui_membro_com_deficiencia', true)
                                ->whereNull('cadastros_sociais.deleted_at');
                        }
                    ])
                    ->get();

                // Customizamos o construtor do GeoJSON só para os lotes para injetar essas variáveis
                $features = [];
                foreach ($lotes as $lote) {
                    if (!empty($lote->geo_json) && !empty($lote->geo_json->coordinates)) {
                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id' => $lote->id,
                                'name' => $lote->numero_lote ?? 'S/N',
                                'codigo' => $lote->code,
                                'layer' => 'lotes',
                                // 👇 AS ETIQUETAS DE BI PARA O MAPA 👇
                                'social_risco' => (bool) $lote->tem_area_risco,
                                'social_beneficio' => (bool) $lote->tem_beneficio,
                                'social_pcd' => (bool) $lote->tem_pcd,
                            ],
                            'geometry' => $lote->geo_json
                        ];
                    }
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
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

            case 'pontos_panoramicos':
                $pontos = PontoPanoramico::where('tenant_id', $tenantId)
                    ->select('id', 'titulo as name', 'code', 'geo', 'image_path') // 'titulo as name' faz a ponte automática com o seu FeatureCollection
                    ->get();
                $data = $buildFeatureCollection($pontos, 'pontos_panoramicos');
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

            case 'setores_fiscais':
                $setores = \App\Models\SetorFiscal::where('tenant_id', $tenantId)
                    ->select('id', 'nome as name', 'geo') // REMOVIDO O 'code' DAQUI!
                    ->get();
                $data = $buildFeatureCollection($setores, 'setores_fiscais');
                break;

            case 'rural-localidades':
                $itens = RuralLocalidade::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-localidades');
                break;

            case 'rural-propriedades':
                $itens = RuralPropriedade::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-propriedades');
                break;

            case 'rural-estradas':
                $itens = RuralEstrada::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-estradas');
                break;

            case 'rural-hidrografias':
                $itens = RuralHidrografia::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-hidrografias');
                break;

            case 'rural-pontes':
                $itens = RuralPonte::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-pontes');
                break;

            case 'rural-pontos-interesse':
                $itens = RuralPontoInteresse::where('tenant_id', $tenantId)->get();
                $data = $buildFeatureCollection($itens, 'rural-pontos-interesse');
                break;

            case 'rural-propriedades':
                $itens = RuralPropriedade::where('tenant_id', $tenantId)
                    // Selecionamos apenas as colunas necessárias e apelidamos o nome para o padrão do JS
                    ->select('id', 'nome_propriedade as name', 'code', 'geo')
                    ->get();
                $data = $buildFeatureCollection($itens, 'rural-propriedades');
                break;

            case 'rural-estradas':
                $itens = RuralEstrada::where('tenant_id', $tenantId)
                    ->select('id', 'nome as name', 'code', 'geo', 'tipo_pavimento', 'condicao_trafego')
                    ->get();
                $data = $buildFeatureCollection($itens, 'rural-estradas');
                break;

            case 'rural-hidrografias':
                $itens = RuralHidrografia::where('tenant_id', $tenantId)
                    ->select('id', 'nome as name', 'tipo', 'code', 'geo') // <-- Add 'tipo'
                    ->get();
                $data = $buildFeatureCollection($itens, 'rural-hidrografias');
                break;

            case 'rural-pontes':
                $itens = RuralPonte::where('tenant_id', $tenantId)
                    ->select('id', 'nome_referencia as name', 'code', 'geo', 'estado_conservacao', 'material_construcao')
                    ->get();
                $data = $buildFeatureCollection($itens, 'rural-pontes');
                break;

            case 'rural-pontos-interesse':
                $itens = RuralPontoInteresse::where('tenant_id', $tenantId)
                    ->select('id', 'nome as name', 'categoria', 'code', 'geo')
                    ->get();
                $data = $buildFeatureCollection($itens, 'rural-pontos-interesse');
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

            // --- 1. BUSCA DE LOTES E UNIDADES (COM DADOS DO JSON) ---
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
                        ->orWhere('unidade_imobiliarias.logradouro_nome', 'ilike', "%{$termo}%")
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ', ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"])
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ' ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"])
                        // Busca por proprietário no JSON
                        ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_name' ILIKE ?", ["%{$termo}%"])
                        ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_cpf' ILIKE ?", ["%{$termo}%"])
                        // 🟢 EXIGÊNCIA EDITAL: Busca por Nome do Edifício / Condomínio dentro do JSON
                        ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'nome_edificio' ILIKE ?", ["%{$termo}%"]);
                })
                ->selectRaw("
                    lotes.id, 
                    lotes.numero_lote, 
                    quadras.name as quadra_nome, 
                    unidade_imobiliarias.codigo_imovel_tributario,
                    unidade_imobiliarias.dados_tributarios->>'proprietario_name' as proprietario_nome,
                    unidade_imobiliarias.dados_tributarios->>'nome_edificio' as nome_edificio,
                    ST_AsGeoJSON(ST_Centroid(lotes.geo)) as centroide
                ")
                ->limit(20)
                ->get();

            $uniqueKeys = [];
            foreach ($lotes as $l) {
                $quadra = $l->quadra_nome ?? 'S/I';
                $cod = $l->codigo_imovel_tributario ?? 'S/C';
                $num = $l->numero_lote ?? 'S/N';

                $uniqueKey = $l->id . '_' . $cod;
                if (in_array($uniqueKey, $uniqueKeys)) continue;
                $uniqueKeys[] = $uniqueKey;

                $centroide = json_decode($l->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;

                // Montagem Inteligente do Subtítulo
                $subtitulo = "Cód Tributário: $cod";
                if ($l->proprietario_nome) {
                    $subtitulo .= " | Prop: " . $l->proprietario_nome;
                }
                
                // 🟢 Se achou por causa do edifício, mostra com destaque para o usuário saber por que aquele lote apareceu!
                $tituloPrincipal = "Lote: $num | Quadra: $quadra";
                $tipoResult = 'lote'; // 🟢 Variável para controlar o ícone no JS

                // 🟢 Se achou por causa do edifício, muda o tipo!
                if (!empty($l->nome_edificio) && stripos($l->nome_edificio, $termo) !== false) {
                    $tituloPrincipal = $l->nome_edificio;
                    $subtitulo = "Condomínio / Edifício | " . $subtitulo;
                    $tipoResult = 'edificio'; // 🏢 Avisa o JS que isso é um prédio
                }

                $results[] = [
                    'id' => $l->id,
                    'tipo' => $tipoResult, // 🟢 Pode ser 'lote' ou 'edificio'
                    'titulo' => $tituloPrincipal,
                    'subtitulo' => $subtitulo,
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
                if (!$coords) continue;

                $results[] = [
                    'id' => $log->id,
                    'tipo' => 'logradouro',
                    'titulo' => $log->name,
                    'subtitulo' => 'Logradouro (Rua/Avenida)',
                    'coords' => $coords
                ];
            }

            // --- 3. BUSCA DE BAIRROS ---
            $bairros = \Illuminate\Support\Facades\DB::table('bairros')
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

            foreach ($bairros as $bairro) {
                $centroide = json_decode($bairro->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;

                $results[] = [
                    'id' => $bairro->id,
                    'tipo' => 'bairro',
                    'titulo' => $bairro->name,
                    'subtitulo' => 'Bairro / Região',
                    'coords' => $coords
                ];
            }

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro DB: ' . $e->getMessage()], 500);
        }
    }

    public function advancedSpatialQuery(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $tipoFiltro = $request->query('tipo_filtro', 'atributo'); // Identifica qual aba do form foi usada

        if (!$tenantId) {
            return response()->json(['error' => 'Parâmetros incompletos (Tenant ID não fornecido)'], 400);
        }

        // Segurança: Lista branca de tabelas estendida para incluir as novas camadas do cruzamento espacial
        $allowedTables = [
            'lotes', 'edificacoes', 'logradouros', 'quadras', 'bairros', 'loteamentos', 
            'rural_propriedades', 'rural_estradas', 'rural_pontes',
            'postes', 'arvores', 'cemiterios', 'zonas', 'rural_localidades'
        ];

        try {
            $features = [];
            $layer = "";
            $infoLabel = "";

            // ========================================================================
            // ROTA 1: CRUZAMENTO ESPACIAL (Entre Camadas)
            // ========================================================================
            if ($tipoFiltro === 'espacial') {
                $targetLayer = $request->query('spatial_target_layer'); 
                $operator    = $request->query('spatial_operator'); 
                $refLayer    = $request->query('spatial_reference_layer'); 
                $refId       = $request->query('spatial_reference_id'); 

                if (!$targetLayer || !$operator || !$refLayer || !$refId) {
                    return response()->json(['error' => 'Parâmetros incompletos para a query espacial GIS'], 400);
                }

                if (!in_array($targetLayer, $allowedTables) || !in_array($refLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                $validOperators = ['ST_Intersects', 'ST_Within'];
                $operator = in_array($operator, $validOperators) ? $operator : 'ST_Intersects';

                $query = "
                    SELECT 
                        target.*, 
                        ST_AsGeoJSON(target.geo) as geo_json,
                        ref.name as searched_value
                    FROM {$targetLayer} target
                    INNER JOIN {$refLayer} ref 
                        ON {$operator}(target.geo::geometry, ref.geo::geometry)
                    WHERE target.tenant_id = ? 
                    AND target.deleted_at IS NULL
                    AND target.geo IS NOT NULL
                    AND ref.id = ?
                    LIMIT 2500
                ";

                $results = \Illuminate\Support\Facades\DB::select($query, [$tenantId, $refId]);
                $layer = $targetLayer;
                $infoLabel = "Cruzamento Espacial ({$operator} em {$refLayer})";

            } 
            // ========================================================================
            // 🟢 ROTA 3: CRUZAMENTO POR DESENHO (Polígono / Retângulo)
            // ========================================================================
            elseif ($tipoFiltro === 'desenho') {
                $targetLayer = $request->query('draw_target_layer'); // O que buscar (ex: lotes)
                $drawnGeometry = $request->query('drawn_geometry');  // O GeoJSON do desenho do usuário

                if (!$targetLayer || !$drawnGeometry) {
                    return response()->json(['error' => 'Parâmetros incompletos. Geometria de desenho ausente.'], 400);
                }

                if (!in_array($targetLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                // MÁGICA POSTGIS: Cruza a tabela alvo com a string GeoJSON injetada via parâmetro seguro (?)
                $query = "
                    SELECT 
                        target.*, 
                        ST_AsGeoJSON(target.geo) as geo_json,
                        'Área Desenhada (Mouse)' as searched_value
                    FROM {$targetLayer} target
                    WHERE target.tenant_id = ? 
                    AND target.deleted_at IS NULL
                    AND target.geo IS NOT NULL
                    AND ST_Intersects(
                        target.geo::geometry, 
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
                    )
                    LIMIT 2000
                ";

                $results = \Illuminate\Support\Facades\DB::select($query, [$tenantId, $drawnGeometry]);
                $layer = $targetLayer;
                $infoLabel = "Consulta Geográfica (Desenho Livre)";

            }
            // ========================================================================
            // ROTA 2: FILTRO POR ATRIBUTO (O Tradicional)
            // ========================================================================
            else {
                $layer = $request->query('layer');
                $field = $request->query('field');
                $operator = $request->query('operator');
                $value = $request->query('value');

                if (!$layer || !$field || !$operator || $value === null) {
                    return response()->json(['error' => 'Parâmetros incompletos para a query de atributos'], 400);
                }

                if (!in_array($layer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida'], 403);
                }

                $queryBuilder = \Illuminate\Support\Facades\DB::table($layer)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo');

                if ($operator === 'LIKE') {
                    $queryBuilder->where($field, 'ilike', '%' . $value . '%');
                } else {
                    $queryBuilder->where($field, $operator, $value);
                }

                $results = $queryBuilder->selectRaw('
                    *, 
                    ST_AsGeoJSON(geo) as geo_json,
                    ' . $field . ' as searched_value
                ')->limit(2000)->get();

                $infoLabel = "Atributo ({$field})";
            }

            // ========================================================================
            // FORMATAÇÃO DO RETORNO (Comum para as 3 rotas)
            // ========================================================================
            foreach ($results as $item) {
                if (!empty($item->geo_json)) {

                    // 🧠 Tenta achar o nome de acordo com a tabela
                    $tituloVisual = $item->numero_lote ?? $item->nome ?? $item->name ?? $item->inscricao_imobiliaria ?? ('ID: ' . $item->id);

                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $item->id,
                            'layer' => $layer,
                            'name' => $tituloVisual,
                            'titulo' => $tituloVisual,
                            'info' => "{$infoLabel}: " . ($item->searched_value ?? 'N/A')
                        ],
                        'geometry' => json_decode($item->geo_json)
                    ];
                }
            }

            return response()->json([
                'type' => 'FeatureCollection',
                'features' => $features
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro na consulta: ' . $e->getMessage()], 500);
        }
    }
}
