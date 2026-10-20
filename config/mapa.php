<?php

/*
|--------------------------------------------------------------------------
| Catálogo do MAPA INTERATIVO — entidades × ações × ferramentas (Release 83)
|--------------------------------------------------------------------------
| FONTE ÚNICA de "o que cada papel pode fazer no mapa", lida por
| App\Support\MapaPermissoes e consumida por:
|   - MapaFullscreen + traits Has*Actions (guard de criar/editar/excluir);
|   - mapa-fullscreen.blade.php (botões de desenho, ficha, ferramentas);
|   - endpoint /gis/{slug}/map-permissions e o engine (reforço visual);
|   - /api/gis-data, consulta avançada, estatísticas e export SHP (camadas);
|   - RoleResource (matriz Menu · Mapa · Criar · Editar · Excluir por módulo).
|
| Vocabulário (decisão do usuário, 2026-10-12 — docs/arquivo/pendenciasTangara.md, R83):
|   menu    = ver a TELA do menu (view_X ou gerenciar_X)        — não é do mapa
|   mapa    = ver a CAMADA no mapa (ver_camada_X)
|   criar / editar / excluir = as MESMAS permissões das Policies das telas
|            (create_X / edit_X / delete_X): uma permissão, dois lugares.
|   null    = a ação não existe para a entidade (camada só leitura, tela sem mapa).
|
| `camadas`   = chaves data-layer/case do MapDataController que a entidade cobre
|               (com "_"; o helper normaliza "-"). Camada-filha (edificacoes,
|               testada_ativa) aponta para a entidade-mãe.
| `artefatos` = tipos do enableDrawing() que criam a entidade.
| `mapa_de`   = a camada do mapa é compartilhada com outra entidade (cemitério).
| `publica`   = servida SEM login pelo /api/gis-data (é o que o mapa público usa);
|               o resto exige login + vínculo com a prefeitura + `mapa` ou bypass.
| `grupo`     = nome D8 (sidebar = acordeon = Criar Artefatos = caixa de papéis).
*/

$q = fn (string $e) => ['menu' => "view_{$e}", 'criar' => "create_{$e}", 'editar' => "edit_{$e}", 'excluir' => "delete_{$e}"];

return [

    'entidades' => [

        // ── Base Cartográfica ───────────────────────────────────────────
        'perimetros_urbanos' => ['label' => 'Distritos / Limites', 'modulo' => 'base_cartografica', 'grupo' => 'Base Cartográfica',
            'camadas' => ['perimetros', 'perimetros_urbanos'], 'artefatos' => ['perimetro_urbano'],
            'mapa' => 'ver_camada_perimetros', 'publica' => true] + $q('perimetros_urbanos'),
        // B9 — setor da inscrição imobiliária (agrupa 1+ bairros); o da PGV é `setores_pgv`
        'setores_fiscais' => ['label' => 'Setores Fiscais', 'modulo' => 'base_cartografica', 'grupo' => 'Base Cartográfica',
            'camadas' => ['setores_fiscais'], 'artefatos' => ['setor_fiscal'],
            'mapa' => 'ver_camada_setores_fiscais', 'publica' => true] + $q('setores_fiscais'),
        'bairros' => ['label' => 'Bairros', 'modulo' => 'base_cartografica', 'grupo' => 'Base Cartográfica',
            'camadas' => ['bairros'], 'artefatos' => ['bairro'],
            'mapa' => 'ver_camada_bairros', 'publica' => true] + $q('bairros'),
        'logradouros' => ['label' => 'Logradouros', 'modulo' => 'base_cartografica', 'grupo' => 'Base Cartográfica',
            'camadas' => ['logradouros'], 'artefatos' => ['logradouro'],
            'mapa' => 'ver_camada_logradouros', 'publica' => true] + $q('logradouros'),
        'secoes_logradouro' => ['label' => 'Seções de Logradouro', 'modulo' => 'base_cartografica', 'grupo' => 'Base Cartográfica',
            'camadas' => ['secoes_logradouro'], 'artefatos' => ['secao_logradouro'],
            'mapa' => 'ver_camada_secoes_logradouro', 'publica' => false,
            'nota' => 'Nasce do próprio logradouro (modal, aba Seções, "Salvar como").'] + $q('secoes_logradouro'),

        // ── Cadastro Imobiliário ────────────────────────────────────────
        'loteamentos' => ['label' => 'Loteamentos', 'modulo' => 'imobiliario', 'grupo' => 'Cadastro Imobiliário',
            'camadas' => ['loteamentos'], 'artefatos' => ['loteamento'],
            'mapa' => 'ver_camada_loteamentos', 'publica' => true] + $q('loteamentos'),
        'quadras' => ['label' => 'Quadras', 'modulo' => 'imobiliario', 'grupo' => 'Cadastro Imobiliário',
            'camadas' => ['quadras'], 'artefatos' => ['quadra'],
            'mapa' => 'ver_camada_quadras', 'publica' => true] + $q('quadras'),
        'lotes' => ['label' => 'Lotes', 'modulo' => 'imobiliario', 'grupo' => 'Cadastro Imobiliário',
            // edificações, testadas e unidades SEGUEM o lote (decisão R83): mesmas permissões
            'camadas' => ['lotes', 'edificacoes', 'testada_ativa', 'edificacao_ativa'], 'artefatos' => ['lote', 'edificacao', 'testada'],
            'mapa' => 'ver_camada_lotes', 'publica' => true,
            'nota' => 'Edificações, testadas, unidades e fotos seguem as permissões do lote.'] + $q('lotes'),
        'meio_fios' => ['label' => 'Meio-fio / Calçada', 'modulo' => 'imobiliario', 'grupo' => 'Cadastro Imobiliário',
            'camadas' => ['meio_fios'], 'artefatos' => ['meio_fio'],
            'mapa' => 'ver_camada_meio_fios', 'publica' => false] + $q('meio_fios'),
        'zonas' => ['label' => 'Zonas de Uso', 'modulo' => 'imobiliario', 'grupo' => 'Zoneamento Urbano',
            'camadas' => ['zonas'], 'artefatos' => ['zona'],
            'mapa' => 'ver_camada_zonas', 'publica' => true] + $q('zonas'),
        'areas_reurb' => ['label' => 'Áreas REURB', 'modulo' => 'imobiliario', 'grupo' => 'Infraestrutura',
            'camadas' => ['areas_reurb'], 'artefatos' => ['area_reurb'],
            'mapa' => 'ver_camada_areas_reurb', 'publica' => false] + $q('areas_reurb'),

        // ── Camadas SÓ LEITURA (sem CRUD no mapa) ───────────────────────
        'coleta' => ['label' => 'Coleta de Dados (status dos lotes)', 'modulo' => 'coleta_cadastral', 'grupo' => 'Coleta de Dados',
            'camadas' => ['coleta'], 'artefatos' => [],
            'menu' => null, 'mapa' => 'ver_camada_coleta', 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],
        'processos' => ['label' => 'Processos Digitais (lotes com processo)', 'modulo' => 'processos', 'grupo' => 'Processos Digitais',
            'camadas' => ['processos'], 'artefatos' => [],
            'menu' => null, 'mapa' => 'ver_camada_processos', 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],

        // ── Imageamento ─────────────────────────────────────────────────
        'pontos_panoramicos' => ['label' => 'Pontos Panorâmicos 360°', 'modulo' => 'imageamento', 'grupo' => 'Imageamento',
            'camadas' => ['pontos_panoramicos'], 'artefatos' => ['ponto_panoramico'],
            'mapa' => 'ver_camada_pontos_panoramicos', 'publica' => true] + $q('pontos_panoramicos'),

        // ── Mobilidade Urbana ───────────────────────────────────────────
        'mob_trechos' => ['label' => 'Trechos Viários (levantamento)', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_trechos'], 'artefatos' => ['mob_trecho'],
            'mapa' => 'ver_camada_mob_trechos', 'publica' => true] + $q('mob_trechos'),
        'mob_vias' => ['label' => 'Vias Urbanas (sentido/fluxo)', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_vias'], 'artefatos' => ['mob_via'],
            'mapa' => 'ver_camada_mob_vias', 'publica' => true] + $q('mob_vias'),
        'mob_sinalizacoes' => ['label' => 'Sinalização Viária', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_sinalizacoes'], 'artefatos' => ['mob_sinalizacao'],
            'mapa' => 'ver_camada_mob_sinalizacoes', 'publica' => true] + $q('mob_sinalizacoes'),
        'mob_pontos_interesse' => ['label' => 'Pontos de Interesse', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_pontos_interesse'], 'artefatos' => ['mob_ponto_interesse'],
            'mapa' => 'ver_camada_mob_pontos_interesse', 'publica' => true] + $q('mob_pontos_interesse'),
        'mob_eixos' => ['label' => 'Eixos (ciclovias, rotas de carga, rodovias)', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_eixos'], 'artefatos' => ['mob_eixo'],
            'mapa' => 'ver_camada_mob_eixos', 'publica' => true] + $q('mob_eixos'),
        'mob_zonas' => ['label' => 'Zonas de Estudo (O/D, setores IBGE)', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_zonas'], 'artefatos' => ['mob_zona'],
            'mapa' => 'ver_camada_mob_zonas', 'publica' => true] + $q('mob_zonas'),
        'mob_cameras' => ['label' => 'Câmeras de Monitoramento', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_cameras'], 'artefatos' => ['mob_camera'],
            'mapa' => 'ver_camada_mob_cameras', 'publica' => true] + $q('mob_cameras'),
        'mob_fluxos' => ['label' => 'Fluxos Origem/Destino', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => ['mob_fluxos'], 'artefatos' => [],
            // SÓ LEITURA no mapa (decisão 2026-09-04); a tela do menu é permissão única
            'menu' => 'gerenciar_mob_fluxos', 'mapa' => 'ver_camada_mob_fluxos', 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => true],
        'mob_tipos_sinalizacao' => ['label' => 'Catálogo de Tipos de Sinalização', 'modulo' => 'mob_infra', 'grupo' => 'Mobilidade Urbana',
            'camadas' => [], 'artefatos' => [],
            'menu' => 'gerenciar_mob_tipos_sinalizacao', 'mapa' => null, 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],

        // ── Infraestrutura ──────────────────────────────────────────────
        'setores_pgv' => ['label' => 'Setores da PGV', 'modulo' => 'pgv', 'grupo' => 'Infraestrutura',
            'camadas' => ['setores_pgv'], 'artefatos' => ['setor_pgv'],
            'mapa' => 'ver_camada_setores_pgv', 'publica' => true] + $q('setores_pgv'),
        'patrimonio_publicos' => ['label' => 'Patrimônios Públicos', 'modulo' => 'patrimonios', 'grupo' => 'Infraestrutura',
            'camadas' => ['patrimonio_publicos', 'patrimonio_publico'], 'artefatos' => ['patrimonio_publico'],
            'mapa' => 'ver_camada_patrimonio_publico', 'publica' => false] + $q('patrimonio_publicos'),
        'tipo_patrimonios' => ['label' => 'Tipos de Patrimônio', 'modulo' => 'patrimonios', 'grupo' => 'Infraestrutura',
            'camadas' => [], 'artefatos' => [], 'mapa' => null, 'publica' => false] + $q('tipo_patrimonios'),
        'postes' => ['label' => 'Postes / Iluminação', 'modulo' => 'iluminacao', 'grupo' => 'Infraestrutura',
            'camadas' => ['postes'], 'artefatos' => ['poste'],
            'mapa' => 'ver_camada_postes', 'publica' => true] + $q('postes'),
        'tipos_poste' => ['label' => 'Tipos de Poste', 'modulo' => 'iluminacao', 'grupo' => 'Infraestrutura',
            'camadas' => [], 'artefatos' => [], 'mapa' => null, 'publica' => false] + $q('tipos_poste'),
        'arvores' => ['label' => 'Árvores', 'modulo' => 'arborizacao', 'grupo' => 'Infraestrutura',
            'camadas' => ['arvores'], 'artefatos' => ['arvore'],
            'mapa' => 'ver_camada_arvores', 'publica' => true] + $q('arvores'),
        'chamados' => ['label' => 'Chamados (App)', 'modulo' => 'chamados', 'grupo' => 'Infraestrutura',
            'camadas' => ['chamados'], 'artefatos' => [],
            'menu' => 'gerenciar_chamados', 'mapa' => 'ver_camada_chamados', 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],
        'chamados_mapa' => ['label' => 'Chamados pelo Mapa (público)', 'modulo' => 'chamados_mapa', 'grupo' => 'Infraestrutura',
            'camadas' => ['chamados_mapa'], 'artefatos' => [],
            'menu' => 'gerenciar_chamados_mapa', 'mapa' => 'ver_camada_chamados_mapa', 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],

        // ── Gestão de Cemitérios (a camada do mapa é UMA — ver_camada_cemiterios) ──
        'cemiterios' => ['label' => 'Cemitérios', 'modulo' => 'cemiterio', 'grupo' => 'Gestão de Cemitérios',
            'camadas' => ['cemiterios'], 'artefatos' => ['cemiterio'],
            'mapa' => 'ver_camada_cemiterios', 'publica' => true] + $q('cemiterios'),
        'quadras_cemiterio' => ['label' => 'Quadras de Cemitério', 'modulo' => 'cemiterio', 'grupo' => 'Gestão de Cemitérios',
            'camadas' => ['quadras_cemiterio'], 'artefatos' => ['quadra_cemiterio'],
            'mapa' => 'ver_camada_cemiterios', 'mapa_de' => 'cemiterios', 'publica' => true] + $q('quadras_cemiterio'),
        'logradouros_cemiterio' => ['label' => 'Ruas de Cemitério', 'modulo' => 'cemiterio', 'grupo' => 'Gestão de Cemitérios',
            'camadas' => ['logradouros_cemiterio'], 'artefatos' => ['logradouro_cemiterio'],
            'mapa' => 'ver_camada_cemiterios', 'mapa_de' => 'cemiterios', 'publica' => true] + $q('logradouros_cemiterio'),
        'jazigos' => ['label' => 'Jazigos', 'modulo' => 'cemiterio', 'grupo' => 'Gestão de Cemitérios',
            'camadas' => ['jazigos'], 'artefatos' => ['jazigo'],
            'mapa' => 'ver_camada_cemiterios', 'mapa_de' => 'cemiterios', 'publica' => true] + $q('jazigos'),

        // ── Cadastro Rural ──────────────────────────────────────────────
        'rural_localidades' => ['label' => 'Localidades / Distritos Rurais', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_localidades'], 'artefatos' => ['rural_localidade'],
            'mapa' => 'ver_camada_rural_localidades', 'publica' => true] + $q('rural_localidades'),
        'rural_propriedades' => ['label' => 'Propriedades Rurais (CAR/INCRA)', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_propriedades'], 'artefatos' => ['rural_propriedade'],
            'mapa' => 'ver_camada_rural_propriedades', 'publica' => true] + $q('rural_propriedades'),
        'rural_estradas' => ['label' => 'Estradas Rurais', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_estradas'], 'artefatos' => ['rural_estrada'],
            'mapa' => 'ver_camada_rural_estradas', 'publica' => true] + $q('rural_estradas'),
        'rural_hidrografias' => ['label' => 'Hidrografia (rios, lagos, nascentes)', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_hidrografias'], 'artefatos' => ['rural_hidro_linha', 'rural_hidro_poligono', 'rural_hidro_ponto', 'rural_hidrografia'],
            'mapa' => 'ver_camada_rural_hidrografias', 'publica' => true] + $q('rural_hidrografias'),
        'rural_pontes' => ['label' => 'Pontes', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_pontes'], 'artefatos' => ['rural_ponte'],
            'mapa' => 'ver_camada_rural_pontes', 'publica' => true] + $q('rural_pontes'),
        'rural_pontos_interesse' => ['label' => 'Pontos de Interesse Rurais', 'modulo' => 'rural', 'grupo' => 'Cadastro Rural',
            'camadas' => ['rural_pontos_interesse'], 'artefatos' => ['rural_ponto_interesse'],
            'mapa' => 'ver_camada_rural_pontos_interesse', 'publica' => true] + $q('rural_pontos_interesse'),

        // ── Gestão Tributária (PGV) — telas + artefatos sem camada própria ──
        'pgv_parametros' => ['label' => 'Parâmetros Base (PGV)', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => [], 'mapa' => null, 'publica' => false] + $q('pgv_parametros'),
        'lote_valor_historicos' => ['label' => 'Valores Venais (histórico)', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => [], 'mapa' => null, 'publica' => false] + $q('lote_valor_historicos'),
        'pgv_amostras' => ['label' => 'Amostras de Mercado', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => ['pgv_amostra'], 'mapa' => null, 'publica' => false,
            'nota' => 'No mapa: Ferramentas → Nova Amostra (clique) e Motor da PGV.'] + $q('pgv_amostras'),
        'pgv_polos' => ['label' => 'Pólos Valorizantes', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => ['pgv_polo'], 'mapa' => null, 'publica' => false,
            'nota' => 'No mapa: Ferramentas → Novo Pólo (clique).'] + $q('pgv_polos'),
        'face_quadras' => ['label' => 'Faces de Quadra', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => ['face_quadra'], 'mapa' => null, 'publica' => false,
            'nota' => 'No mapa: nasce da quadra (modal → Nova Face) e aparece no Motor da PGV.'] + $q('face_quadras'),
        'pgv_cubs' => ['label' => 'Tabela CUB', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => [], 'menu' => 'gerenciar_pgv_cubs', 'mapa' => null, 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],
        'pgv_depreciacoes' => ['label' => 'Depreciação', 'modulo' => 'pgv', 'grupo' => 'Gestão Tributária (PGV)',
            'camadas' => [], 'artefatos' => [], 'menu' => 'gerenciar_pgv_depreciacoes', 'mapa' => null, 'criar' => null, 'editar' => null, 'excluir' => null, 'publica' => false],

        // ── Anotações (núcleo) ──────────────────────────────────────────
        'toponimias' => ['label' => 'Toponímias / Textos', 'modulo' => 'nucleo', 'grupo' => 'Anotações',
            'camadas' => ['toponimias'], 'artefatos' => ['toponimia'],
            'menu' => null, 'mapa' => 'ver_camada_toponimias',
            'criar' => 'create_toponimias', 'editar' => 'edit_toponimias', 'excluir' => 'delete_toponimias', 'publica' => false],
    ],

    /*
    | FILHOS do lote: edificação, testada, unidade e fotos não têm permissão própria —
    | criar/editar/excluir qualquer um deles = EDITAR o lote (é conteúdo do lote,
    | como as unidades); ver = ver a camada de lotes. Chaves = artefatos, camadas e
    | nomes usados pelos traits/engine.
    */
    'filhos' => [
        'edificacao' => 'lotes', 'edificacoes' => 'lotes', 'edificacao_ativa' => 'lotes',
        'testada' => 'lotes', 'testadas' => 'lotes', 'testada_ativa' => 'lotes',
        'unidade' => 'lotes', 'unidades' => 'lotes', 'fotos_lote' => 'lotes',
    ],

    /*
    | Ferramentas do mapa — cada id vira a permissão `ferramenta_<id>` (uma por item
    | do menu, decisão R83). `exige` = permissão de ENTIDADE que a ferramenta também
    | precisa porque ela GRAVA (checada no servidor junto com a da ferramenta).
    | `grupo` organiza a caixa "Mapa — Ferramentas" da tela de Papéis.
    */
    'ferramentas' => [
        // Barra principal
        'filtros' => ['label' => 'Filtro avançado / tematização', 'grupo' => 'Barra principal'],
        'estatisticas' => ['label' => 'Estatísticas por área', 'grupo' => 'Barra principal'],
        'enquadramento' => ['label' => 'Salvar enquadramento padrão da prefeitura', 'grupo' => 'Barra principal'],
        'cad' => ['label' => 'Módulo CAD avançado (mesa de desenho, Unir/Cortar)', 'grupo' => 'Barra principal'],
        'cores' => ['label' => 'Personalizar cores das camadas', 'grupo' => 'Barra principal'],
        // Menu Ferramentas
        'numeracao' => ['label' => 'Numeração Predial', 'grupo' => 'Menu Ferramentas', 'exige' => ['lotes' => 'editar']],
        'altimetria' => ['label' => 'Perfil de terreno (altimetria)', 'grupo' => 'Menu Ferramentas'],
        'unificar' => ['label' => 'Unificar lotes (solda)', 'grupo' => 'Menu Ferramentas', 'exige' => ['lotes' => 'editar']],
        'desmembrar' => ['label' => 'Desmembrar / cortar lote (ficha do lote)', 'grupo' => 'Menu Ferramentas', 'exige' => ['lotes' => 'editar']],
        'azimute' => ['label' => 'Criar por azimutes', 'grupo' => 'Menu Ferramentas'],
        'coordxy' => ['label' => 'Criar por coordenada XY', 'grupo' => 'Menu Ferramentas'],
        'sentidos' => ['label' => 'Classificar sentidos das vias urbanas', 'grupo' => 'Menu Ferramentas', 'exige' => ['mob_vias' => 'editar']],
        'lidar' => ['label' => 'Visualizador 3D (LiDAR)', 'grupo' => 'Menu Ferramentas'],
        'wms' => ['label' => 'Camadas WMS (salvas e avulsas)', 'grupo' => 'Menu Ferramentas'],
        'medir' => ['label' => 'Medir distância e área', 'grupo' => 'Menu Ferramentas'],
        'capturar' => ['label' => 'Capturar coordenadas', 'grupo' => 'Menu Ferramentas'],
        'street_view' => ['label' => 'Street View (ficha do lote)', 'grupo' => 'Menu Ferramentas'],
        // PGV
        'pgv_simulador' => ['label' => 'Simulador de valores (PGV)', 'grupo' => 'PGV'],
        'pgv_motor' => ['label' => 'Motor da PGV (avaliação em massa)', 'grupo' => 'PGV'],
        'pgv_amostra' => ['label' => 'Nova amostra pelo clique', 'grupo' => 'PGV', 'exige' => ['pgv_amostras' => 'criar']],
        'pgv_polo' => ['label' => 'Novo pólo valorizante pelo clique', 'grupo' => 'PGV', 'exige' => ['pgv_polos' => 'criar']],
        // Impressão e exportação
        'imprimir' => ['label' => 'Imprimir mapa em PDF (A4 a A0)', 'grupo' => 'Impressão e exportação'],
        'exportar_shp' => ['label' => 'Exportar dados: camada em SHP + resultado do filtro em GeoPackage, JSON ou Excel (só as camadas que o papel vê)', 'grupo' => 'Impressão e exportação'],
    ],
];
