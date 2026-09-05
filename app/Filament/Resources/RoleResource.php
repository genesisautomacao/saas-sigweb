<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Support\Modulos;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Papéis da prefeitura (Spatie + teams).
 *
 * As "caixas" de permissão são geradas de caixas() — fonte única também para o
 * CreateRole/EditRole. Caixa cujas permissões pertencem a módulo INATIVO na
 * prefeitura não aparece (config/modulos.php via Modulos::filtrarOpcoes); as
 * permissões já gravadas nesses módulos são PRESERVADAS no save (decisão D3 de
 * docs/Modulos_Permissoes.txt). Papel de sistema (Manager) é identificado pela
 * flag roles.papel_sistema (D7): pode ser renomeado, não excluído.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'Papel de Acesso';

    protected static ?string $pluralModelLabel = 'Papéis de Acesso';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 34;

    private static function quarteto(string $entidade, string $singular, string $plural, string $vis = 'Visualizar'): array
    {
        return [
            "view_{$entidade}" => "{$vis} {$plural}",
            "create_{$entidade}" => "Criar {$plural}",
            "edit_{$entidade}" => "Editar {$plural}",
            "delete_{$entidade}" => "Excluir {$plural}",
        ];
    }

    /**
     * Definição das caixas: chave (campo do form) => [titulo, rotulo (null = sem rótulo),
     * opcoes (permissão => rótulo), colunas, span ('full'|1), ajuda?].
     */
    public static function caixas(): array
    {
        return [
            'permissions_users' => ['titulo' => 'Módulo: Equipe (Usuários)', 'rotulo' => 'Gestão de Usuários', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('users', 'Usuário', 'Usuários')],
            'permissions_roles' => ['titulo' => 'Módulo: Segurança (Papéis)', 'rotulo' => 'Gestão de Papéis', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('roles', 'Papel', 'Papéis')],
            'permissions_pessoas' => ['titulo' => 'Módulo Administrativo: Pessoas', 'rotulo' => 'Gestão de Pessoas', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('pessoas', 'Pessoa', 'Pessoas')],
            'permissions_contatos' => ['titulo' => 'Módulo Administrativo: Contatos', 'rotulo' => 'Gestão de Contatos', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('contatos', 'Contato', 'Contatos')],
            'permissions_enderecos' => ['titulo' => 'Módulo Administrativo: Endereços', 'rotulo' => 'Gestão de Endereços', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('enderecos', 'Endereço', 'Endereços')],
            'permissions_documentos' => ['titulo' => 'Módulo Administrativo: Documentos', 'rotulo' => 'Gestão de Documentos', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('documentos', 'Documento', 'Documentos')],
            'permissions_pontos_panoramicos' => ['titulo' => 'Módulo Imageamento: Pontos Panorâmicos 360º', 'rotulo' => 'Gestão de Imagens 360º', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('pontos_panoramicos', 'Ponto 360º', 'Pontos 360º')],
            'permissions_iluminacao' => ['titulo' => 'Módulo: Iluminação Pública', 'rotulo' => 'Gestão de Iluminação', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('tipos_poste', 'Tipo', 'Tipos') + self::quarteto('postes', 'Poste', 'Postes')],
            'permissions_arborizacao' => ['titulo' => 'Módulo: Meio Ambiente (Árvores)', 'rotulo' => 'Gestão de Árvores', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('arvores', 'Árvore', 'Árvores')],
            'permissions_estoque' => ['titulo' => 'Módulo: Estoque', 'rotulo' => 'Gestão de Estoque', 'span' => 'full', 'colunas' => 4,
                'opcoes' => self::quarteto('locais_estoque', 'Local', 'Locais', 'Vis.')
                    + self::quarteto('marcas', 'Marca', 'Marcas', 'Vis.')
                    + self::quarteto('produtos', 'Produto', 'Produtos', 'Vis.')
                    + ['view_estoques' => 'Visualizar Saldos']
                    + self::quarteto('movimentacoes', 'Movimentação', 'Movimentações', 'Vis.')],
            'permissions_estoque_cadastros' => ['titulo' => 'Módulo: Estoque — Cadastros Auxiliares', 'rotulo' => 'Cada permissão libera visualizar, criar, editar e excluir a entidade', 'span' => 'full', 'colunas' => 3,
                'opcoes' => [
                    'gerenciar_estabelecimentos' => 'Gerenciar Estabelecimentos',
                    'gerenciar_fabricantes' => 'Gerenciar Fabricantes',
                    'gerenciar_fornecedores' => 'Gerenciar Fornecedores',
                    'gerenciar_unidade_medidas' => 'Gerenciar Unidades de Medida',
                    'gerenciar_embalagens' => 'Gerenciar Embalagens',
                    'gerenciar_familia_produtos' => 'Gerenciar Famílias de Produto',
                    'gerenciar_tipo_estoques' => 'Gerenciar Tipos de Estoque',
                    'gerenciar_operacao_internas' => 'Gerenciar Operações Internas',
                    'gerenciar_lote_estoques' => 'Gerenciar Lotes / Séries',
                ]],
            'permissions_manutencao' => ['titulo' => 'Módulo: Manutenção e Serviços', 'rotulo' => 'Gestão de Manutenção (O.S.)', 'span' => 'full', 'colunas' => 2,
                'opcoes' => self::quarteto('solicitacoes', 'Solicitação', 'Solicitações', 'Vis.') + self::quarteto('ordens_servico', 'Ordem (OS)', 'Ordens (OS)', 'Vis.')],
            'permissions_cemiterio' => ['titulo' => 'Módulo: Gestão de Cemitérios', 'rotulo' => 'Administração de Cemitérios e Jazigos', 'span' => 'full', 'colunas' => 4,
                'opcoes' => self::quarteto('cemiterios', 'Cemitério', 'Cemitérios', 'Vis.')
                    + self::quarteto('quadras_cemiterio', 'Quadra', 'Quadras', 'Vis.')
                    + self::quarteto('logradouros_cemiterio', 'Rua', 'Ruas', 'Vis.')
                    + self::quarteto('jazigos', 'Jazigo', 'Jazigos', 'Vis.')],
            'permissions_imobiliario' => ['titulo' => 'Módulo: Imobiliário e Geográfico (SIGWEB)', 'rotulo' => 'Gestão de Lotes, Ruas e Zoneamento', 'span' => 'full', 'colunas' => 4,
                'opcoes' => self::quarteto('lotes', 'Lote', 'Lotes', 'Vis.')
                    + self::quarteto('logradouros', 'Rua', 'Ruas', 'Vis.')
                    + self::quarteto('bairros', 'Bairro', 'Bairros', 'Vis.')
                    + self::quarteto('perimetros_urbanos', 'Distrito', 'Distritos', 'Vis.')
                    + self::quarteto('meio_fios', 'Meio-fio', 'Meio-fio', 'Vis.')
                    + self::quarteto('loteamentos', 'Loteam.', 'Loteamentos', 'Vis.')
                    + self::quarteto('quadras', 'Quadra', 'Quadras', 'Vis.')
                    + self::quarteto('zonas', 'Zona', 'Zonas', 'Vis.')
                    + [
                        'gerenciar_areas_reurb' => 'Gerenciar Áreas REURB',
                        'gerenciar_secoes_logradouro' => 'Gerenciar Seções de Logradouro',
                    ]],
            'permissions_coleta' => ['titulo' => 'Coleta Cadastral (campos e regiões)', 'rotulo' => null, 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_campos_customizados' => 'Campos do Município (customizados e padrão)',
                    'gerenciar_atribuicoes_coleta' => 'Atribuições de Região (cadastradores)',
                ]],
            'permissions_social' => ['titulo' => 'Módulo: Cadastro Social', 'rotulo' => 'Gestão de Cadastros Sociais', 'span' => 1, 'colunas' => 1,
                'opcoes' => self::quarteto('cadastros_sociais', 'Cadastro social', 'Cadastros sociais')
                    + ['view_painel_social' => 'Acessar Painel Social (gráfico + mapa)']],
            'permissions_social_aux' => ['titulo' => 'Módulo Social — Cadastros Auxiliares', 'rotulo' => 'Cada permissão libera visualizar, criar, editar e excluir a entidade', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_tipo_rendas' => 'Gerenciar Tipos de Renda',
                    'gerenciar_tipo_entidades' => 'Gerenciar Tipos de Entidade',
                    'gerenciar_entidades' => 'Gerenciar Entidades',
                    'gerenciar_servico_sociais' => 'Gerenciar Serviços Sociais',
                    'gerenciar_programas' => 'Gerenciar Programas',
                    'gerenciar_eventos' => 'Gerenciar Eventos',
                    'gerenciar_informacao_sociais' => 'Gerenciar Informações Sociais',
                    'gerenciar_empreendimentos' => 'Gerenciar Empreendimentos',
                ]],
            'permissions_rural' => ['titulo' => 'Módulo: Cadastro Rural', 'rotulo' => 'Gestão de Zona Rural', 'span' => 'full', 'colunas' => 4,
                'opcoes' => self::quarteto('rural_localidades', 'Localidade', 'Localidades/Distritos', 'Ver')
                    + self::quarteto('rural_propriedades', 'Propriedade', 'Propriedades (CAR/INCRA)', 'Ver')
                    + self::quarteto('rural_estradas', 'Estrada', 'Estradas', 'Ver')
                    + self::quarteto('rural_hidrografias', 'Rio', 'Rios/Lagos', 'Ver')
                    + self::quarteto('rural_pontes', 'Ponte', 'Pontes', 'Ver')
                    + self::quarteto('rural_pontos_interesse', 'Ponto', 'Pontos de Interesse', 'Ver')],
            'permissions_patrimonio' => ['titulo' => 'Módulo de Patrimônios Públicos', 'rotulo' => 'Administração Patrimônios Públicos', 'span' => 'full', 'colunas' => 2,
                'opcoes' => self::quarteto('tipo_patrimonios', 'Tipo', 'Tipos de Patrimônio', 'Ver')
                    + self::quarteto('patrimonio_publicos', 'Patrimônio', 'Patrimônios Públicos', 'Ver')],
            'permissions_bpmn' => ['titulo' => 'Módulo: Processos Digitais (BPMN)', 'rotulo' => 'Fluxos BPMN e Processos', 'span' => 'full', 'colunas' => 2,
                'opcoes' => self::quarteto('bpmn_fluxos', 'Fluxo', 'Fluxos BPMN', 'Ver')
                    + [
                        'view_processos_digitais' => 'Ver Processos Digitais (Caixa de Entrada)',
                        'create_processos_digitais' => 'Criar Processos Digitais',
                        'edit_processos_digitais' => 'Editar Processos Digitais',
                        'delete_processos_digitais' => 'Excluir Processos Digitais',
                        'gerenciar_setores' => 'Gerenciar Setores / Departamentos',
                        'view_todos_processos' => 'Ver TODOS os processos (ignora o filtro por setor)',
                        'view_processos_progresso' => 'Ver Progresso dos Processos (dashboard)',
                    ]],
            'permissions_viabilidade' => ['titulo' => 'Módulo: Consultas de Viabilidade', 'rotulo' => 'CNAEs, Regras de Zoneamento e Parâmetros Urbanísticos', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'view_cnaes' => 'Ver CNAEs e Atividades', 'create_cnaes' => 'Criar CNAEs', 'edit_cnaes' => 'Editar CNAEs', 'delete_cnaes' => 'Excluir CNAEs',
                    'view_regras_zoneamento' => 'Ver Regras de Zoneamento', 'create_regras_zoneamento' => 'Criar Regras de Zoneamento',
                    'edit_regras_zoneamento' => 'Editar Regras de Zoneamento', 'delete_regras_zoneamento' => 'Excluir Regras de Zoneamento',
                    'view_parametros_urbanos' => 'Ver Parâmetros de Loteamento', 'create_parametros_urbanos' => 'Criar Parâmetros de Loteamento',
                    'edit_parametros_urbanos' => 'Editar Parâmetros de Loteamento', 'delete_parametros_urbanos' => 'Excluir Parâmetros de Loteamento',
                    'view_viabilidade_emissoes' => 'Ver Histórico de Emissões',
                ]],
            'permissions_pgv' => ['titulo' => 'Módulo: Gestão Tributária (PGV)', 'rotulo' => 'Parâmetros PGV, Setores Fiscais e Histórico de Valores', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'view_pgv_parametros' => 'Ver Parâmetros Base (PGV)', 'create_pgv_parametros' => 'Criar Parâmetros Base',
                    'edit_pgv_parametros' => 'Editar Parâmetros Base', 'delete_pgv_parametros' => 'Excluir Parâmetros Base',
                    'view_setores_fiscais' => 'Ver Setores Fiscais', 'create_setores_fiscais' => 'Criar Setores Fiscais',
                    'edit_setores_fiscais' => 'Editar Setores Fiscais', 'delete_setores_fiscais' => 'Excluir Setores Fiscais',
                    'view_lote_valor_historicos' => 'Ver Valores Venais (Histórico)', 'create_lote_valor_historicos' => 'Criar Valores Venais',
                    'edit_lote_valor_historicos' => 'Editar Valores Venais', 'delete_lote_valor_historicos' => 'Excluir Valores Venais',
                ]],
            'permissions_pgv_avaliacao' => ['titulo' => 'PGV — Avaliação em Massa', 'rotulo' => 'Cada permissão libera visualizar, criar, editar e excluir a entidade', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_pgv_amostras' => 'Gerenciar Amostras de Mercado',
                    'gerenciar_pgv_polos' => 'Gerenciar Pólos Valorizantes',
                    'gerenciar_pgv_cubs' => 'Gerenciar Tabela CUB',
                    'gerenciar_pgv_depreciacoes' => 'Gerenciar Depreciação',
                    'gerenciar_face_quadras' => 'Gerenciar Faces de Quadra',
                ]],
            'permissions_administracao' => ['titulo' => 'Administração — Páginas Gerenciais', 'rotulo' => 'Acesso às páginas de administração', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'view_auditoria' => 'Auditoria (Histórico de Operações)',
                    'view_monitoramento_campo' => 'Monitoramento de Campo (GPS)',
                    'view_produtividade' => 'Relatório de Produtividade',
                    'view_mensagens' => 'Mensagens (Chat Supervisor ↔ Cadastrador)',
                    'gerenciar_wms' => 'Gerenciar Camadas WMS (fontes e categorias)',
                ]],
            'permissions_chamados' => ['titulo' => 'Módulo: App de Chamados (Gestão do Aplicativo Móvel)', 'rotulo' => 'Gestão de chamados, fluxos e categorias', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_chamados' => 'Gerenciar Chamados (solicitações)',
                    'gerenciar_fluxos_chamado' => 'Gerenciar Fluxos de Trabalho + Fases',
                    'gerenciar_categorias_chamado' => 'Gerenciar Categorias de Chamado',
                ]],
            'permissions_mobilidade' => ['titulo' => 'Módulo: Mobilidade Urbana', 'rotulo' => 'Entidades da mobilidade urbana (permissão única por entidade)', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_mob_trechos' => 'Trechos Viários (levantamento)',
                    'gerenciar_mob_vias' => 'Vias Urbanas (sentido/fluxo)',
                    'gerenciar_mob_sinalizacoes' => 'Sinalização Viária',
                    'gerenciar_mob_tipos_sinalizacao' => 'Catálogo de Tipos de Sinalização',
                    'gerenciar_mob_pontos_interesse' => 'Pontos de Interesse',
                    'gerenciar_mob_eixos' => 'Eixos (Ciclovias, Rotas de Carga, Rodovias)',
                    'gerenciar_mob_zonas' => 'Zonas de Estudo (O/D, Setores IBGE)',
                    'gerenciar_mob_fluxos' => 'Fluxos Origem/Destino',
                    'gerenciar_mob_cameras' => 'Câmeras de Monitoramento (tempo real)',
                ]],
            'permissions_mapa_camadas' => ['titulo' => 'Mapa — Visibilidade de Camadas (ver_camada_*)', 'rotulo' => 'Camadas visíveis no mapa interativo', 'span' => 'full', 'colunas' => 4,
                'ajuda' => 'Deixe vazio = sem restrição (todos veem). Marque para liberar acesso por camada.',
                'opcoes' => [
                    'ver_camada_perimetros' => 'Distritos / Limites',
                    'ver_camada_setores_fiscais' => 'Setores Fiscais',
                    'ver_camada_bairros' => 'Bairros',
                    'ver_camada_loteamentos' => 'Loteamentos',
                    'ver_camada_meio_fios' => 'Meio-fio / Calçada',
                    'ver_camada_secoes_logradouro' => 'Seções de Logradouro',
                    'ver_camada_quadras' => 'Quadras',
                    'ver_camada_lotes' => 'Lotes',
                    'ver_camada_areas_reurb' => 'Áreas REURB',
                    'ver_camada_coleta' => 'Coleta de Dados (status)',
                    'ver_camada_processos' => 'Processos Digitais',
                    'ver_camada_logradouros' => 'Logradouros / Ruas',
                    'ver_camada_postes' => 'Postes / Iluminação',
                    'ver_camada_arvores' => 'Árvores',
                    'ver_camada_zonas' => 'Zonas de Uso (PGV)',
                    'ver_camada_patrimonio_publico' => 'Patrimônio Público',
                    'ver_camada_chamados' => 'Chamados (App)',
                    'ver_camada_cemiterios' => 'Cemitérios',
                    'ver_camada_rural_localidades' => 'Localidades Rurais',
                    'ver_camada_rural_propriedades' => 'Propriedades Rurais',
                    'ver_camada_rural_estradas' => 'Estradas Rurais',
                    'ver_camada_rural_hidrografias' => 'Hidrografia (Rios)',
                    'ver_camada_rural_pontes' => 'Pontes',
                    'ver_camada_rural_pontos_interesse' => 'Pontos de Interesse',
                    'ver_camada_pontos_panoramicos' => 'Pontos Panorâmicos 360°',
                    'ver_camada_toponimias' => 'Toponímias / Textos',
                    'ver_camada_mob_trechos' => 'Mobilidade — Trechos Viários',
                    'ver_camada_mob_vias' => 'Mobilidade — Vias Urbanas (sentido)',
                    'ver_camada_mob_sinalizacoes' => 'Mobilidade — Sinalização',
                    'ver_camada_mob_pontos_interesse' => 'Mobilidade — Pontos de Interesse',
                    'ver_camada_mob_eixos' => 'Mobilidade — Eixos (Ciclo/Carga/Rodovia)',
                    'ver_camada_mob_zonas' => 'Mobilidade — Zonas de Estudo',
                    'ver_camada_mob_fluxos' => 'Mobilidade — Fluxos O/D',
                    'ver_camada_mob_cameras' => 'Mobilidade — Monitoramento em Tempo Real (câmeras)',
                ]],
            'permissions_mapa_toolbar' => ['titulo' => 'Mapa — Toolbar (toolbar_*)', 'rotulo' => 'Seções da barra de ferramentas', 'span' => 'full', 'colunas' => 3,
                'ajuda' => 'Deixe vazio = sem restrição. A pesquisa é sempre visível.',
                'opcoes' => [
                    'toolbar_criar_artefatos' => 'Criar Artefatos (Lotes, Quadras, Ruas...)',
                    'toolbar_ferramentas' => 'Ferramentas (Medição, Impressão, Exportação)',
                    'toolbar_filtros' => 'Filtros e Estatísticas',
                ]],
        ];
    }

    /** Todas as permissões conhecidas pelas caixas (p/ repartir as do papel no fill). */
    public static function permissoesDasCaixas(): array
    {
        $todas = [];
        foreach (self::caixas() as $caixa) {
            $todas = array_merge($todas, array_keys($caixa['opcoes']));
        }

        return $todas;
    }

    public static function form(Form $form): Form
    {
        $componentes = [];
        foreach (self::caixas() as $chave => $caixa) {
            // Caixa de módulo inativo na prefeitura não é oferecida (as permissões já
            // gravadas ficam preservadas — ver EditRole::mutateFormDataBeforeSave)
            $opcoes = Modulos::filtrarOpcoes($caixa['opcoes']);
            if ($opcoes === []) {
                continue;
            }

            $lista = Forms\Components\CheckboxList::make($chave)
                ->options($opcoes)
                ->bulkToggleable()
                ->columns($caixa['colunas'] ?? 1);
            $lista = $caixa['rotulo'] === null ? $lista->hiddenLabel() : $lista->label($caixa['rotulo']);
            if (! empty($caixa['ajuda'])) {
                $lista->helperText($caixa['ajuda']);
            }

            $fieldset = Forms\Components\Fieldset::make($caixa['titulo'])->schema([$lista])->columns(1);
            $componentes[] = ($caixa['span'] ?? 'full') === 'full' ? $fieldset->columnSpanFull() : $fieldset->columnSpan(1);
        }

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Papel')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn (?Role $record) => $record?->ehDeSistema()
                        ? 'Papel de sistema da prefeitura (gestor): pode ser renomeado, mas não excluído.'
                        : null)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                            $tenant = \Filament\Facades\Filament::getTenant();

                            return $rule->where('tenant_id', $tenant?->id);
                        }
                    ),

                // D7: papel que acompanha todos os módulos recebe as permissões de qualquer
                // módulo ligado depois (Manager sempre; qualquer papel pode ter).
                Forms\Components\Toggle::make('todos_modulos')
                    ->label('Acompanha todos os módulos')
                    ->helperText('Ligado: este papel recebe automaticamente todas as permissões dos módulos ativos da prefeitura, inclusive de módulos liberados depois. Desligado: só o que estiver marcado abaixo.')
                    ->inline(false)
                    ->disabled(fn (?Role $record) => $record?->ehManager() ?? false)
                    ->dehydrated(fn (?Role $record) => ! ($record?->ehManager() ?? false))
                    ->live(),

                Forms\Components\Section::make('Permissões de Acesso')
                    ->description(fn (Forms\Get $get) => $get('todos_modulos')
                        ? 'Este papel acompanha todos os módulos: as caixas abaixo são só leitura do que ele recebe automaticamente.'
                        : 'Selecione as permissões organizadas por módulo do sistema. Só aparecem os módulos liberados para esta prefeitura.')
                    ->schema($componentes)
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Papel')
                    ->badge()
                    ->color(fn (Role $record) => $record->ehDeSistema() ? 'danger' : 'warning')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('papel_sistema')
                    ->label('Sistema')
                    ->boolean()
                    ->state(fn (Role $record) => $record->ehDeSistema())
                    ->tooltip('Papel de sistema (gestor da prefeitura): acesso total, não pode ser excluído'),
                Tables\Columns\IconColumn::make('todos_modulos')
                    ->label('Todos os módulos')
                    ->boolean()
                    ->tooltip('Recebe automaticamente as permissões de todo módulo liberado para a prefeitura'),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissões')
                    ->counts('permissions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->hidden(fn (Role $record) => $record->ehDeSistema()),
                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Role $record): bool => ! $record->ehDeSistema(),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
