<?php

/*
|--------------------------------------------------------------------------
| Catálogo de MÓDULOS do SIGWEB (docs/Modulos_Permissoes.txt, 2026-09-04)
|--------------------------------------------------------------------------
| FONTE ÚNICA do que pertence a cada módulo contratado por prefeitura
| (`tenants.modules`). Lido por App\Support\Modulos e consumido por:
|   - menu do /app (HasTenantModule nos Resources; Pages via Modulos::ativo)
|   - tela de Papéis (caixas/opções só de módulos ativos; Manager sync)
|   - mapa interativo (acordeons, camadas, Criar Artefatos, Ferramentas,
|     endpoint map-permissions, 403 no MapDataController)
|   - /admin → Prefeitura → Módulos (rótulos + pré-requisitos)
|
| Chave 'nucleo' = sempre ativo, em qualquer prefeitura.
| Cada módulo: label, descricao, requer[], permissoes[], camadas[] (data-layer do
| painel, com "_"), artefatos[] (enableDrawing), ferramentas[] (ids do menu).
| Permissão/camada/artefato que NÃO estiver em nenhum módulo é tratada como
| núcleo (nunca esconder o que o catálogo desconhece).
*/

$quarteto = fn (string $entidade) => ["view_{$entidade}", "create_{$entidade}", "edit_{$entidade}", "delete_{$entidade}"];

return [

    'nucleo' => [
        'label' => 'Núcleo (sempre ativo)',
        'descricao' => 'Usuários, papéis, auditoria, mapa e ferramentas genéricas.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('users'), ...$quarteto('roles'),
            'view_auditoria', 'gerenciar_wms',
            'toolbar_criar_artefatos', 'toolbar_ferramentas', 'toolbar_filtros',
            'ver_camada_toponimias',
        ],
        'camadas' => ['toponimias'],
        'artefatos' => [],
        'ferramentas' => ['altimetria', 'azimute', 'coordxy', 'wms', 'medir', 'capturar', 'cad'],
    ],

    'base_cartografica' => [
        'label' => 'GIS - Base Cartográfica',
        'descricao' => 'Perímetro/distritos, bairros, logradouros e suas seções. Pré-requisito do imobiliário; base de qualquer módulo GIS.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('perimetros_urbanos'), ...$quarteto('bairros'), ...$quarteto('logradouros'),
            'gerenciar_secoes_logradouro', // D8 (2026-09-05): a seção segue o logradouro
            'ver_camada_perimetros', 'ver_camada_bairros', 'ver_camada_logradouros', 'ver_camada_secoes_logradouro',
        ],
        'camadas' => ['perimetros', 'bairros', 'logradouros', 'secoes_logradouro'],
        'artefatos' => ['perimetro_urbano', 'bairro', 'logradouro'],
        'ferramentas' => [],
    ],

    'imobiliario' => [
        'label' => 'GIS - Cadastro Imobiliário',
        'descricao' => 'Lotes, quadras, loteamentos, edificações, unidades, zoneamento, viabilidade, customizações de campos.',
        'requer' => ['base_cartografica'],
        'permissoes' => [
            ...$quarteto('lotes'), ...$quarteto('meio_fios'), ...$quarteto('loteamentos'),
            ...$quarteto('quadras'), ...$quarteto('zonas'),
            'gerenciar_areas_reurb', 'gerenciar_campos_customizados',
            ...$quarteto('cnaes'), ...$quarteto('regras_zoneamento'), ...$quarteto('parametros_urbanos'),
            'view_viabilidade_emissoes',
            'ver_camada_loteamentos', 'ver_camada_quadras', 'ver_camada_lotes', 'ver_camada_meio_fios',
            'ver_camada_zonas', 'ver_camada_areas_reurb',
        ],
        'camadas' => ['loteamentos', 'quadras', 'lotes', 'edificacoes', 'meio_fios', 'zonas', 'areas_reurb'],
        'artefatos' => ['loteamento', 'quadra', 'lote', 'edificacao', 'meio_fio', 'area_reurb', 'zona', 'testada'],
        'ferramentas' => ['numeracao', 'unificar', 'filtros'],
    ],

    'coleta_cadastral' => [
        'label' => 'APP - Coleta Cadastral',
        'descricao' => 'App de coleta em campo: boletim, regiões, monitoramento, produtividade, validação e mensagens.',
        'requer' => ['imobiliario'],
        'permissoes' => [
            'gerenciar_atribuicoes_coleta', 'view_monitoramento_campo', 'view_produtividade', 'view_mensagens',
            'ver_camada_coleta',
        ],
        'camadas' => ['coleta'],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    // D8 (2026-09-05): o App de Chamados deixa de ser parte da coleta — módulo contratável à parte,
    // sem pré-requisito (chamado = ponto + categoria, não depende de lote).
    'chamados' => [
        'label' => 'APP - App de Chamados',
        'descricao' => 'App de chamados do cidadão: categorias, fluxos/fases, chamados e a camada de chamados no mapa.',
        'requer' => [],
        'permissoes' => [
            'gerenciar_categorias_chamado', 'gerenciar_fluxos_chamado', 'gerenciar_chamados',
            'ver_camada_chamados',
        ],
        'camadas' => ['chamados'],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'imageamento' => [
        'label' => 'GIS - Imageamento (360° e 3D)',
        'descricao' => 'Panorâmicas 360 (Street View) e Visualizador 3D / LiDAR.',
        'requer' => [],
        'permissoes' => [...$quarteto('pontos_panoramicos'), 'ver_camada_pontos_panoramicos'],
        'camadas' => ['pontos_panoramicos'],
        'artefatos' => ['ponto_panoramico'],
        'ferramentas' => ['lidar'],
    ],

    'administrativo' => [
        'label' => 'ADM - Administrativo',
        'descricao' => 'Pessoas, contatos, endereços e documentos.',
        'requer' => [],
        'permissoes' => [...$quarteto('pessoas'), ...$quarteto('contatos'), ...$quarteto('enderecos'), ...$quarteto('documentos')],
        'camadas' => [],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'arborizacao' => [
        'label' => 'GIS - Arborização Urbana',
        'descricao' => 'Cadastro de árvores.',
        'requer' => [],
        'permissoes' => [...$quarteto('arvores'), 'ver_camada_arvores'],
        'camadas' => ['arvores'],
        'artefatos' => ['arvore'],
        'ferramentas' => [],
    ],

    'iluminacao' => [
        'label' => 'GIS - Iluminação Pública',
        'descricao' => 'Postes e tipos de poste.',
        'requer' => [],
        'permissoes' => [...$quarteto('tipos_poste'), ...$quarteto('postes'), 'ver_camada_postes'],
        'camadas' => ['postes'],
        'artefatos' => ['poste'],
        'ferramentas' => [],
    ],

    'estoque' => [
        'label' => 'ADM - Estoque',
        'descricao' => 'Almoxarifado: produtos, lotes/séries, movimentações e cadastros auxiliares.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('locais_estoque'), ...$quarteto('marcas'), ...$quarteto('produtos'),
            'view_estoques', ...$quarteto('movimentacoes'),
            'gerenciar_estabelecimentos', 'gerenciar_fabricantes', 'gerenciar_fornecedores',
            'gerenciar_unidade_medidas', 'gerenciar_embalagens', 'gerenciar_familia_produtos',
            'gerenciar_tipo_estoques', 'gerenciar_operacao_internas', 'gerenciar_lote_estoques',
        ],
        'camadas' => [],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'manutencao' => [
        'label' => 'ADM - Manutenção e Serviços',
        'descricao' => 'Solicitações e ordens de serviço.',
        'requer' => [],
        'permissoes' => [...$quarteto('solicitacoes'), ...$quarteto('ordens_servico')],
        'camadas' => [],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'cemiterio' => [
        'label' => 'GIS - Gestão de Cemitérios',
        'descricao' => 'Cemitérios, quadras, ruas e jazigos.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('cemiterios'), ...$quarteto('quadras_cemiterio'),
            ...$quarteto('logradouros_cemiterio'), ...$quarteto('jazigos'),
            'ver_camada_cemiterios',
        ],
        'camadas' => ['cemiterios', 'quadras_cemiterio', 'logradouros_cemiterio', 'jazigos'],
        'artefatos' => ['cemiterio', 'quadra_cemiterio', 'logradouro_cemiterio', 'jazigo'],
        'ferramentas' => [],
    ],

    'social' => [
        'label' => 'ADM - Cadastro Social',
        'descricao' => 'Famílias, pessoas-social, painel social e cadastros auxiliares.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('cadastros_sociais'), 'view_painel_social',
            'gerenciar_tipo_rendas', 'gerenciar_tipo_entidades', 'gerenciar_entidades', 'gerenciar_servico_sociais',
            'gerenciar_programas', 'gerenciar_eventos', 'gerenciar_informacao_sociais', 'gerenciar_empreendimentos',
        ],
        'camadas' => [],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'pgv' => [
        'label' => 'PGV - Planta Genérica de Valores',
        'descricao' => 'Parâmetros, setores fiscais, histórico de valores e avaliação em massa.',
        'requer' => ['imobiliario'],
        'permissoes' => [
            ...$quarteto('pgv_parametros'), ...$quarteto('setores_fiscais'), ...$quarteto('lote_valor_historicos'),
            'gerenciar_pgv_amostras', 'gerenciar_pgv_polos', 'gerenciar_pgv_cubs', 'gerenciar_pgv_depreciacoes',
            'gerenciar_face_quadras',
            'ver_camada_setores_fiscais',
        ],
        'camadas' => ['setores_fiscais'],
        'artefatos' => ['setor_fiscal'],
        'ferramentas' => ['pgv_simulador', 'pgv_motor', 'pgv_amostra', 'pgv_polo'],
    ],

    'processos' => [
        'label' => 'BPMN - Processos Digitais',
        'descricao' => 'Fluxos BPMN, processos digitais e setores.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('bpmn_fluxos'), ...$quarteto('processos_digitais'),
            'gerenciar_setores', 'view_todos_processos', 'view_processos_progresso',
            'ver_camada_processos',
        ],
        'camadas' => ['processos'],
        'artefatos' => [],
        'ferramentas' => [],
    ],

    'rural' => [
        'label' => 'GIS - Módulo Rural',
        'descricao' => 'Localidades, propriedades, estradas, hidrografia, pontes e pontos de interesse rurais.',
        'requer' => [],
        'permissoes' => [
            ...$quarteto('rural_localidades'), ...$quarteto('rural_propriedades'), ...$quarteto('rural_estradas'),
            ...$quarteto('rural_hidrografias'), ...$quarteto('rural_pontes'), ...$quarteto('rural_pontos_interesse'),
            'ver_camada_rural_localidades', 'ver_camada_rural_propriedades', 'ver_camada_rural_estradas',
            'ver_camada_rural_hidrografias', 'ver_camada_rural_pontes', 'ver_camada_rural_pontos_interesse',
        ],
        'camadas' => ['rural_localidades', 'rural_propriedades', 'rural_estradas', 'rural_hidrografias', 'rural_pontes', 'rural_pontos_interesse'],
        'artefatos' => ['rural_localidade', 'rural_propriedade', 'rural_estrada', 'rural_hidro_linha', 'rural_hidro_poligono', 'rural_hidro_ponto', 'rural_ponte', 'rural_ponto_interesse'],
        'ferramentas' => [],
    ],

    'patrimonios' => [
        'label' => 'ADM - Patrimônios Públicos',
        'descricao' => 'Tipos e patrimônios públicos (com geometria).',
        'requer' => [],
        'permissoes' => [...$quarteto('tipo_patrimonios'), ...$quarteto('patrimonio_publicos'), 'ver_camada_patrimonio_publico'],
        'camadas' => ['patrimonio_publicos'],
        'artefatos' => ['patrimonio_publico'],
        'ferramentas' => [],
    ],

    'mob_infra' => [
        'label' => 'Mobilidade - Infraestrutura',
        'descricao' => 'Mobilidade Urbana: trechos, vias, sinalização, POIs, eixos, zonas, fluxos O/D e câmeras.',
        'requer' => [],
        'permissoes' => [
            'gerenciar_mob_trechos', 'gerenciar_mob_vias', 'gerenciar_mob_sinalizacoes', 'gerenciar_mob_tipos_sinalizacao',
            'gerenciar_mob_pontos_interesse', 'gerenciar_mob_eixos', 'gerenciar_mob_zonas', 'gerenciar_mob_fluxos',
            'gerenciar_mob_cameras',
            'ver_camada_mob_trechos', 'ver_camada_mob_vias', 'ver_camada_mob_sinalizacoes', 'ver_camada_mob_pontos_interesse',
            'ver_camada_mob_eixos', 'ver_camada_mob_zonas', 'ver_camada_mob_fluxos', 'ver_camada_mob_cameras',
        ],
        'camadas' => ['mob_trechos', 'mob_vias', 'mob_cameras', 'mob_sinalizacoes', 'mob_pontos_interesse', 'mob_eixos', 'mob_zonas', 'mob_fluxos'],
        'artefatos' => ['mob_trecho', 'mob_via', 'mob_sinalizacao', 'mob_ponto_interesse', 'mob_camera', 'mob_eixo', 'mob_zona'],
        'ferramentas' => ['sentidos'],
    ],
];
