<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Support\MapaPermissoes;
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
 *
 * R83 (2026-10-12): as caixas dos módulos GIS viraram MATRIZES por entidade
 * (Menu · Mapa · Criar · Editar · Excluir), montadas do catálogo config/mapa.php
 * (MapaPermissoes) — a mesma permissão vale na tela do menu e no mapa. As antigas
 * caixas "Mapa — Camadas" e "Mapa — Toolbar" saíram: ver camada é a coluna "Mapa"
 * de cada entidade e as ferramentas têm a caixa "Mapa — Ferramentas", uma por item.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'Papel de Acesso';

    protected static ?string $pluralModelLabel = 'Papéis de Acesso';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 34;

    /** Colunas da matriz (R83) — chave do catálogo => rótulo da coluna. */
    public const COLUNAS_MATRIZ = [
        'menu' => 'Menu',
        'mapa' => 'Mapa',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
    ];

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
     * Linhas da matriz de um módulo, a partir do catálogo do mapa (config/mapa.php).
     * Entidade que é só camada de leitura (sem tela nem CRUD — coleta, processos,
     * chamados) NÃO vira linha: entra como opção simples na caixa do seu módulo.
     */
    public static function matrizDoModulo(string $modulo): array
    {
        $linhas = [];
        $entidades = MapaPermissoes::entidades();
        foreach ($entidades as $chave => $def) {
            if (($def['modulo'] ?? null) !== $modulo) {
                continue;
            }
            if (empty($def['menu']) && empty($def['criar'])) {
                continue; // camada só leitura — opção simples na caixa do módulo
            }
            $linhas[] = [
                'chave' => $chave,
                'label' => (string) ($def['label'] ?? $chave),
                'nota' => $def['nota'] ?? null,
                'menu' => $def['menu'] ?? null,
                // camada compartilhada (quadras/ruas/jazigos de cemitério): a coluna fica na entidade-mãe
                'mapa' => empty($def['mapa_de']) ? ($def['mapa'] ?? null) : null,
                'mapa_de' => ! empty($def['mapa_de']) ? (string) ($entidades[$def['mapa_de']]['label'] ?? $def['mapa_de']) : null,
                'criar' => $def['criar'] ?? null,
                'editar' => $def['editar'] ?? null,
                'excluir' => $def['excluir'] ?? null,
            ];
        }

        return $linhas;
    }

    /** Opções da caixa "Mapa — Ferramentas": uma permissão por item, rotulada pelo grupo. */
    public static function opcoesFerramentas(): array
    {
        $opcoes = [];
        foreach (MapaPermissoes::ferramentas() as $id => $def) {
            $exige = [];
            foreach ((array) ($def['exige'] ?? []) as $entidade => $acao) {
                $exige[] = $acao.' '.(MapaPermissoes::entidade((string) $entidade)['label'] ?? $entidade);
            }
            $opcoes[MapaPermissoes::permissaoFerramenta((string) $id)] = ($def['grupo'] ?? 'Ferramentas').' · '.($def['label'] ?? $id)
                .($exige ? ' (exige '.implode(', ', $exige).')' : '');
        }

        return $opcoes;
    }

    /**
     * Definição das caixas: chave (campo do form) => [titulo, rotulo (null = sem rótulo),
     * opcoes (permissão => rótulo) OU matriz (linhas de matrizDoModulo) + modulo, colunas,
     * span ('full'|1), ajuda?].
     */
    public static function caixas(): array
    {
        $matriz = fn (string $modulo, string $titulo, ?string $ajuda = null) => [
            'titulo' => $titulo, 'rotulo' => null, 'span' => 'full', 'modulo' => $modulo,
            'matriz' => self::matrizDoModulo($modulo), 'opcoes' => [],
            'ajuda' => $ajuda ?? 'Menu = ver a tela; Mapa = ver a camada; Criar/Editar/Excluir valem no mapa E na tela.',
        ];

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

            // ── GIS em matriz (R83) — nomes D8, mesma ordem da sidebar ──
            'permissions_base_cartografica' => $matriz('base_cartografica', 'GIS - Base Cartográfica'),
            'permissions_imobiliario' => $matriz('imobiliario', 'GIS - Cadastro Imobiliário',
                'Edificações, testadas, unidades e fotos seguem as permissões do Lote. Criar/Editar/Excluir valem no mapa E na tela.'),
            'permissions_coleta' => ['titulo' => 'Coleta Cadastral (campos, regiões e camada)', 'rotulo' => null, 'span' => 'full', 'colunas' => 3,
                'opcoes' => [
                    'gerenciar_campos_customizados' => 'Campos do Município (customizados e padrão)',
                    'gerenciar_atribuicoes_coleta' => 'Atribuições de Região (cadastradores)',
                    'ver_camada_coleta' => 'Ver a camada "Coleta de Dados" (status dos lotes) no mapa',
                ]],
            'permissions_imageamento' => $matriz('imageamento', 'GIS - Imageamento (360° e 3D)'),
            'permissions_mobilidade' => $matriz('mob_infra', 'Mobilidade Urbana',
                'Fluxos O/D são só leitura no mapa (a tela do menu é permissão única). Catálogo de sinalização não tem camada.'),
            'permissions_iluminacao' => $matriz('iluminacao', 'Módulo: Iluminação Pública'),
            'permissions_arborizacao' => $matriz('arborizacao', 'Módulo: Meio Ambiente (Árvores)'),
            'permissions_estoque' => ['titulo' => 'Módulo: Estoque', 'rotulo' => 'Gestão de Estoque', 'span' => 'full', 'colunas' => 3,
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
            'permissions_cemiterio' => $matriz('cemiterio', 'Módulo: Gestão de Cemitérios',
                'A camada do mapa é uma só (Cemitérios): quadras, ruas e jazigos aparecem junto com ela.'),
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
            'permissions_rural' => $matriz('rural', 'Módulo: Cadastro Rural'),
            'permissions_patrimonio' => $matriz('patrimonios', 'Módulo de Patrimônios Públicos'),
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
                        'ver_camada_processos' => 'Ver a camada "Processos Digitais" no mapa',
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
            'permissions_pgv' => $matriz('pgv', 'Módulo: Gestão Tributária (PGV)',
                'Amostras, pólos e faces nascem no mapa (Ferramentas / modal da quadra); CUB e depreciação são telas de permissão única.'),
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
                    'ver_camada_chamados' => 'Ver a camada "Chamados (App)" no mapa',
                ]],
            'permissions_chamados_mapa' => ['titulo' => 'Módulo: Chamados pelo Mapa Público', 'rotulo' => 'Chamados abertos pelo cidadão no mapa público', 'span' => 'full', 'colunas' => 2,
                'opcoes' => [
                    'gerenciar_chamados_mapa' => 'Gerenciar Chamados pelo Mapa (ver, alterar situação, responder)',
                    'ver_camada_chamados_mapa' => 'Ver a camada "Chamados pelo Mapa" no mapa',
                ]],
            'permissions_anotacoes' => $matriz(Modulos::NUCLEO, 'Mapa — Anotações',
                'Textos/toponímias soltos no mapa (núcleo, qualquer prefeitura).'),
            'permissions_mapa_ferramentas' => ['titulo' => 'Mapa — Ferramentas (uma permissão por item)', 'rotulo' => null, 'span' => 'full', 'colunas' => 3,
                'ajuda' => 'O menu Ferramentas aparece quando o papel tem ao menos um item. Ferramenta que grava exige TAMBÉM a permissão da entidade (entre parênteses). "Criar Artefatos" não tem permissão própria: vem de Criar em cada entidade.',
                'opcoes' => self::opcoesFerramentas()],
        ];
    }

    /** Permissões de uma caixa: opções simples + todas as células da matriz (R83). */
    public static function permissoesDaCaixa(array $caixa): array
    {
        $perms = array_keys((array) ($caixa['opcoes'] ?? []));
        foreach ((array) ($caixa['matriz'] ?? []) as $linha) {
            foreach (array_keys(self::COLUNAS_MATRIZ) as $col) {
                if (! empty($linha[$col])) {
                    $perms[] = $linha[$col];
                }
            }
        }

        return array_values(array_unique($perms));
    }

    /** Todas as permissões conhecidas pelas caixas (p/ repartir as do papel no fill). */
    public static function permissoesDasCaixas(): array
    {
        $todas = [];
        foreach (self::caixas() as $caixa) {
            $todas = array_merge($todas, self::permissoesDaCaixa($caixa));
        }

        return array_values(array_unique($todas));
    }

    public static function form(Form $form): Form
    {
        // R83 (2026-10-13): papel que acompanha todos os módulos (Manager sempre) recebe TUDO no
        // save e o Manager ainda tem bypass — as caixas ficam travadas para a tela não aceitar
        // cliques que não valem (o usuário "restringiu" o gerente e nada mudou).
        $travado = fn (Forms\Get $get): bool => (bool) $get('todos_modulos');

        $componentes = [];
        foreach (self::caixas() as $chave => $caixa) {
            if (! empty($caixa['matriz'])) {
                // R83: caixa em matriz — some inteira se o módulo estiver inativo na prefeitura
                // (as permissões já gravadas ficam preservadas — EditRole::mutateFormDataBeforeSave)
                if (! Modulos::ativo((string) ($caixa['modulo'] ?? Modulos::NUCLEO))) {
                    continue;
                }
                $linhas = $caixa['matriz'];
                $campo = Forms\Components\ViewField::make($chave)
                    ->view('filament.forms.components.matriz-permissoes')
                    ->viewData(fn (Forms\Get $get): array => [
                        'linhas' => $linhas,
                        'colunas' => self::COLUNAS_MATRIZ,
                        'somenteLeitura' => $travado($get),
                    ])
                    ->hiddenLabel()
                    ->default([]);
                if (! empty($caixa['ajuda'])) {
                    $campo->helperText($caixa['ajuda']);
                }
                $componentes[] = Forms\Components\Fieldset::make($caixa['titulo'])->schema([$campo])->columns(1)->columnSpanFull();

                continue;
            }

            // Caixa de módulo inativo na prefeitura não é oferecida (as permissões já
            // gravadas ficam preservadas — ver EditRole::mutateFormDataBeforeSave)
            $opcoes = Modulos::filtrarOpcoes($caixa['opcoes']);
            if ($opcoes === []) {
                continue;
            }

            $lista = Forms\Components\CheckboxList::make($chave)
                ->options($opcoes)
                ->bulkToggleable()
                ->disabled($travado)
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
                    ->description(function (Forms\Get $get, ?Role $record): string {
                        if ($record?->ehManager()) {
                            return 'Papel de sistema (gestor da prefeitura): tem acesso TOTAL ao mapa e às telas, independentemente das caixas — elas ficam travadas e só mostram o que ele recebe. Para um gestor com restrições, crie um papel comum sem "Acompanha todos os módulos".';
                        }

                        return $get('todos_modulos')
                            ? 'Este papel acompanha todos os módulos: recebe automaticamente todas as permissões dos módulos ativos ao salvar, então as caixas ficam travadas. Desligue o toggle acima para escolher permissão a permissão.'
                            : 'Selecione as permissões organizadas por módulo do sistema. Só aparecem os módulos liberados para esta prefeitura. Nas matrizes: Menu = ver a tela, Mapa = ver a camada, Criar/Editar/Excluir valem no mapa e na tela.';
                    })
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
