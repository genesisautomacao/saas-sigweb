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
use App\Models\AreaReurb;
use Illuminate\Support\Facades\DB;

class MapDataController extends Controller
{
    public function getMapData(Request $request)
    {
        $tenantId = $request->input('tenant_id');
        $layer = $request->input('layer');

        if (!$tenantId || !$layer) {
            return response()->json(['error' => 'Parâmetros inválidos'], 400);
        }

        $buildFeatureCollection = function ($items, $layerName) {
            $features = [];

            // ⚡ PERF (2026-08-06): o accessor geo_json roda 1 QUERY POR REGISTRO no
            // banco, e este loop o acessava 3× por item — com 6.000+ lotes eram
            // ~20 mil queries e >10 s de resposta. A conversão ST_AsGeoJSON agora
            // sai numa ÚNICA query por camada (mapa id → geometria decodificada).
            $geoPorId = collect();
            $primeiro = collect($items)->first();
            if ($primeiro instanceof \Illuminate\Database\Eloquent\Model) {
                $ids = collect($items)->pluck('id')->filter();
                if ($ids->isNotEmpty()) {
                    $geoPorId = DB::table($primeiro->getTable())
                        ->whereIn('id', $ids)
                        ->whereNotNull('geo')
                        ->selectRaw('id, ST_AsGeoJSON(geo, 6) AS gj')
                        ->pluck('gj', 'id')
                        ->map(fn ($gj) => json_decode($gj));
                }
            }

            foreach ($items as $item) {
                $geom = $geoPorId[$item->id]
                    ?? ($primeiro instanceof \Illuminate\Database\Eloquent\Model ? null : ($item->geo_json ?? null));

                if ($geom && !empty($geom->coordinates)) {

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
                            // null | 'solicitacao' (só chamado) | 'os_aberta' (OS gerada) — cores no mapa
                            'status_manutencao' => ($item->tem_os_aberta ?? false)
                                ? 'os_aberta'
                                : (($item->tem_chamado ?? false) ? 'solicitacao' : null),
                            'name' => $item->nome ?? $item->nome_propriedade ?? $item->nome_referencia ?? $item->name ?? 'S/N',

                            'categoria' => $item->categoria ?? null, // Pontos de Interesse
                            // Refatoração PoC Tangará: nas camadas do imobiliário estes
                            // atributos viraram campos customizados — fallback no JSONB
                            // mantém as props do engine/tooltips sem mexer no JS.
                            'tipo' => $item->tipo ?? ($item->dados_customizados['tipo_edificacao'] ?? null), // Localidades, Hidrografia e Edificações
                            'tipo_pavimento' => $item->tipo_pavimento ?? null, // Estradas
                            'tipo_pavimentacao' => $item->tipo_pavimentacao ?? ($item->dados_customizados['tipo_pavimentacao'] ?? null), // Seções de Logradouro
                            'estado_conservacao' => $item->estado_conservacao ?? ($item->dados_customizados['estado_conservacao'] ?? null), // Pontos, Pontes e Meio-fio
                            'material' => $item->material ?? ($item->dados_customizados['material'] ?? null), // Meio-fio
                            'lado' => $item->lado ?? null, // Seções de Logradouro (item 44)
                            'codigo' => $item->codigo ?? null, // Código municipal (itens 44-49) — rótulos/labels
                            'extensao_geo' => isset($item->extensao_geo) ? (float) $item->extensao_geo : null, // Meio-fio
                        ],
                        'geometry' => $geom
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

            case 'patrimonio_publicos':
                $items = \App\Models\PatrimonioPublico::where('tenant_id', $tenantId)
                    ->with('tipo')
                    ->select('id', 'sequential_id', 'name', 'geo', 'tipo_patrimonio_id', 'address')
                    ->get();
                // ⚡ PERF: geometrias numa query só (o accessor geo_json é 1 query/registro)
                $geosPat = DB::table('patrimonio_publicos')->whereIn('id', $items->pluck('id'))
                    ->whereNotNull('geo')->selectRaw('id, ST_AsGeoJSON(geo, 6) AS gj')->pluck('gj', 'id');
                $features = [];
                foreach ($items as $item) {
                    $geom = isset($geosPat[$item->id]) ? json_decode($geosPat[$item->id]) : null;
                    if ($geom && !empty($geom->coordinates)) {
                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id'            => $item->id,
                                'name'          => $item->name,
                                'sequential_id' => $item->sequential_id,
                                'tipo'          => $item->tipo?->name,
                                'address'       => $item->address,
                                'layer'         => 'patrimonio_publicos',
                            ],
                            'geometry' => $geom,
                        ];
                    }
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'areas_reurb':
                $areas = AreaReurb::where('tenant_id', $tenantId)->get();
                // ⚡ PERF: geometrias numa query só (o accessor geo_json é 1 query/registro)
                $geosReurb = DB::table('areas_reurb')->whereIn('id', $areas->pluck('id'))
                    ->whereNotNull('geo')->selectRaw('id, ST_AsGeoJSON(geo, 6) AS gj')->pluck('gj', 'id');
                $features = [];
                foreach ($areas as $area) {
                    $geom = isset($geosReurb[$area->id]) ? json_decode($geosReurb[$area->id]) : null;
                    if ($geom) {
                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id' => $area->id,
                                'name' => $area->nome,
                                'tipo_reurb' => $area->tipo_reurb,
                                'status' => $area->status,
                                'sequential_id' => $area->sequential_id,
                                'area_geo' => $area->area_geo,
                                'layer' => 'areas_reurb',
                            ],
                            'geometry' => $geom,
                        ];
                    }
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
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

            case 'meio_fios':
                $data = $buildFeatureCollection(
                    \App\Models\MeioFio::query()->where('tenant_id', $tenantId)->get(),
                    'meio_fios'
                );
                break;

            case 'secoes_logradouro':
                $data = $buildFeatureCollection(
                    \App\Models\SecaoLogradouro::query()->where('tenant_id', $tenantId)->get(),
                    'secoes_logradouro'
                );
                break;

            case 'lotes':
                // 🛑 A MÁGICA: Buscamos os lotes e já trazemos a contagem de vulnerabilidades sociais!
                $lotes = Lote::query()->where('lotes.tenant_id', $tenantId)
                    ->select('lotes.id', 'lotes.sequential_id', 'lotes.numero_lote', 'lotes.numero_logradouro', 'lotes.area_geo', 'lotes.code', 'lotes.status_cadastro')
                    // ⚡ PERF (2026-08-06): geometria convertida NA MESMA query — o accessor
                    // geo_json fazia 1 query por lote (×3 acessos no loop = ~20 mil queries
                    // e >10 s num município de 6.000 lotes).
                    ->selectRaw('ST_AsGeoJSON(lotes.geo, 6) AS gj')
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

                // Processos digitais em andamento por lote — para coloração temática no mapa
                $processosPorLote = DB::table('processos_digitais')
                    ->join('bpmn_etapas', 'bpmn_etapas.id', '=', 'processos_digitais.etapa_atual_id')
                    ->where('processos_digitais.tenant_id', $tenantId)
                    // Motor híbrido: qualquer processo em trânsito (com o setor OU com o cidadão)
                    ->whereNotIn('processos_digitais.status', ['rascunho', 'concluido', 'cancelado'])
                    ->whereNull('processos_digitais.deleted_at')
                    ->whereNotNull('processos_digitais.lote_id')
                    ->select(
                        'processos_digitais.lote_id',
                        'bpmn_etapas.cor_mapa as processo_etapa_cor',
                        'bpmn_etapas.nome as processo_etapa_nome',
                        'processos_digitais.codigo_processo'
                    )
                    ->orderBy('processos_digitais.id')
                    ->get()
                    ->keyBy('lote_id');

                // Customizamos o construtor do GeoJSON só para os lotes para injetar essas variáveis
                $features = [];
                foreach ($lotes as $lote) {
                    $geomLote = $lote->gj ? json_decode($lote->gj) : null;
                    if ($geomLote && !empty($geomLote->coordinates)) {
                        $proc = $processosPorLote[$lote->id] ?? null;
                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id' => $lote->id,
                                'name' => $lote->numero_lote ?? 'S/N',
                                'codigo' => $lote->code,
                                'layer' => 'lotes',
                                'numero_lote' => $lote->numero_lote,
                                'numero_logradouro' => $lote->numero_logradouro,
                                'sequential_id' => $lote->sequential_id,
                                'area_geo' => $lote->area_geo !== null ? round((float) $lote->area_geo, 2) : null,
                                // 👇 AS ETIQUETAS DE BI PARA O MAPA 👇
                                'social_risco' => (bool) $lote->tem_area_risco,
                                'social_beneficio' => (bool) $lote->tem_beneficio,
                                'social_pcd' => (bool) $lote->tem_pcd,
                                'status_cadastro' => $lote->status_cadastro ?? 'nao_visitado',
                                // 👇 PROCESSOS DIGITAIS — etapa atual com cor definida no BpmnFluxo
                                'processo_etapa_cor' => $proc?->processo_etapa_cor,
                                'processo_etapa_nome' => $proc?->processo_etapa_nome,
                                'codigo_processo' => $proc?->codigo_processo,
                            ],
                            'geometry' => $geomLote
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
                        },
                        'solicitacoesManutencao as tem_os_aberta' => function ($query) {
                            $query->where('status', 'aprovada_os');
                        },
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
                        },
                        'solicitacoesManutencao as tem_os_aberta' => function ($query) {
                            $query->where('status', 'aprovada_os');
                        },
                    ])
                    ->get();
                $data = $buildFeatureCollection($arvores, 'arvores');
                break;

            case 'pontos_panoramicos':
                // ⚡ Imageamento 360 em massa (46 mil pontos): payload ENXUTO —
                // só id/name/layer por feição — e SEM hidratar milhares de models
                // Eloquent (query direta + build manual). Com o gzip do middleware,
                // a camada inteira desce em ~600KB em vez de ~20MB.
                $rows = DB::table('pontos_panoramicos')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo')
                    ->selectRaw('id, titulo, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'name' => $row->titulo,
                            'layer' => 'pontos_panoramicos',
                        ],
                        'geometry' => json_decode($row->gj),
                    ];
                }

                $data = ['type' => 'FeatureCollection', 'features' => $features];
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

            case 'processos':
                // Lotes que têm processo digital (em andamento ou concluído), coloridos pela cor
                // do FLUXO. Self-contained: não depende da camada 'lotes' estar ligada.
                $rows = DB::table('processos_digitais as p')
                    ->join('lotes as l', 'l.id', '=', 'p.lote_id')
                    ->leftJoin('bpmn_fluxos as f', 'f.id', '=', 'p.bpmn_fluxo_id')
                    ->leftJoin('bpmn_etapas as e', 'e.id', '=', 'p.etapa_atual_id')
                    ->where('p.tenant_id', $tenantId)
                    ->whereIn('p.status', ['em_andamento', 'concluido'])
                    ->whereNull('p.deleted_at')
                    ->whereNotNull('p.lote_id')
                    ->whereNotNull('l.geo')
                    ->orderBy('p.id')
                    ->selectRaw("p.id, p.codigo_processo, p.bpmn_fluxo_id, p.status,
                        l.numero_lote,
                        f.nome as fluxo_nome, COALESCE(f.cor, '#3b82f6') as fluxo_cor,
                        e.nome as etapa_nome,
                        ST_AsGeoJSON(l.geo, 6) as geo_json")
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = $row->geo_json ? json_decode($row->geo_json) : null;
                    if (!$geom || empty($geom->coordinates)) {
                        continue;
                    }
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id'              => $row->id,
                            'layer'           => 'processos',
                            'codigo_processo' => $row->codigo_processo,
                            'bpmn_fluxo_id'   => $row->bpmn_fluxo_id,
                            'fluxo_nome'      => $row->fluxo_nome,
                            'fluxo_cor'       => $row->fluxo_cor ?: '#3b82f6',
                            'etapa_nome'      => $row->etapa_nome,
                            'status'          => $row->status,
                            'is_concluido'    => $row->status === 'concluido',
                            'numero_lote'     => $row->numero_lote,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'coleta':
                // Camada de COLETA DE DADOS — lotes coloridos por status_cadastro. Self-contained
                // (não depende da camada 'lotes' estar carregada). O LEFT JOIN da coleta vigente
                // traz o PONTO GPS da inconformidade (marcado pelo app), que vira um PIN no mapa.
                $campanhaColeta = \App\Models\ColetaImobiliaria::CAMPANHA_PADRAO;

                $rows = DB::table('lotes')
                    ->leftJoin('coleta_imobiliaria as ci', function ($join) use ($campanhaColeta) {
                        $join->on('ci.coletavel_id', '=', 'lotes.id')
                            ->where('ci.coletavel_type', '=', 'App\\Models\\Lote')
                            ->where('ci.campanha', '=', $campanhaColeta)
                            ->whereNull('ci.deleted_at');
                    })
                    ->where('lotes.tenant_id', $tenantId)
                    ->whereNull('lotes.deleted_at')
                    ->whereNotNull('lotes.geo')
                    ->selectRaw("lotes.id, lotes.numero_lote,
                        COALESCE(lotes.status_cadastro, 'nao_visitado') as status_cadastro,
                        ci.inconformidade_descricao, ci.inconformidade_ponto,
                        ST_AsGeoJSON(lotes.geo, 6) as geo_json")
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = $row->geo_json ? json_decode($row->geo_json) : null;
                    if (!$geom || empty($geom->coordinates)) {
                        continue;
                    }
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id'              => $row->id,
                            'layer'           => 'coleta',
                            'numero_lote'     => $row->numero_lote,
                            'status_cadastro' => $row->status_cadastro,
                        ],
                        'geometry' => $geom,
                    ];

                    // PIN da inconformidade — só quando o app capturou o ponto GPS
                    // (o coletor marca ONDE viu o problema, nem sempre o centro do lote)
                    $ponto = $row->inconformidade_ponto ? json_decode($row->inconformidade_ponto, true) : null;
                    if ($row->status_cadastro === 'inconformidade' && isset($ponto['lat'], $ponto['lon'])) {
                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id'          => $row->id,
                                'layer'       => 'coleta',
                                'tipo'        => 'inconformidade_pin',
                                'numero_lote' => $row->numero_lote,
                                'descricao'   => $row->inconformidade_descricao,
                                'status_cadastro' => 'inconformidade',
                            ],
                            'geometry' => [
                                'type' => 'Point',
                                'coordinates' => [(float) $ponto['lon'], (float) $ponto['lat']],
                            ],
                        ];
                    }
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'chamados':
                // App de Chamados — pontos coloridos pela cor da CATEGORIA. Self-contained.
                $rows = DB::table('chamados as c')
                    ->leftJoin('categorias_chamado as cat', 'cat.id', '=', 'c.categoria_chamado_id')
                    ->leftJoin('fases_chamado as fa', 'fa.id', '=', 'c.fase_atual_id')
                    ->where('c.tenant_id', $tenantId)
                    ->whereNull('c.deleted_at')
                    ->whereNotNull('c.geo')
                    ->orderBy('c.id')
                    ->selectRaw("c.id, c.protocolo, c.status, c.solicitante_nome,
                        cat.nome as categoria_nome, COALESCE(cat.cor, '#3b82f6') as categoria_cor,
                        fa.nome as fase_nome, COALESCE(fa.cor, '#6b7280') as fase_cor,
                        ST_AsGeoJSON(c.geo, 6) as geo_json")
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = $row->geo_json ? json_decode($row->geo_json) : null;
                    if (!$geom || empty($geom->coordinates)) {
                        continue;
                    }
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id'              => $row->id,
                            'layer'           => 'chamados',
                            'protocolo'       => $row->protocolo,
                            'categoria_nome'  => $row->categoria_nome,
                            'categoria_cor'   => $row->categoria_cor ?: '#3b82f6',
                            'fase_nome'        => $row->fase_nome,
                            'fase_cor'         => $row->fase_cor ?: '#6b7280',
                            'status'          => $row->status,
                            'solicitante'     => $row->solicitante_nome,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            // ── MOBILIDADE URBANA (docs/piuma.txt, Onda 2) — camadas self-contained,
            //    query direta com ST_AsGeoJSON (nunca accessor em loop). Toggles por
            //    tipo/categoria são filtro de CLIENTE (dados pequenos: ≤ ~830 feats).
            case 'mob_trechos':
                $rows = DB::table('mob_trechos')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw("id, sequential_id, via_id, azimute, extensao_geo, observacao,
                        tipologia_da_via, tipo_de_pavimentacao, estado_conservacao_pavimentacao,
                        classe_faixa_rodagem, dimensionamento_da_via, dados_customizados,
                        ST_AsGeoJSON(geo, 6) AS gj")
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_trechos',
                            'name' => 'Trecho #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'via_id' => $row->via_id,
                            // direção do MAPEAMENTO (calçada direita/esquerda) — não é sentido de tráfego
                            'azimute' => $row->azimute !== null ? (float) $row->azimute : null,
                            'extensao_geo' => $row->extensao_geo !== null ? (float) $row->extensao_geo : null,
                            'tipologia_da_via' => $row->tipologia_da_via,
                            'tipo_de_pavimentacao' => $row->tipo_de_pavimentacao,
                            'estado_conservacao_pavimentacao' => $row->estado_conservacao_pavimentacao,
                            'classe_faixa_rodagem' => $row->classe_faixa_rodagem,
                            'dimensionamento_da_via' => $row->dimensionamento_da_via,
                            'observacao' => $row->observacao,
                            // "Colorir por" também tematiza pelos campos do kit (calçadas etc.)
                            'custom' => $row->dados_customizados ? json_decode($row->dados_customizados) : null,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_sinalizacoes':
                // Cor/ícone/nome vêm do CATÁLOGO (decisão 6.1 do piuma.txt)
                $rows = DB::table('mob_sinalizacoes as s')
                    ->leftJoin('mob_tipos_sinalizacao as ts', 'ts.id', '=', 's.tipo_sinalizacao_id')
                    ->where('s.tenant_id', $tenantId)->whereNull('s.deleted_at')->whereNotNull('s.geo')
                    ->orderBy('s.id')
                    ->selectRaw("s.id, s.sequential_id, s.descricao_original,
                        ts.name as tipo_nome, ts.tipo as tipo_vh,
                        COALESCE(ts.cor, '#9CA3AF') as cor, ts.icone,
                        ST_AsGeoJSON(s.geo, 6) AS gj")
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_sinalizacoes',
                            'name' => $row->tipo_nome ?? 'A Classificar',
                            'sequential_id' => $row->sequential_id,
                            'tipo_vh' => $row->tipo_vh ?? 'vertical',
                            'cor' => $row->cor,
                            'icone' => $row->icone ? asset('storage/'.$row->icone) : null,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_pontos_interesse':
                $rows = DB::table('mob_pontos_interesse')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, categoria, name, numero, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_pontos_interesse',
                            'name' => $row->name ?: 'POI #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'categoria' => $row->categoria,
                            'numero' => $row->numero,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_cameras':
                // Monitoramento em tempo real (docs/piuma.txt, Onda 5) — só posição/metadados; o vídeo
                // é carregado no modal (player) ao clicar, nunca em massa.
                $rows = DB::table('mob_cameras')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, nome, tipo, provedor, azimute_visada, ativo, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_cameras',
                            'name' => $row->nome ?: 'Câmera #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'tipo' => $row->tipo,
                            'provedor' => $row->provedor,
                            'azimute_visada' => $row->azimute_visada !== null ? (float) $row->azimute_visada : null,
                            'ativo' => (bool) $row->ativo,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_vias':
                // Vias Urbanas (piuma.txt Onda 6) = o FLUXO: sentido + direção (ordem dos vértices).
                $rows = DB::table('mob_vias')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, nome, sentido, azimute, extensao_geo, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (! $geom || empty($geom->coordinates)) {
                        continue;
                    }
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_vias',
                            'name' => $row->nome ?: 'Via #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'nome' => $row->nome,
                            'sentido' => $row->sentido,
                            'azimute' => $row->azimute !== null ? (float) $row->azimute : null,
                            'extensao_geo' => $row->extensao_geo !== null ? (float) $row->extensao_geo : null,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_eixos':
                $rows = DB::table('mob_eixos')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, tipo, name, extensao_geo, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_eixos',
                            'name' => $row->name ?: 'Eixo #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'tipo' => $row->tipo,
                            'extensao_geo' => $row->extensao_geo !== null ? (float) $row->extensao_geo : null,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_zonas':
                $rows = DB::table('mob_zonas')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, tipo, name, codigo, situacao, origens, destinos, area_geo, populacao, densidade, renda, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_zonas',
                            'name' => $row->name ?: 'Zona #'.$row->sequential_id,
                            'sequential_id' => $row->sequential_id,
                            'tipo' => $row->tipo,
                            'codigo' => $row->codigo,
                            'situacao' => $row->situacao,
                            'origens' => $row->origens !== null ? (float) $row->origens : null,
                            'destinos' => $row->destinos !== null ? (float) $row->destinos : null,
                            'area_geo' => $row->area_geo !== null ? (float) $row->area_geo : null,
                            // Demografia do setor (2026-09-04) — temas do "Colorir setores por"
                            'populacao' => $row->populacao !== null ? (int) $row->populacao : null,
                            'densidade' => $row->densidade !== null ? (float) $row->densidade : null,
                            'renda' => $row->renda !== null ? (float) $row->renda : null,
                        ],
                        'geometry' => $geom,
                    ];
                }
                $data = ['type' => 'FeatureCollection', 'features' => $features];
                break;

            case 'mob_fluxos':
                // Rótulo do mapa = % do total GERAL de deslocamentos (decisão 2026-09-04).
                // O total inclui os fluxos intrazonais sem geometria — são viagens reais.
                $totalFluxos = (int) DB::table('mob_fluxos')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')
                    ->sum('valores');

                // Cor por zona de DESTINO (derivada da geometria — MobFluxo::distribuicao)
                $distFluxos = \App\Models\MobFluxo::distribuicao($tenantId);

                $rows = DB::table('mob_fluxos')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('geo')
                    ->orderBy('id')
                    ->selectRaw('id, sequential_id, origem_regiao, origem_zona, destino_zona, valores, ST_AsGeoJSON(geo, 6) AS gj')
                    ->get();

                $features = [];
                foreach ($rows as $row) {
                    $geom = json_decode($row->gj);
                    if (!$geom || empty($geom->coordinates)) continue;
                    $slugDestino = \App\Models\MobFluxo::slugZona($row->destino_zona);
                    $origem = $row->origem_zona ?: (\App\Models\MobFluxo::REGIOES[$row->origem_regiao] ?? ucfirst((string) $row->origem_regiao));
                    $destino = $row->destino_zona ?: 'Sem zona';
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $row->id,
                            'layer' => 'mob_fluxos',
                            'name' => $origem.' → '.$destino,
                            'sequential_id' => $row->sequential_id,
                            'origem_regiao' => $row->origem_regiao,
                            'origem_zona' => $row->origem_zona,
                            'destino_zona' => $row->destino_zona,
                            'destino_slug' => $slugDestino,
                            'cor' => $distFluxos['destinos'][$slugDestino]['cor'] ?? \App\Models\MobFluxo::COR_SEM_ZONA,
                            'valores' => (int) $row->valores,
                            'percentual' => $totalFluxos > 0 ? round($row->valores * 100 / $totalFluxos, 1) : 0,
                            'total' => $totalFluxos,
                        ],
                        'geometry' => $geom,
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
        $tenantId = $request->input('tenant_id');
        $termo = (string) $request->input('termo');
        // Modo público: o frontend do cidadão envia ?publico=1 — não retornar nome nem CPF do proprietário,
        // e também não permitir busca por esses campos sensíveis.
        $publico = $request->boolean('publico');

        if (!$tenantId || strlen($termo) < 1) {
            return response()->json([]);
        }

        try {
            $results = [];

            // --- 1. BUSCA DE LOTES E UNIDADES (COM DADOS DO JSON) ---
            // Refatoração PoC Tangará:
            //  - endereço vive SÓ na unidade (as cópias do lote foram removidas);
            //  - nome_edificio virou coluna (indexada por trigram);
            //  - proprietário busca em PESSOAS via proprietario_id (funciona também em
            //    município SEM integração tributária), com fallback no JSON legado.
            $lotes = \Illuminate\Support\Facades\DB::table('lotes')
                ->leftJoin('quadras', 'lotes.quadra_id', '=', 'quadras.id')
                ->leftJoin('unidade_imobiliarias', function ($join) {
                    $join->on('unidade_imobiliarias.lote_id', '=', 'lotes.id')
                        ->whereNull('unidade_imobiliarias.deleted_at');
                })
                ->leftJoin('pessoas', function ($join) {
                    $join->on('pessoas.id', '=', 'unidade_imobiliarias.proprietario_id')
                        ->whereNull('pessoas.deleted_at');
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
                        // Nº predial do lote (número NOVO da numeração predial)
                        ->orWhere('lotes.numero_logradouro', $termo)
                        // Nome do Edifício / Condomínio (não-sensível, permanece no modo público)
                        ->orWhere('unidade_imobiliarias.nome_edificio', 'ilike', "%{$termo}%");

                    // Campos sensíveis: só no modo logado (intranet)
                    if (! $publico) {
                        $q->orWhere('pessoas.name', 'ilike', "%{$termo}%")
                          ->orWhere('pessoas.cpf', 'ilike', "%{$termo}%")
                          ->orWhere('pessoas.cnpj', 'ilike', "%{$termo}%")
                          // Fallback: unidade sem Pessoa vinculada, mas com JSON do tributário
                          ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_name' ILIKE ?", ["%{$termo}%"])
                          ->orWhereRaw("unidade_imobiliarias.dados_tributarios->>'proprietario_cpf' ILIKE ?", ["%{$termo}%"]);
                    }
                })
                ->selectRaw("
                    lotes.id,
                    lotes.numero_lote,
                    quadras.name as quadra_nome,
                    unidade_imobiliarias.codigo_imovel_tributario,
                    COALESCE(pessoas.name, unidade_imobiliarias.dados_tributarios->>'proprietario_name') as proprietario_nome,
                    COALESCE(pessoas.cpf, pessoas.cnpj, unidade_imobiliarias.dados_tributarios->>'proprietario_cpf') as proprietario_cpf,
                    unidade_imobiliarias.nome_edificio,
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
        $tenantId = $request->input('tenant_id');
        $tipoFiltro = $request->input('tipo_filtro', 'atributo'); // Identifica qual aba do form foi usada

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
            'meio_fios',
            'secoes_logradouro',
        ];

        /**
         * Onda 4 (PoC Tangará) — resolve um campo do filtro para expressão SQL SEGURA.
         *
         * Aceita coluna real (whitelist pelo schema — antes o $field ia interpolado cru
         * no selectRaw, uma injeção de SQL) ou campo customizado do município no formato
         * `custom:slug` (item 76① — expressões de consulta), que vira acesso ao JSONB
         * `dados_customizados` com cast numérico quando o campo é do tipo número.
         * Retorna null para qualquer coisa fora da whitelist.
         */
        $resolveCampo = function (string $tabela, ?string $campo, string $alias = '') use ($tenantId): ?array {
            if (blank($campo)) {
                return null;
            }

            if (str_starts_with($campo, 'custom:')) {
                $slug = substr($campo, 7);

                if (! preg_match('/^[a-z0-9_]+$/', $slug)) {
                    return null;
                }

                $entidade = array_search($tabela, \App\Services\Coleta\CampoCustomizadoService::ENTIDADE_TABELA, true);

                if ($entidade === false) {
                    return null;
                }

                $def = \App\Services\Coleta\CampoCustomizadoService::definicoes($entidade, (int) $tenantId)
                    ->firstWhere('slug', $slug);

                if (! $def) {
                    return null;
                }

                $expr = "{$alias}dados_customizados->>'{$slug}'";

                return [
                    'expr' => $def->tipo === 'numero' ? "({$expr})::numeric" : $expr,
                    'numerico' => $def->tipo === 'numero',
                    'label' => $def->label,
                ];
            }

            if (! preg_match('/^[a-z0-9_]+$/', $campo)
                || ! in_array($campo, \Illuminate\Support\Facades\Schema::getColumnListing($tabela), true)) {
                return null;
            }

            return ['expr' => $alias.$campo, 'numerico' => false, 'label' => $campo];
        };

        $operadoresValidos = ['=', '!=', '>', '<', '>=', '<=', 'LIKE'];

        /**
         * T1.8 (item 2.1-1) — condição de atributo OPCIONAL aplicada junto do cruzamento
         * espacial/desenho ("lotes na zona X COM área > Y" numa só consulta).
         * Retorna [clausulaSql, bindings, rotulo] ou [null, [], null] quando não usada.
         */
        $condicaoAtributo = function (string $tabela, string $alias = '') use ($request, $resolveCampo, $operadoresValidos): array {
            $campo = $request->input('attr_field');
            $op = $request->input('attr_operator');
            $valor = $request->input('attr_value');

            if (blank($campo) || blank($op) || $valor === null || $valor === '') {
                return [null, [], null];
            }

            $info = $resolveCampo($tabela, $campo, $alias);

            if (! $info || ! in_array($op, $operadoresValidos, true)) {
                return [null, [], null];
            }

            if ($op === 'LIKE') {
                return ["AND {$info['expr']}::text ILIKE ?", ['%'.$valor.'%'], "{$info['label']} contém \"{$valor}\""];
            }

            $binding = $info['numerico'] && is_numeric($valor) ? (float) $valor : $valor;

            return ["AND {$info['expr']} {$op} ?", [$binding], "{$info['label']} {$op} {$valor}"];
        };

        try {
            $features = [];
            $layer = "";
            $infoLabel = "";
            $attr = null;

            // ========================================================================
            // ROTA 1: CRUZAMENTO ESPACIAL (Entre Camadas)
            // ========================================================================
            if ($tipoFiltro === 'espacial') {
                $targetLayer = $request->input('spatial_target_layer');
                $operator    = $request->input('spatial_operator');
                $refLayer    = $request->input('spatial_reference_layer');
                $refIds      = $request->input('spatial_reference_ids'); // 👈 AGORA BUSCAMOS O ARRAY

                // 🛡️ Fallback de segurança caso o JS ainda mande o id solto (para manter a compatibilidade)
                if (empty($refIds) && $request->input('spatial_reference_id')) {
                    $refIds = [$request->input('spatial_reference_id')];
                }

                if (!$targetLayer || !$operator || !$refLayer || empty($refIds)) {
                    return response()->json(['error' => 'Parâmetros incompletos para a query espacial GIS'], 400);
                }

                if (!in_array($targetLayer, $allowedTables) || !in_array($refLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                $validOperators = ['ST_Intersects', 'ST_Within'];
                $operator = in_array($operator, $validOperators) ? $operator : 'ST_Intersects';

                // T1.9 (item 2.1-2) — referência LINEAR (logradouro) vira área de interesse
                // via ST_Buffer de N metros sobre o eixo. Buffer zero/ausente = geometria pura.
                $bufferMetros = (float) $request->input('spatial_buffer', 0);
                $bufferMetros = max(0, min($bufferMetros, 50000));

                $refGeomExpr = $bufferMetros > 0
                    ? 'ST_Buffer(ref.geo::geography, ?)::geometry'
                    : 'ref.geo::geometry';

                // T1.8 — condição de atributo combinada na MESMA consulta
                [$attrSql, $attrBindings, $attrRotulo] = $condicaoAtributo($targetLayer, 'target.');

                // 🪄 Bindings na ordem em que os ? aparecem no SQL:
                // [buffer?] + tenant + refIds + [atributo?]
                $placeholders = implode(',', array_fill(0, count($refIds), '?'));
                $params = array_merge(
                    $bufferMetros > 0 ? [$bufferMetros] : [],
                    [$tenantId],
                    $refIds,
                    $attrBindings
                );

                $query = "
                    SELECT
                        target.*,
                        ST_AsGeoJSON(target.geo) as geo_json,
                        ref.name as searched_value
                    FROM {$targetLayer} target
                    INNER JOIN {$refLayer} ref
                        ON {$operator}(target.geo::geometry, {$refGeomExpr})
                    WHERE target.tenant_id = ?
                    AND target.deleted_at IS NULL
                    AND target.geo IS NOT NULL
                    AND ref.id IN ($placeholders)
                    " . ($attrSql ? $attrSql . ' ' : '') . "
                    LIMIT 2500
                ";

                $results = \Illuminate\Support\Facades\DB::select($query, $params);
                $layer = $targetLayer;
                $infoLabel = "Cruzamento Espacial ({$operator} em {$refLayer}"
                    . ($bufferMetros > 0 ? ", buffer {$bufferMetros}m" : '')
                    . ($attrRotulo ? " + {$attrRotulo}" : '')
                    . ')';
            }
            // ========================================================================
            // 🟢 ROTA 3: CRUZAMENTO POR DESENHO (Polígono / Retângulo)
            // ========================================================================
            elseif ($tipoFiltro === 'desenho') {
                $targetLayer = $request->input('draw_target_layer'); // O que buscar (ex: lotes)
                $drawnGeometry = $request->input('drawn_geometry');  // O GeoJSON do desenho do usuário

                // 👈 Puxa o operador que o JS vai nos mandar agora
                $drawOperator = $request->input('draw_spatial_operator', 'ST_Intersects');

                if (!$targetLayer || !$drawnGeometry) {
                    return response()->json(['error' => 'Parâmetros incompletos. Geometria de desenho ausente.'], 400);
                }

                if (!in_array($targetLayer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida por segurança'], 403);
                }

                // 🛡️ Segurança dupla: Garante que só rodam operadores conhecidos
                $validDrawOperators = ['ST_Intersects', 'ST_Within'];
                $drawOperator = in_array($drawOperator, $validDrawOperators) ? $drawOperator : 'ST_Intersects';

                // T1.8 — condição de atributo combinada com o desenho ("dentro da área E com área > X")
                [$attrSql, $attrBindings, $attrRotulo] = $condicaoAtributo($targetLayer, 'target.');

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
                    " . ($attrSql ? $attrSql . ' ' : '') . "
                    LIMIT 2000
                ";

                $results = \Illuminate\Support\Facades\DB::select($query, array_merge([$tenantId, $drawnGeometry], $attrBindings));
                $layer = $targetLayer;
                $infoLabel = 'Consulta Geográfica (Desenho Livre' . ($attrRotulo ? " + {$attrRotulo}" : '') . ')';
            }
            // ========================================================================
            // ROTA 2: FILTRO POR ATRIBUTO (O Tradicional)
            // ========================================================================
            elseif ($tipoFiltro === 'atributo') {
                $layer = $request->input('layer');
                $field = $request->input('field');
                $operator = $request->input('operator');
                $value = $request->input('value');

                if (!$layer || !$field || !$operator || $value === null) {
                    return response()->json(['error' => 'Parâmetros incompletos para a query de atributos'], 400);
                }

                if (!in_array($layer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida'], 403);
                }

                // Onda 4 — campo validado pela whitelist (schema ou custom:slug do município).
                // Antes o $field ia interpolado CRU no selectRaw: injeção de SQL.
                $info = $resolveCampo($layer, $field);

                if (! $info || ! in_array($operator, $operadoresValidos, true)) {
                    return response()->json(['error' => 'Campo ou operador não permitido.'], 403);
                }

                $queryBuilder = \Illuminate\Support\Facades\DB::table($layer)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo');

                if ($operator === 'LIKE') {
                    $queryBuilder->whereRaw($info['expr'] . '::text ILIKE ?', ['%' . $value . '%']);
                } else {
                    $binding = $info['numerico'] && is_numeric($value) ? (float) $value : $value;
                    $queryBuilder->whereRaw($info['expr'] . ' ' . $operator . ' ?', [$binding]);
                }

                $results = $queryBuilder->selectRaw('
                    *,
                    ST_AsGeoJSON(geo) as geo_json,
                    ' . $info['expr'] . ' as searched_value
                ')->limit(2000)->get();

                $infoLabel = "Atributo ({$info['label']})";

                // ========================================================================
                // 🎨 ROTA 5 (T1.10): TEMATIZAÇÃO POR VALORES ÚNICOS — itens 2.5-32/34/37
                // e 3-18/20/23. Agrupa os valores distintos do atributo (coluna ou campo
                // customizado do município) e devolve as feições rotuladas + o resumo
                // de valores para a legenda/paleta do front.
                // ========================================================================
            } elseif ($tipoFiltro === 'valores_unicos') {
                $layer = $request->input('layer');
                $vuAttr = $request->input('vu_attribute');

                if (! $layer || ! $vuAttr) {
                    return response()->json(['error' => 'Parâmetros incompletos para Valores Únicos'], 400);
                }

                if (! in_array($layer, $allowedTables)) {
                    return response()->json(['error' => 'Camada não permitida'], 403);
                }

                $info = $resolveCampo($layer, $vuAttr);

                if (! $info) {
                    return response()->json(['error' => 'Atributo não permitido.'], 403);
                }

                $results = \Illuminate\Support\Facades\DB::table($layer)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo')
                    ->selectRaw('
                        id,
                        COALESCE(' . $info['expr'] . '::text, \'Não informado\') as searched_value,
                        ST_AsGeoJSON(geo) as geo_json
                    ')
                    ->limit(5000)
                    ->get();

                $resumoValores = collect($results)
                    ->groupBy('searched_value')
                    ->map(fn ($g, $v) => ['valor' => (string) $v, 'quantidade' => $g->count()])
                    ->sortByDesc('quantidade')
                    ->values()
                    ->all();

                $features = [];
                foreach ($results as $item) {
                    if (! empty($item->geo_json)) {
                        // Geometria vazia (coordinates: []) derruba o parser do OpenLayers
                        $geom = json_decode($item->geo_json);
                        if (! $geom || empty($geom->coordinates)) {
                            continue;
                        }

                        $features[] = [
                            'type' => 'Feature',
                            'properties' => [
                                'id' => $item->id,
                                'layer' => $layer,
                                'valor_unico' => (string) $item->searched_value,
                                'info' => "{$info['label']}: {$item->searched_value}",
                            ],
                            'geometry' => $geom,
                        ];
                    }
                }

                return response()->json([
                    'type' => 'FeatureCollection',
                    'features' => $features,
                    'valores' => $resumoValores,
                    'atributo_label' => $info['label'],
                ]);

                // ========================================================================
                // 📊 ROTA 4: TEMATIZAÇÃO POR INTERVALO DE CLASSES
                // ========================================================================
            } elseif ($tipoFiltro === 'intervalo') {
                $layer = $request->input('layer'); // 'lotes'
                $attr = $request->input('interval_attribute'); // 'area_geo'

                // T1.11 — o heatmap genérico reusa esta rota SEM atributo (peso = 1 por
                // feição; com atributo numérico, o valor vira o peso do calor).
                if (! $layer) {
                    return response()->json(['error' => 'Parâmetros incompletos para o Intervalo'], 400);
                }

                if (blank($attr)) {
                    $attr = null;
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

                // Onda 4 — whitelist do atributo (schema ou custom:slug numérico do município).
                // Antes o $attr ia interpolado cru no selectRaw (injeção de SQL).
                // T1.11 — sem atributo (heatmap por contagem): peso fixo 1.
                $infoAttr = $attr === null
                    ? ['expr' => '1', 'numerico' => true, 'label' => 'Contagem']
                    : $resolveCampo($layer, $attr);

                if (! $infoAttr) {
                    return response()->json(['error' => 'Atributo não permitido.'], 403);
                }

                $results = \Illuminate\Support\Facades\DB::table($layer)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('geo')
                    ->selectRaw('
                        id,
                        ' . $labelCol . ' as label_visual,
                        ' . $infoAttr['expr'] . ' as searched_value,
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

                    // Onda 5 — geometria vazia (coordinates: []) derruba o readFeatures
                    // do OpenLayers em TODAS as rotas; filtra na origem.
                    $geomComum = json_decode($item->geo_json);
                    if (! $geomComum || empty($geomComum->coordinates)) {
                        continue;
                    }

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
                        'geometry' => $geomComum
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
            $tenantId   = $request->input('tenant_id');
            $areaType   = $request->input('area_type');   // bairros | setores_fiscais | perimetros_urbanos
            $areaId     = $request->input('area_id');     // id da área (ou 'all')
            $targetLayer= $request->input('target_layer'); // lotes | edificacoes | logradouros
            $groupField = $request->input('group_field'); // campo para agrupar
 
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
                    // Refatoração PoC Tangará: os atributos descritivos viraram campos
                    // customizados — o agrupamento lê o JSONB. `expr` = expressão segura
                    // (o nome que chega do front é só a CHAVE deste array).
                    'group_fields'=> [
                        'tipo'                    => ['label' => 'Tipo de Edificação', 'expr' => "dados_customizados->>'tipo_edificacao'"],
                        'tp_construcao'           => ['label' => 'Tipo de Construção', 'expr' => "dados_customizados->>'tp_construcao'"],
                        'estado_conservacao'      => ['label' => 'Estado de Conservação', 'expr' => "dados_customizados->>'estado_conservacao'"],
                        'caracteristica_construcao'=> ['label' => 'Característica', 'expr' => "dados_customizados->>'caracteristica_construcao'"],
                    ],
                ],
                'logradouros' => [
                    'table'       => 'logradouros',
                    'label_col'   => 'name',
                    'group_fields'=> [
                        'name' => ['label' => 'Nome do Logradouro'],
                    ],
                ],
                // T1.12 (item 2.6-38) — estatísticas para qualquer camada com item de cadastro
                'arvores' => [
                    'table'       => 'arvores',
                    'label_col'   => 'sequential_id',
                    'group_fields'=> [
                        'botanical_species' => ['label' => 'Espécie Botânica'],
                        'size' => ['label' => 'Porte'],
                        'phytosanitary_condition' => ['label' => 'Condição Fitossanitária'],
                        'general_state' => ['label' => 'Estado Geral'],
                    ],
                ],
                'postes' => [
                    'table'       => 'postes',
                    'label_col'   => 'sequential_id',
                    'group_fields'=> [
                        'structural_condition' => ['label' => 'Condição Estrutural'],
                        'luminaire_type' => ['label' => 'Tipo de Luminária'],
                    ],
                ],
                'chamados' => [
                    'table'       => 'chamados',
                    'label_col'   => 'id',
                    'group_fields'=> [
                        'categoria' => ['label' => 'Categoria', 'join' => ['categorias_chamado', 'categoria_chamado_id', 'id', 'nome']],
                        'fase' => ['label' => 'Fase', 'join' => ['fases_chamado', 'fase_chamado_id', 'id', 'nome']],
                    ],
                ],
                'quadras' => [
                    'table'       => 'quadras',
                    'label_col'   => 'name',
                    'group_fields'=> [
                        'bairro_id' => ['label' => 'Bairro', 'join' => ['bairros', 'bairro_id', 'id', 'name']],
                    ],
                ],
                'secoes_logradouro' => [
                    'table'       => 'secoes_logradouro',
                    'label_col'   => 'codigo',
                    'group_fields'=> [
                        'lado' => ['label' => 'Lado da Seção'],
                    ],
                ],
                'meio_fios' => [
                    'table'       => 'meio_fios',
                    'label_col'   => 'sequential_id',
                    'group_fields'=> [],
                ],
            ];

            if (!isset($layerConfig[$targetLayer])) {
                return response()->json(['error' => 'Camada inválida.'], 400);
            }

            $cfg   = $layerConfig[$targetLayer];
            $table = $cfg['table'];

            // Segurança: o campo de agrupamento PRECISA estar na whitelist da camada —
            // antes o nome ia cru para o selectRaw (injeção de SQL via group_field).
            // T1.12 + item 76⑤ — fora da whitelist curada, aceita campo customizado do
            // município (custom:slug) ou coluna real do schema, validados.
            if (! isset($cfg['group_fields'][$groupField])) {
                $entidadeCustom = array_search($table, \App\Services\Coleta\CampoCustomizadoService::ENTIDADE_TABELA, true);
                $infoGrupo = null;

                if (str_starts_with((string) $groupField, 'custom:') && $entidadeCustom !== false) {
                    $slugGrupo = substr($groupField, 7);
                    $defGrupo = preg_match('/^[a-z0-9_]+$/', $slugGrupo)
                        ? \App\Services\Coleta\CampoCustomizadoService::definicoes($entidadeCustom, (int) $tenantId)->firstWhere('slug', $slugGrupo)
                        : null;

                    if ($defGrupo) {
                        $infoGrupo = ['label' => $defGrupo->label, 'expr' => "dados_customizados->>'{$slugGrupo}'"];
                    }
                } elseif (preg_match('/^[a-z0-9_]+$/', (string) $groupField)
                    && in_array($groupField, \Illuminate\Support\Facades\Schema::getColumnListing($table), true)) {
                    $infoGrupo = ['label' => $groupField, 'expr' => $groupField];
                }

                if (! $infoGrupo) {
                    return response()->json(['error' => 'Campo de agrupamento inválido.'], 400);
                }

                $cfg['group_fields'][$groupField] = $infoGrupo;
            }
 
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
                    // Agrupamento direto: coluna da tabela ou expressão JSONB (campos
                    // que viraram customizados). A expressão vem da whitelist, nunca do request.
                    $groupExpr = $cfg['group_fields'][$groupField]['expr'] ?? $groupField;

                    $rows = (clone $q)
                        ->selectRaw("{$groupExpr} as grupo_valor, COUNT(*) as quantidade")
                        ->groupByRaw($groupExpr)
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