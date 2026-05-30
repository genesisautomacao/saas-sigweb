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

                            'area_geo' => isset($item->area_geo) ? (float) $item->area_geo : 0,
                            'main_facade_length' => isset($item->main_facade_length) ? (float) $item->main_facade_length : 0,

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
                $data = $buildFeatureCollection(Logradouro::query()->where('tenant_id', $tenantId)->get(), 'logradouros');
                break;

            case 'lotes':
                // 🛑 A MÁGICA: Buscamos os lotes e já trazemos a contagem de vulnerabilidades sociais!
                $lotes = Lote::query()->where('lotes.tenant_id', $tenantId)
                    ->select('lotes.id', 'lotes.sequential_id', 'lotes.numero_lote', 'lotes.area_geo', 'lotes.geo', 'lotes.code', 'lotes.status_cadastro')
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
                                'numero_lote' => $lote->numero_lote,
                                'sequential_id' => $lote->sequential_id,
                                'area_geo' => $lote->area_geo !== null ? round((float) $lote->area_geo, 2) : null,
                                // 👇 AS ETIQUETAS DE BI PARA O MAPA 👇
                                'social_risco' => (bool) $lote->tem_area_risco,
                                'social_beneficio' => (bool) $lote->tem_beneficio,
                                'social_pcd' => (bool) $lote->tem_pcd,
                                'status_cadastro' => $lote->status_cadastro ?? 'nao_visitado',
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

            case 'toponimias':
                $items = \App\Models\Toponimia::where('tenant_id', $tenantId)->get();
                $features = [];
                foreach ($items as $item) {
                    $features[] = [
                        'type'       => 'Feature',
                        'properties' => [
                            'id'    => $item->id,
                            'texto' => $item->texto,
                            'layer' => 'toponimias',
                            'estilo' => $item->estilo ?? [],
                        ],
                        'geometry' => [
                            'type'        => 'Point',
                            'coordinates' => [(float) $item->lon, (float) $item->lat],
                        ],
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
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
        // Modo público: o frontend do cidadão envia ?publico=1 — não retornar nome nem CPF do proprietário,
        // e também não permitir busca por esses campos sensíveis.
        $publico = $request->boolean('publico');

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
                ->where(function ($q) use ($termo, $publico) {
                    $q->where('lotes.numero_lote', $termo)
                        ->orWhere('unidade_imobiliarias.inscricao_imobiliaria', $termo)
                        ->orWhere('unidade_imobiliarias.codigo_imovel_tributario', $termo)
                        ->orWhere('unidade_imobiliarias.logradouro_nome', 'ilike', "%{$termo}%")
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ', ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"])
                        ->orWhereRaw("CONCAT(unidade_imobiliarias.logradouro_nome, ' ', unidade_imobiliarias.numero_imovel) ILIKE ?", ["%{$termo}%"])
                        // Busca por Nome do Edifício / Condomínio (não-sensível, permanece no modo público)
                        ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'nome_edificio' ILIKE ?", ["%{$termo}%"]);

                    // Campos sensíveis: só no modo logado (intranet)
                    if (! $publico) {
                        $q->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_name' ILIKE ?", ["%{$termo}%"])
                          ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_cpf' ILIKE ?", ["%{$termo}%"]);
                    }
                })
                ->selectRaw("
                    lotes.id, 
                    lotes.numero_lote, 
                    quadras.name as quadra_nome, 
                    unidade_imobiliarias.codigo_imovel_tributario,
                    unidade_imobiliarias.dados_tributarios->>'proprietario_name' as proprietario_nome,
                    unidade_imobiliarias.dados_tributarios->>'proprietario_cpf' as proprietario_cpf,
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

                // Montagem Inteligente do Subtítulo (oculta proprietário no modo público)
                $subtitulo = "Cód Tributário: $cod";
                if (! $publico && $l->proprietario_nome) {
                    $subtitulo .= " | Prop: " . $l->proprietario_nome . " (doc: " . $l->proprietario_cpf . ")";
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

            // --- 4. BUSCA DE LOTEAMENTOS ---
            $loteamentos = \Illuminate\Support\Facades\DB::table('loteamentos')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->where('name', 'ilike', "%{$termo}%")
                ->selectRaw("id, name, ST_AsGeoJSON(ST_PointOnSurface(geo::geometry)) as centroide")
                ->limit(5)
                ->get();

            foreach ($loteamentos as $lot) {
                $centroide = json_decode($lot->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;
                $results[] = [
                    'id'        => $lot->id,
                    'tipo'      => 'loteamento',
                    'titulo'    => $lot->name,
                    'subtitulo' => 'Loteamento',
                    'coords'    => $coords
                ];
            }

            // --- 5. BUSCA DE QUADRAS ---
            $quadras = \Illuminate\Support\Facades\DB::table('quadras')
                ->leftJoin('bairros', 'quadras.bairro_id', '=', 'bairros.id')
                ->leftJoin('loteamentos', 'quadras.loteamento_id', '=', 'loteamentos.id')
                ->where('quadras.tenant_id', $tenantId)
                ->whereNull('quadras.deleted_at')
                ->whereNotNull('quadras.geo')
                ->where('quadras.name', 'ilike', "%{$termo}%")
                ->selectRaw("
                    quadras.id,
                    quadras.name,
                    bairros.name as bairro_nome,
                    loteamentos.name as loteamento_nome,
                    ST_AsGeoJSON(ST_PointOnSurface(quadras.geo::geometry)) as centroide
                ")
                ->limit(5)
                ->get();

            foreach ($quadras as $quadra) {
                $centroide = json_decode($quadra->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;

                // Subtítulo: prioriza Bairro; se não houver, mostra Loteamento; senão "Quadra Urbana"
                $partes = [];
                if (!empty($quadra->bairro_nome)) {
                    $partes[] = 'Bairro ' . $quadra->bairro_nome;
                }
                if (!empty($quadra->loteamento_nome)) {
                    $partes[] = 'Loteamento ' . $quadra->loteamento_nome;
                }
                $subtitulo = !empty($partes) ? implode(' · ', $partes) : 'Quadra Urbana';

                $results[] = [
                    'id'        => $quadra->id,
                    'tipo'      => 'quadra',
                    'titulo'    => 'Quadra ' . $quadra->name,
                    'subtitulo' => $subtitulo,
                    'coords'    => $coords
                ];
            }

            // --- 6. BUSCA DE SETORES FISCAIS ---
            $setores = \Illuminate\Support\Facades\DB::table('setores_fiscais')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->where('nome', 'ilike', "%{$termo}%")
                ->selectRaw("id, nome, ST_AsGeoJSON(ST_PointOnSurface(geo::geometry)) as centroide")
                ->limit(5)
                ->get();

            foreach ($setores as $setor) {
                $centroide = json_decode($setor->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;
                $results[] = [
                    'id'       => $setor->id,
                    'tipo'     => 'setor',
                    'titulo'   => $setor->nome,
                    'subtitulo'=> 'Setor Fiscal',
                    'coords'   => $coords
                ];
            }

            // --- 7. BUSCA DE DISTRITOS (Perímetros Urbanos) ---
            $distritos = \Illuminate\Support\Facades\DB::table('perimetros_urbanos')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->where('name', 'ilike', "%{$termo}%")
                ->selectRaw("id, name, ST_AsGeoJSON(ST_PointOnSurface(geo::geometry)) as centroide")
                ->limit(5)
                ->get();

            foreach ($distritos as $distrito) {
                $centroide = json_decode($distrito->centroide);
                $coords = $centroide->coordinates ?? null;
                if (!$coords) continue;
                $results[] = [
                    'id'       => $distrito->id,
                    'tipo'     => 'distrito',
                    'titulo'   => $distrito->name,
                    'subtitulo'=> 'Distrito / Limites',
                    'coords'   => $coords
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
            'lotes',
            'edificacoes',
            'logradouros',
            'quadras',
            'bairros',
            'loteamentos',
            'rural_propriedades',
            'rural_estradas',
            'rural_pontes',
            'postes',
            'arvores',
            'cemiterios',
            'zonas',
            'rural_localidades',
            'perimetros_urbanos',
        ];

        try {
            $features = [];
            $layer = "";
            $infoLabel = "";
            $attr = null;

            // ========================================================================
            // ROTA 1: CRUZAMENTO ESPACIAL (Entre Camadas)
            // ========================================================================
            if ($tipoFiltro === 'espacial') {
                $targetLayer = $request->query('spatial_target_layer');
                $operator    = $request->query('spatial_operator');
                $refLayer    = $request->query('spatial_reference_layer');
                $refIds      = $request->query('spatial_reference_ids'); // 👈 AGORA BUSCAMOS O ARRAY

                // 🛡️ Fallback de segurança caso o JS ainda mande o id solto (para manter a compatibilidade)
                if (empty($refIds) && $request->query('spatial_reference_id')) {
                    $refIds = [$request->query('spatial_reference_id')];
                }

                if (!$targetLayer || !$operator || !$refLayer || empty($refIds)) {
                    return response()->json(['error' => 'Parâmetros incompletos para a query espacial GIS'], 400);
                }

                if (!in_array($targetLayer, $allowedTables) || !in_array($refLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                $validOperators = ['ST_Intersects', 'ST_Within'];
                $operator = in_array($operator, $validOperators) ? $operator : 'ST_Intersects';

                // 🪄 Convertemos o array do PHP em formato IN do SQL de forma segura para os parâmetros
                $placeholders = implode(',', array_fill(0, count($refIds), '?'));
                $params = array_merge([$tenantId], $refIds);

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
                    AND ref.id IN ($placeholders)
                    LIMIT 2500
                ";

                $results = \Illuminate\Support\Facades\DB::select($query, $params);
                $layer = $targetLayer;
                $infoLabel = "Cruzamento Espacial ({$operator} em {$refLayer})";
            }
            // ========================================================================
            // 🟢 ROTA 3: CRUZAMENTO POR DESENHO (Polígono / Retângulo)
            // ========================================================================
            elseif ($tipoFiltro === 'desenho') {
                $targetLayer = $request->query('draw_target_layer'); // O que buscar (ex: lotes)
                $drawnGeometry = $request->query('drawn_geometry');  // O GeoJSON do desenho do usuário

                // 👈 Puxa o operador que o JS vai nos mandar agora
                $drawOperator = $request->query('draw_spatial_operator', 'ST_Intersects');

                if (!$targetLayer || !$drawnGeometry) {
                    return response()->json(['error' => 'Parâmetros incompletos. Geometria de desenho ausente.'], 400);
                }

                if (!in_array($targetLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                // 🛡️ Segurança dupla: Garante que só rodam operadores conhecidos
                $validDrawOperators = ['ST_Intersects', 'ST_Within'];
                $drawOperator = in_array($drawOperator, $validDrawOperators) ? $drawOperator : 'ST_Intersects';

                // MÁGICA POSTGIS: Cruza a tabela alvo com a string GeoJSON e o operador dinâmico
                $query = "
                    SELECT 
                        target.*, 
                        ST_AsGeoJSON(target.geo) as geo_json,
                        'Área Desenhada (Mouse)' as searched_value
                    FROM {$targetLayer} target
                    WHERE target.tenant_id = ? 
                    AND target.deleted_at IS NULL
                    AND target.geo IS NOT NULL
                    AND {$drawOperator}(
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
            elseif ($tipoFiltro === 'atributo') {
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

                // ========================================================================
                // 📊 ROTA 4: TEMATIZAÇÃO POR INTERVALO DE CLASSES
                // ========================================================================
            } elseif ($tipoFiltro === 'intervalo') {
                $layer = $request->query('layer'); // 'lotes'
                $attr = $request->query('interval_attribute'); // 'area_geo'

                if (!$layer || !$attr) {
                    return response()->json(['error' => 'Parâmetros incompletos para o Intervalo'], 400);
                }

                if (!in_array($layer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida'], 403);
                }

                // Busca todos os itens da camada para calcular o gradiente de cores
                // Sem limite drástico para que o mapa temático fique completo
                // Colunas de identificação variam por camada
                $labelColMap = [
                    'lotes'              => 'numero_lote',
                    'edificacoes'        => 'code',
                    'quadras'            => 'code',
                    'bairros'            => 'name',
                    'loteamentos'        => 'name',
                    'zonas'              => 'sigla',
                    'rural_propriedades' => 'nome_propriedade',
                    'rural_estradas'     => 'nome',
                    'postes'             => 'sequential_id',
                    'arvores'            => 'sequential_id',
                    'setor_fiscais'      => 'code',
                    'perimetro_urbanos'  => 'code',
                ];
                $labelCol = $labelColMap[$layer] ?? 'id';

                $results = \Illuminate\Support\Facades\DB::table($layer)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo')
                    ->selectRaw('
                        id,
                        ' . $labelCol . ' as label_visual,
                        ' . $attr . ' as searched_value,
                        ST_AsGeoJSON(geo) as geo_json
                    ')
                    ->limit(5000)
                    ->get();

                $infoLabel = "Valor do Atributo";
            } else {
                return response()->json(['error' => 'Tipo de filtro desconhecido.'], 400);
            }

            // ========================================================================
            // FORMATAÇÃO DO RETORNO (Comum para as 3 rotas)
            // ========================================================================
            foreach ($results as $item) {
                if (!empty($item->geo_json)) {

                    $tituloVisual = $item->label_visual ?? ('ID: ' . $item->id);

                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $item->id,
                            'layer' => $layer,
                            'name' => $tituloVisual,
                            'titulo' => $tituloVisual,
                            'info' => "{$infoLabel}: " . ($item->searched_value ?? 'N/A'),
                            'searched_value' => isset($item->searched_value) ? (float) $item->searched_value : 0,
                        ],
                        'geometry' => json_decode($item->geo_json)
                    ];
                    // Expõe o atributo numérico pelo nome original (ex: area_geo) quando for tematização por intervalo
                    if ($attr) {
                        $features[count($features) - 1]['properties'][$attr] = $features[count($features) - 1]['properties']['searched_value'];
                    }
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

    // =========================================================================
    // ESTATÍSTICAS POR ÁREA DE INTERESSE
    // =========================================================================
    public function getEstatisticas(Request $request)
    {
        try {
            $tenantId   = $request->query('tenant_id');
            $areaType   = $request->query('area_type');   // bairros | setores_fiscais | perimetros_urbanos
            $areaId     = $request->query('area_id');     // id da área (ou 'all')
            $targetLayer= $request->query('target_layer'); // lotes | edificacoes | logradouros
            $groupField = $request->query('group_field'); // campo para agrupar
 
            // ----------------------------------------------------------------
            // 1. Mapa de configuração por camada
            // ----------------------------------------------------------------
            $layerConfig = [
                'lotes' => [
                    'table'       => 'lotes',
                    'label_col'   => 'numero_lote',
                    'group_fields'=> [
                        'zona_id'   => ['label' => 'Zona Urbana',      'join' => ['zonas', 'zona_id', 'id', 'sigla']],
                        'area_faixa'=> ['label' => 'Faixa de Área',    'computed' => true],
                    ],
                ],
                'edificacoes' => [
                    'table'       => 'edificacoes',
                    'label_col'   => 'code',
                    'group_fields'=> [
                        'tipo'                    => ['label' => 'Tipo de Uso'],
                        'tp_construcao'           => ['label' => 'Tipo de Construção'],
                        'estado_conservacao'      => ['label' => 'Estado de Conservação'],
                        'caracteristica_construcao'=> ['label' => 'Característica'],
                    ],
                ],
                'logradouros' => [
                    'table'       => 'logradouros',
                    'label_col'   => 'name',
                    'group_fields'=> [
                        'name' => ['label' => 'Nome do Logradouro'],
                    ],
                ],
            ];
 
            if (!isset($layerConfig[$targetLayer])) {
                return response()->json(['error' => 'Camada inválida.'], 400);
            }
 
            $cfg   = $layerConfig[$targetLayer];
            $table = $cfg['table'];
 
            // ----------------------------------------------------------------
            // 2. Resolve a geometria da(s) área(s) de interesse + centroide
            // ----------------------------------------------------------------
            $areaTableMap = [
                'bairros'             => ['table' => 'bairros',            'label' => 'name'],
                'setores_fiscais'     => ['table' => 'setores_fiscais',    'label' => 'nome'],
                'perimetros_urbanos'  => ['table' => 'perimetros_urbanos', 'label' => 'name'],
            ];
 
            if (!isset($areaTableMap[$areaType])) {
                return response()->json(['error' => 'Tipo de área inválido.'], 400);
            }
 
            $areaTable      = $areaTableMap[$areaType]['table'];
            $areaLabelCol   = $areaTableMap[$areaType]['label'];
 
            $areaQuery = \Illuminate\Support\Facades\DB::table($areaTable)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->select([
                    'id',
                    \Illuminate\Support\Facades\DB::raw("{$areaLabelCol} as area_label"),
                    \Illuminate\Support\Facades\DB::raw('ST_AsGeoJSON(ST_Centroid(geo::geometry)) as centroide'),
                    \Illuminate\Support\Facades\DB::raw('ST_AsGeoJSON(geo) as geo_json'),
                ]);
 
            if ($areaId !== 'all') {
                $areaQuery->where('id', $areaId);
            }
 
            $areas = $areaQuery->get();
 
            if ($areas->isEmpty()) {
                return response()->json(['error' => 'Área não encontrada.'], 404);
            }
 
            // ----------------------------------------------------------------
            // 3. Para cada área, faz o cruzamento e agrega
            // ----------------------------------------------------------------
            $resultAreas = [];
 
            foreach ($areas as $area) {
                // Monta a query base com cruzamento espacial
                $q = \Illuminate\Support\Facades\DB::table($table)
                    ->where("{$table}.tenant_id", $tenantId)
                    ->whereNull("{$table}.deleted_at")
                    ->whereNotNull("{$table}.geo")
                    ->whereRaw("ST_Intersects({$table}.geo, (
                        SELECT geo FROM {$areaTable} WHERE id = ? LIMIT 1
                    ))", [$area->id]);
 
                // Total geral
                $total = (clone $q)->count();
 
                if ($total === 0) {
                    continue;
                }
 
                // Agrupamento
                $grupos = [];
 
                if ($groupField === 'area_faixa' && $targetLayer === 'lotes') {
                    // Agrupamento especial por faixa de área
                    $faixas = [
                        'Até 125 m²'        => [0, 125],
                        '125 a 250 m²'      => [125, 250],
                        '250 a 500 m²'      => [250, 500],
                        '500 a 1000 m²'     => [500, 1000],
                        'Acima de 1000 m²'  => [1000, 999999999],
                    ];
                    foreach ($faixas as $label => [$min, $max]) {
                        $count = (clone $q)
                            ->where('area_geo', '>=', $min)
                            ->where('area_geo', '<', $max)
                            ->count();
                        if ($count > 0) {
                            $grupos[] = [
                                'valor'      => $label,
                                'quantidade' => $count,
                                'percentual' => round($count / $total * 100, 1),
                            ];
                        }
                    }
                } elseif (isset($cfg['group_fields'][$groupField]['join'])) {
                    // Join com tabela de referência (ex: zona_id → zonas.sigla)
                    [$joinTable, $fk, $pk, $labelJoin] = $cfg['group_fields'][$groupField]['join'];
                    $rows = (clone $q)
                        ->leftJoin($joinTable, "{$table}.{$fk}", '=', "{$joinTable}.{$pk}")
                        ->selectRaw("{$joinTable}.{$labelJoin} as grupo_valor, COUNT(*) as quantidade")
                        ->groupBy("{$joinTable}.{$labelJoin}")
                        ->orderByDesc('quantidade')
                        ->get();
 
                    foreach ($rows as $row) {
                        $grupos[] = [
                            'valor'      => $row->grupo_valor ?? 'Não informado',
                            'quantidade' => $row->quantidade,
                            'percentual' => round($row->quantidade / $total * 100, 1),
                        ];
                    }
                } else {
                    // Agrupamento direto por campo da tabela
                    $rows = (clone $q)
                        ->selectRaw("{$groupField} as grupo_valor, COUNT(*) as quantidade")
                        ->groupBy($groupField)
                        ->orderByDesc('quantidade')
                        ->get();
 
                    foreach ($rows as $row) {
                        $grupos[] = [
                            'valor'      => $row->grupo_valor ?? 'Não informado',
                            'quantidade' => $row->quantidade,
                            'percentual' => round($row->quantidade / $total * 100, 1),
                        ];
                    }
                }
 
                $centroide = json_decode($area->centroide);
 
                $resultAreas[] = [
                    'area_id'       => $area->id,
                    'area_label'    => $area->area_label,
                    'centroide'     => $centroide->coordinates ?? null,
                    'total'         => $total,
                    'grupos'        => $grupos,
                    'group_label'   => $cfg['group_fields'][$groupField]['label'] ?? $groupField,
                    'layer_label'   => match($targetLayer) {
                        'lotes'       => 'Lotes',
                        'edificacoes' => 'Edificações',
                        'logradouros' => 'Logradouros',
                        default       => $targetLayer,
                    },
                ];
            }
 
            return response()->json([
                'areas'        => $resultAreas,
                'area_type'    => $areaType,
                'target_layer' => $targetLayer,
                'group_field'  => $groupField,
            ]);
 
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro nas estatísticas: ' . $e->getMessage()], 500);
        }
    }
}