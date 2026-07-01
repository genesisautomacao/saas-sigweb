<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $modelLabel = 'Papel de Acesso';
    protected static ?string $pluralModelLabel = 'Papéis de Acesso';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 34;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('name')
                    ->label('Nome do Papel')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            return $rule->where('tenant_id', $tenant?->id);
                        }
                    ),

                Forms\Components\Section::make('Permissões de Acesso')
                    ->description('Selecione as permissões organizadas por módulo do sistema.')
                    ->schema([

                        // CAIXA 1: USUÁRIOS E VENDEDORES
                        Forms\Components\Fieldset::make('Módulo: Equipe (Usuários)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_users')
                                    ->label('Gestão de Usuários')
                                    ->options([
                                        'view_users' => 'Visualizar Usuários',
                                        'create_users' => 'Criar Usuários',
                                        'edit_users' => 'Editar Usuários',
                                        'delete_users' => 'Excluir Usuários',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 2: PAPÉIS
                        Forms\Components\Fieldset::make('Módulo: Segurança (Papéis)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_roles')
                                    ->label('Gestão de Papéis')
                                    ->options([
                                        'view_roles' => 'Visualizar Papéis',
                                        'create_roles' => 'Criar Papéis',
                                        'edit_roles' => 'Editar Papéis',
                                        'delete_roles' => 'Excluir Papéis',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 3: ADMINISTRATIVO - PESSOAS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Pessoas')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_pessoas')
                                    ->label('Gestão de Pessoas')
                                    ->options([
                                        'view_pessoas' => 'Visualizar Pessoas',
                                        'create_pessoas' => 'Criar Pessoas',
                                        'edit_pessoas' => 'Editar Pessoas',
                                        'delete_pessoas' => 'Excluir Pessoas',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 4: ADMINISTRATIVO - CONTATOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Contatos')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_contatos')
                                    ->label('Gestão de Contatos')
                                    ->options([
                                        'view_contatos' => 'Visualizar Contatos',
                                        'create_contatos' => 'Criar Contatos',
                                        'edit_contatos' => 'Editar Contatos',
                                        'delete_contatos' => 'Excluir Contatos',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 5: ADMINISTRATIVO - ENDEREÇOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Endereços')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_enderecos')
                                    ->label('Gestão de Endereços')
                                    ->options([
                                        'view_enderecos' => 'Visualizar Endereços',
                                        'create_enderecos' => 'Criar Endereços',
                                        'edit_enderecos' => 'Editar Endereços',
                                        'delete_enderecos' => 'Excluir Endereços',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 6: ADMINISTRATIVO - DOCUMENTOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Documentos')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_documentos')
                                    ->label('Gestão de Documentos')
                                    ->options([
                                        'view_documentos' => 'Visualizar Documentos',
                                        'create_documentos' => 'Criar Documentos',
                                        'edit_documentos' => 'Editar Documentos',
                                        'delete_documentos' => 'Excluir Documentos',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 6b: ADMINISTRATIVO - PONTOS PANORÂMICOS 360º
                        Forms\Components\Fieldset::make('Módulo Administrativo: Pontos Panorâmicos 360º')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_pontos_panoramicos')
                                    ->label('Gestão de Imagens 360º')
                                    ->options([
                                        'view_pontos_panoramicos'   => 'Visualizar Pontos 360º',
                                        'create_pontos_panoramicos' => 'Criar Pontos 360º',
                                        'edit_pontos_panoramicos'   => 'Editar Pontos 360º',
                                        'delete_pontos_panoramicos' => 'Excluir Pontos 360º',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 7: ILUMINAÇÃO PÚBLICA
                        Forms\Components\Fieldset::make('Módulo: Iluminação Pública')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_iluminacao')
                                    ->label('Gestão de Iluminação')
                                    ->options([
                                        'view_tipos_poste' => 'Visualizar Tipos',
                                        'create_tipos_poste' => 'Criar Tipos',
                                        'edit_tipos_poste' => 'Editar Tipos',
                                        'delete_tipos_poste' => 'Excluir Tipos',
                                        'view_postes' => 'Visualizar Postes',
                                        'create_postes' => 'Criar Postes',
                                        'edit_postes' => 'Editar Postes',
                                        'delete_postes' => 'Excluir Postes',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 8: MEIO AMBIENTE / ARBORIZAÇÃO
                        Forms\Components\Fieldset::make('Módulo: Meio Ambiente (Árvores)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_arborizacao')
                                    ->label('Gestão de Árvores')
                                    ->options([
                                        'view_arvores' => 'Visualizar Árvores',
                                        'create_arvores' => 'Criar Árvores',
                                        'edit_arvores' => 'Editar Árvores',
                                        'delete_arvores' => 'Excluir Árvores',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 9: ESTOQUE
                        Forms\Components\Fieldset::make('Módulo: Estoque')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_estoque')
                                    ->label('Gestão de Estoque')
                                    ->options([
                                        'view_locais_estoque' => 'Vis. Locais',
                                        'create_locais_estoque' => 'Criar Locais',
                                        'edit_locais_estoque' => 'Editar Locais',
                                        'delete_locais_estoque' => 'Excluir Locais',
                                        'view_marcas' => 'Vis. Marcas',
                                        'create_marcas' => 'Criar Marcas',
                                        'edit_marcas' => 'Editar Marcas',
                                        'delete_marcas' => 'Excluir Marcas',
                                        'view_produtos' => 'Vis. Produtos',
                                        'create_produtos' => 'Criar Produtos',
                                        'edit_produtos' => 'Editar Produtos',
                                        'delete_produtos' => 'Excluir Produtos',
                                        'view_estoques' => 'Visualizar Saldos',
                                        'view_movimentacoes' => 'Vis. Movimentações',
                                        'create_movimentacoes' => 'Criar Movimentações',
                                        'edit_movimentacoes' => 'Editar Movimentações',
                                        'delete_movimentacoes' => 'Excluir Movimentações',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(4),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 9b: ESTOQUE — CADASTROS AUXILIARES (permissão única "gerenciar")
                        Forms\Components\Fieldset::make('Módulo: Estoque — Cadastros Auxiliares')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_estoque_cadastros')
                                    ->label('Cada permissão libera visualizar, criar, editar e excluir a entidade')
                                    ->options([
                                        'gerenciar_estabelecimentos' => 'Gerenciar Estabelecimentos',
                                        'gerenciar_fabricantes'      => 'Gerenciar Fabricantes',
                                        'gerenciar_fornecedores'     => 'Gerenciar Fornecedores',
                                        'gerenciar_unidade_medidas'  => 'Gerenciar Unidades de Medida',
                                        'gerenciar_embalagens'       => 'Gerenciar Embalagens',
                                        'gerenciar_familia_produtos' => 'Gerenciar Famílias de Produto',
                                        'gerenciar_tipo_estoques'    => 'Gerenciar Tipos de Estoque',
                                        'gerenciar_operacao_internas' => 'Gerenciar Operações Internas',
                                        'gerenciar_lote_estoques'    => 'Gerenciar Lotes / Séries',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(3),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 10: MANUTENÇÃO E SERVIÇOS
                        Forms\Components\Fieldset::make('Módulo: Manutenção e Serviços')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_manutencao')
                                    ->label('Gestão de Manutenção (O.S.)')
                                    ->options([
                                        'view_solicitacoes' => 'Vis. Solicitações',
                                        'create_solicitacoes' => 'Criar Solicitações',
                                        'edit_solicitacoes' => 'Editar Solicitações',
                                        'delete_solicitacoes' => 'Excluir Solicitações',
                                        'view_ordens_servico' => 'Vis. Ordens (OS)',
                                        'create_ordens_servico' => 'Criar Ordens (OS)',
                                        'edit_ordens_servico' => 'Editar Ordens (OS)',
                                        'delete_ordens_servico' => 'Excluir Ordens (OS)',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // 🟢 CAIXA 11: CEMITÉRIOS (NOVO)
                        Forms\Components\Fieldset::make('Módulo: Gestão de Cemitérios')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_cemiterio')
                                    ->label('Administração de Cemitérios e Jazigos')
                                    ->options([
                                        'view_cemiterios' => 'Vis. Cemitérios',
                                        'create_cemiterios' => 'Criar Cemitérios',
                                        'edit_cemiterios' => 'Editar Cemitérios',
                                        'delete_cemiterios' => 'Excluir Cemitérios',

                                        'view_quadras_cemiterio' => 'Vis. Quadras',
                                        'create_quadras_cemiterio' => 'Criar Quadras',
                                        'edit_quadras_cemiterio' => 'Editar Quadras',
                                        'delete_quadras_cemiterio' => 'Excluir Quadras',

                                        'view_logradouros_cemiterio' => 'Vis. Ruas',
                                        'create_logradouros_cemiterio' => 'Criar Ruas',
                                        'edit_logradouros_cemiterio' => 'Editar Ruas',
                                        'delete_logradouros_cemiterio' => 'Excluir Ruas',

                                        'view_jazigos' => 'Vis. Jazigos',
                                        'create_jazigos' => 'Criar Jazigos',
                                        'edit_jazigos' => 'Editar Jazigos',
                                        'delete_jazigos' => 'Excluir Jazigos',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(4), // Bem largo para aproveitar a tela
                            ])->columns(1)->columnSpanFull(),

                        // 🟢 CAIXA 12: MÓDULO IMOBILIÁRIO E GEOGRÁFICO
                        Forms\Components\Fieldset::make('Módulo: Imobiliário e Geográfico (SIGWEB)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_imobiliario')
                                    ->label('Gestão de Lotes, Ruas e Zoneamento')
                                    ->options([
                                        'view_lotes' => 'Vis. Lotes',
                                        'create_lotes' => 'Criar Lotes',
                                        'edit_lotes' => 'Editar Lotes',
                                        'delete_lotes' => 'Excluir Lotes',

                                        'view_logradouros' => 'Vis. Ruas',
                                        'create_logradouros' => 'Criar Ruas',
                                        'edit_logradouros' => 'Editar Ruas',
                                        'delete_logradouros' => 'Excluir Ruas',

                                        'view_bairros' => 'Vis. Bairros',
                                        'create_bairros' => 'Criar Bairros',
                                        'edit_bairros' => 'Editar Bairros',
                                        'delete_bairros' => 'Excluir Bairros',

                                        'view_perimetros_urbanos' => 'Vis. Distritos',
                                        'create_perimetros_urbanos' => 'Criar Distritos',
                                        'edit_perimetros_urbanos' => 'Editar Distritos',
                                        'delete_perimetros_urbanos' => 'Excluir Distritos',

                                        'view_meio_fios' => 'Vis. Meio-fio',
                                        'create_meio_fios' => 'Criar Meio-fio',
                                        'edit_meio_fios' => 'Editar Meio-fio',
                                        'delete_meio_fios' => 'Excluir Meio-fio',

                                        'view_loteamentos' => 'Vis. Loteamentos',
                                        'create_loteamentos' => 'Criar Loteam.',
                                        'edit_loteamentos' => 'Editar Loteam.',
                                        'delete_loteamentos' => 'Excluir Loteam.',

                                        'view_quadras' => 'Vis. Quadras',
                                        'create_quadras' => 'Criar Quadras',
                                        'edit_quadras' => 'Editar Quadras',
                                        'delete_quadras' => 'Excluir Quadras',

                                        'view_zonas' => 'Vis. Zonas',
                                        'create_zonas' => 'Criar Zonas',
                                        'edit_zonas' => 'Editar Zonas',
                                        'delete_zonas' => 'Excluir Zonas',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(4),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 13: CADASTRO SOCIAL
                        Forms\Components\Fieldset::make('Módulo: Cadastro Social')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_social')
                                    ->label('Gestão de Cadastros Sociais')
                                    ->options([
                                        'view_cadastros_sociais' => 'Visualizar Cadastros sociais',
                                        'create_cadastros_sociais' => 'Criar Cadastros sociais',
                                        'edit_cadastros_sociais' => 'Editar Cadastros sociais',
                                        'delete_cadastros_sociais' => 'Excluir Cadastros sociais',
                                        'view_painel_social' => 'Acessar Painel Social (gráfico + mapa)',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 13b: SOCIAL — CADASTROS AUXILIARES (permissão única "gerenciar")
                        Forms\Components\Fieldset::make('Módulo Social — Cadastros Auxiliares')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_social_aux')
                                    ->label('Cada permissão libera visualizar, criar, editar e excluir a entidade')
                                    ->options([
                                        'gerenciar_tipo_rendas' => 'Gerenciar Tipos de Renda',
                                        'gerenciar_tipo_entidades' => 'Gerenciar Tipos de Entidade',
                                        'gerenciar_entidades' => 'Gerenciar Entidades',
                                        'gerenciar_servico_sociais' => 'Gerenciar Serviços Sociais',
                                        'gerenciar_programas' => 'Gerenciar Programas',
                                        'gerenciar_eventos' => 'Gerenciar Eventos',
                                        'gerenciar_informacao_sociais' => 'Gerenciar Informações Sociais',
                                        'gerenciar_empreendimentos' => 'Gerenciar Empreendimentos',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 14: MÓDULO RURAL
                        Forms\Components\Fieldset::make('Módulo: Imobiliário e Geográfico (SIGWEB)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_rural')
                                    ->label('Gestão de Zona Rural')
                                    ->options([
                                        'view_rural_localidades' => 'Ver Localidades/Distritos',
                                        'create_rural_localidades' => 'Criar Localidades/Distritos',
                                        'edit_rural_localidades' => 'Editar Localidades/Distritos',
                                        'delete_rural_localidades' => 'Excluir Localidades/Distritos',

                                        'view_rural_propriedades' => 'Ver Propriedades (CAR/INCRA)',
                                        'create_rural_propriedades' => 'Criar Propriedades (CAR/INCRA)',
                                        'edit_rural_propriedades' => 'Editar Propriedades (CAR/INCRA)',
                                        'delete_rural_propriedades' => 'Excluir Propriedades (CAR/INCRA)',

                                        'view_rural_estradas' => 'Ver Estradas',
                                        'create_rural_estradas' => 'Criar Estradas',
                                        'edit_rural_estradas' => 'Editar Estradas',
                                        'delete_rural_estradas' => 'Excluir Estradas',

                                        'view_rural_hidrografias' => 'Ver Rios/Lagos',
                                        'create_rural_hidrografias' => 'Criar Rios/Lagos',
                                        'edit_rural_hidrografias' => 'Editar Rios/Lagos',
                                        'delete_rural_hidrografias' => 'Excluir Rios/Lagos',

                                        'view_rural_pontes' => 'Ver Pontes',
                                        'create_rural_pontes' => 'Criar Pontes',
                                        'edit_rural_pontes' => 'Editar Pontes',
                                        'delete_rural_pontes' => 'Excluir Pontes',

                                        'view_rural_pontos_interesse' => 'Ver Pontos de Interesse',
                                        'create_rural_pontos_interesse' => 'Criar Pontos de Interesse',
                                        'edit_rural_pontos_interesse' => 'Editar Pontos de Interesse',
                                        'delete_rural_pontos_interesse' => 'Excluir Pontos de Interesse',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(4),
                            ])->columns(1)->columnSpanFull(),

                        //CAIXA 15: PATRIMÔNIOS PÚBLICOS
                        Forms\Components\Fieldset::make('Módulo de Patrimônios Públicos')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_patrimonio')
                                    ->label('Administração Patrimônios Públicos')
                                    ->options([
                                        'view_tipo_patrimonios' => 'Ver Tipos de Patrimônio',
                                        'create_tipo_patrimonios' => 'Criar Tipos de Patrimônio',
                                        'edit_tipo_patrimonios' => 'Editar Tipos de Patrimônio',
                                        'delete_tipo_patrimonios' => 'Excluir Tipos de Patrimônio',

                                        'view_patrimonio_publicos' => 'Ver Patrimônios Públicos',
                                        'create_patrimonio_publicos' => 'Criar Patrimônios Públicos',
                                        'edit_patrimonio_publicos' => 'Editar Patrimônios Públicos',
                                        'delete_patrimonio_publicos' => 'Excluir Patrimônios Públicos',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 16: BPMN E PROCESSOS DIGITAIS
                        Forms\Components\Fieldset::make('Módulo: Processos Digitais (BPMN)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_bpmn')
                                    ->label('Fluxos BPMN e Processos')
                                    ->options([
                                        'view_bpmn_fluxos'          => 'Ver Fluxos BPMN',
                                        'create_bpmn_fluxos'        => 'Criar Fluxos BPMN',
                                        'edit_bpmn_fluxos'          => 'Editar Fluxos BPMN',
                                        'delete_bpmn_fluxos'        => 'Excluir Fluxos BPMN',
                                        'view_processos_digitais'   => 'Ver Processos Digitais',
                                        'create_processos_digitais' => 'Criar Processos Digitais',
                                        'edit_processos_digitais'   => 'Editar Processos Digitais',
                                        'delete_processos_digitais' => 'Excluir Processos Digitais',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 17: CONSULTAS DE VIABILIDADE
                        Forms\Components\Fieldset::make('Módulo: Consultas de Viabilidade')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_viabilidade')
                                    ->label('CNAEs, Regras de Zoneamento e Parâmetros Urbanísticos')
                                    ->options([
                                        'view_cnaes'                 => 'Ver CNAEs e Atividades',
                                        'create_cnaes'               => 'Criar CNAEs',
                                        'edit_cnaes'                 => 'Editar CNAEs',
                                        'delete_cnaes'               => 'Excluir CNAEs',
                                        'view_regras_zoneamento'     => 'Ver Regras de Zoneamento',
                                        'create_regras_zoneamento'   => 'Criar Regras de Zoneamento',
                                        'edit_regras_zoneamento'     => 'Editar Regras de Zoneamento',
                                        'delete_regras_zoneamento'   => 'Excluir Regras de Zoneamento',
                                        'view_parametros_urbanos'    => 'Ver Parâmetros de Loteamento',
                                        'create_parametros_urbanos'  => 'Criar Parâmetros de Loteamento',
                                        'edit_parametros_urbanos'    => 'Editar Parâmetros de Loteamento',
                                        'delete_parametros_urbanos'  => 'Excluir Parâmetros de Loteamento',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 18: GESTÃO TRIBUTÁRIA (PGV)
                        Forms\Components\Fieldset::make('Módulo: Gestão Tributária (PGV)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_pgv')
                                    ->label('Parâmetros PGV, Setores Fiscais e Histórico de Valores')
                                    ->options([
                                        'view_pgv_parametros'          => 'Ver Parâmetros Base (PGV)',
                                        'create_pgv_parametros'        => 'Criar Parâmetros Base',
                                        'edit_pgv_parametros'          => 'Editar Parâmetros Base',
                                        'delete_pgv_parametros'        => 'Excluir Parâmetros Base',
                                        'view_setores_fiscais'         => 'Ver Setores Fiscais',
                                        'create_setores_fiscais'       => 'Criar Setores Fiscais',
                                        'edit_setores_fiscais'         => 'Editar Setores Fiscais',
                                        'delete_setores_fiscais'       => 'Excluir Setores Fiscais',
                                        'view_lote_valor_historicos'   => 'Ver Valores Venais (Histórico)',
                                        'create_lote_valor_historicos' => 'Criar Valores Venais',
                                        'edit_lote_valor_historicos'   => 'Editar Valores Venais',
                                        'delete_lote_valor_historicos' => 'Excluir Valores Venais',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 19: PÁGINAS DE ADMINISTRAÇÃO
                        Forms\Components\Fieldset::make('Administração — Páginas Gerenciais')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_administracao')
                                    ->label('Acesso às páginas de administração')
                                    ->options([
                                        'view_auditoria'          => 'Auditoria (Histórico de Operações)',
                                        'view_monitoramento_campo' => 'Monitoramento de Campo (GPS)',
                                        'view_produtividade'      => 'Relatório de Produtividade',
                                        'view_mensagens'          => 'Mensagens (Chat Supervisor ↔ Cadastrador)',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(2),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 20: VISIBILIDADE DE CAMADAS NO MAPA
                        Forms\Components\Fieldset::make('Mapa — Visibilidade de Camadas (ver_camada_*)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_mapa_camadas')
                                    ->label('Camadas visíveis no mapa interativo')
                                    ->helperText('Deixe vazio = sem restrição (todos veem). Marque para liberar acesso por camada.')
                                    ->options([
                                        'ver_camada_perimetros'            => 'Distritos / Limites',
                                        'ver_camada_setores_fiscais'       => 'Setores Fiscais',
                                        'ver_camada_bairros'               => 'Bairros',
                                        'ver_camada_loteamentos'           => 'Loteamentos',
                                        'ver_camada_meio_fios'             => 'Meio-fio / Calçada',
                                        'ver_camada_secoes_logradouro'     => 'Seções de Logradouro',
                                        'ver_camada_quadras'               => 'Quadras',
                                        'ver_camada_lotes'                 => 'Lotes',
                                        'ver_camada_logradouros'           => 'Logradouros / Ruas',
                                        'ver_camada_postes'                => 'Postes / Iluminação',
                                        'ver_camada_arvores'               => 'Árvores',
                                        'ver_camada_zonas'                 => 'Zonas de Uso (PGV)',
                                        'ver_camada_patrimonio_publico'    => 'Patrimônio Público',
                                        'ver_camada_cemiterios'            => 'Cemitérios',
                                        'ver_camada_rural_localidades'     => 'Localidades Rurais',
                                        'ver_camada_rural_propriedades'    => 'Propriedades Rurais',
                                        'ver_camada_rural_estradas'        => 'Estradas Rurais',
                                        'ver_camada_rural_hidrografias'    => 'Hidrografia (Rios)',
                                        'ver_camada_rural_pontes'          => 'Pontes',
                                        'ver_camada_rural_pontos_interesse' => 'Pontos de Interesse',
                                        'ver_camada_pontos_panoramicos'    => 'Pontos Panorâmicos 360°',
                                        'ver_camada_toponimias'            => 'Toponímias / Textos',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(4),
                            ])->columns(1)->columnSpanFull(),

                        // CAIXA 17: FERRAMENTAS DO MAPA (TOOLBAR)
                        Forms\Components\Fieldset::make('Mapa — Toolbar (toolbar_*)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_mapa_toolbar')
                                    ->label('Seções da barra de ferramentas')
                                    ->helperText('Deixe vazio = sem restrição. A pesquisa é sempre visível.')
                                    ->options([
                                        'toolbar_criar_artefatos' => 'Criar Artefatos (Lotes, Quadras, Ruas...)',
                                        'toolbar_ferramentas'     => 'Ferramentas (Medição, Impressão, Exportação)',
                                        'toolbar_filtros'         => 'Filtros e Estatísticas',
                                    ])
                                    ->bulkToggleable()
                                    ->columns(3),
                            ])->columns(1)->columnSpanFull(),

                    ])->columns(4),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Papel')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->hidden(fn(\App\Models\Role $record) => in_array($record->name, ['Master', 'Manager'])),
                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn(\App\Models\Role $record): bool => !in_array($record->name, ['Master', 'Manager']),
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
