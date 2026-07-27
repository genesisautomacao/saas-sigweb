<?php

namespace App\Filament\Resources\BpmnFluxoResource\RelationManagers;

use App\Models\Setor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EtapasRelationManager extends RelationManager
{
    protected static string $relationship = 'etapas';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?string $title = 'Configuração das Etapas (Regras e Formulários)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configurações')
                    ->tabs([
                        // ABA 1: REGRAS E MAPA
                        Forms\Components\Tabs\Tab::make('Regras e Mapa')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('nome')
                                    ->label('Nome da Etapa (Ex: Análise Técnica)')
                                    ->required()
                                    ->maxLength(255),

                                // Fluxo híbrido: quem age nesta etapa (processosConceito.md §9.2)
                                Forms\Components\Select::make('executor')
                                    ->label('Quem preenche/age nesta etapa?')
                                    ->options([
                                        'solicitante' => 'Solicitante (Cidadão)',
                                        'analista' => 'Analista (Setor)',
                                    ])
                                    ->default('analista')
                                    ->required()
                                    ->live() // item 4 — mostra/esconde o setor conforme a escolha
                                    ->helperText('Etapas do solicitante são preenchidas no painel do cidadão; as do analista caem na fila do setor.'),

                                // Item 4 — só aparece quando a etapa é do Setor/Analista
                                Forms\Components\Select::make('setor_id')
                                    ->label('Setor Responsável (Departamento)')
                                    ->relationship('setor', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get) => $get('executor') === 'analista')
                                    ->required(fn (Get $get) => $get('executor') === 'analista')
                                    ->helperText('Usado nas swimlanes e na fila do setor (item 206).'),

                                Forms\Components\TextInput::make('ordem')
                                    ->label('Ordem da Etapa')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Sequência padrão (o roteamento real é livre via tramitação).'),

                                Forms\Components\ColorPicker::make('cor_mapa')
                                    ->label('Cor do Lote no Mapa')
                                    ->required()
                                    ->default('#f59e0b'), // Default Amber/Amarelo

                                Forms\Components\TextInput::make('tempo_medio_minutos')
                                    ->label('SLA / Tempo Médio (dias)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Tempo previsto para conclusão desta fase.'),

                                // PD-5 — o que esta etapa (do analista) exige do checklist de anexos
                                // para poder ser APROVADA. Reprovado bloqueia sempre, em qualquer modo.
                                Forms\Components\Select::make('aprovacao_anexos')
                                    ->label('Aprovação dos anexos para aprovar a etapa')
                                    ->options([
                                        'nao_exige' => 'Não exigir (aprova mesmo com anexos pendentes)',
                                        'novos' => 'Exigir só os anexos novos desde a última análise (ex.: comprovante)',
                                        'todos' => 'Exigir TODOS os anexos do processo aprovados (análise final)',
                                    ])
                                    ->default('todos')
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('executor') === 'analista')
                                    ->helperText('Documento REPROVADO bloqueia a aprovação da etapa em qualquer modo — devolva ao cidadão ou desfaça a reprova.')
                                    ->columnSpanFull(),

                                // PD-1 — em qual etapa do solicitante o requerimento assinado é exigido.
                                // Sem nenhuma etapa marcada, vale a 1ª etapa do fluxo (abertura).
                                Forms\Components\Toggle::make('exige_requerimento')
                                    ->label('Exigir requerimento assinado ao concluir esta etapa')
                                    ->visible(fn (Get $get) => $get('executor') === 'solicitante')
                                    ->helperText('Só tem efeito se o fluxo tiver "Requerimento Assinado" ativado. Use quando as variáveis do requerimento ({{campo:...}}) forem preenchidas nesta etapa, e não na abertura.')
                                    ->columnSpanFull(),

                                // Item 5 — afunilamento por usuário dentro do setor.
                                // Vazio = todos os usuários do setor; preenchido = apenas os escolhidos.
                                Forms\Components\Select::make('usuarios_autorizados')
                                    ->label('Usuários autorizados (opcional — afunilar dentro do setor)')
                                    ->multiple()
                                    ->searchable()
                                    ->visible(fn (Get $get) => $get('executor') === 'analista')
                                    ->options(function (Get $get) {
                                        $setorId = $get('setor_id');
                                        if (! $setorId) {
                                            return [];
                                        }

                                        return Setor::with('users')->find($setorId)?->users->pluck('name', 'id')->toArray() ?? [];
                                    })
                                    ->helperText('Deixe vazio para permitir a TODOS os usuários do setor. Preencha para restringir a usuários específicos.'),
                            ])->columns(2),

                        // ABA 2: O CONSTRUTOR DE FORMULÁRIO DO EDITAL
                        Forms\Components\Tabs\Tab::make('Formulário da Etapa')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Builder::make('campos_formulario')
                                    ->label('Monte o formulário que será exigido nesta etapa:')
                                    ->blocks([
                                        // 1. TEXTO SIMPLES
                                        Forms\Components\Builder\Block::make('texto')
                                            ->label('Campo de Texto Simples')
                                            ->icon('heroicon-m-bars-3-bottom-left')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Preenchimento Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),

                                        // 2. MÚLTIPLA ESCOLHA (type 'checkbox' mantido por compat; renderiza Select multiple)
                                        Forms\Components\Builder\Block::make('checkbox')
                                            ->label('Múltipla Escolha')
                                            ->icon('heroicon-m-list-bullet')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Pergunta')->required(),
                                                Forms\Components\TagsInput::make('opcoes')->label('Opções (Aperte Enter para separar)')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),

                                        // 2b. SELEÇÃO ÚNICA (PD-3) — escolha mutuamente exclusiva (ex.: Estado Civil).
                                        // É o gatilho ideal para a exibição condicional de outros campos.
                                        Forms\Components\Builder\Block::make('selecao')
                                            ->label('Seleção Única (Select)')
                                            ->icon('heroicon-m-chevron-up-down')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Título do Campo (Ex: Estado Civil)')->required(),
                                                Forms\Components\TagsInput::make('opcoes')->label('Opções (Aperte Enter para separar — Ex: Solteiro, Casado)')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),

                                        // 3. MAPA (COORDENADA)
                                        Forms\Components\Builder\Block::make('mapa')
                                            ->label('Seleção de Posição no Mapa')
                                            ->icon('heroicon-m-map-pin')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Instrução (Ex: Marque o poste com defeito)')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),

                                        // 4. MÁSCARAS (CPF/TELEFONE)
                                        Forms\Components\Builder\Block::make('documento')
                                            ->label('Campo com Máscara (CPF/Tel)')
                                            ->icon('heroicon-m-identification')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                                                Forms\Components\Select::make('mascara')
                                                    ->label('Tipo de Máscara')
                                                    ->options([
                                                        'cpf' => 'CPF',
                                                        'cnpj' => 'CNPJ',
                                                        'cpf_cnpj' => 'CPF ou CNPJ (máscara dinâmica)',
                                                        'telefone' => 'Telefone / Celular',
                                                    ])->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),

                                        // 5. UPLOAD DE ARQUIVO (documento nomeado) — item 3
                                        Forms\Components\Builder\Block::make('arquivo')
                                            ->label('Upload de Arquivo (documento nomeado)')
                                            ->icon('heroicon-m-paper-clip')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')
                                                    ->label('Nome do documento (ex.: Foto do CPF, Escritura, Habite-se)')
                                                    ->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                                ...self::camposCondicao(),
                                            ]),
                                    ])
                                    ->addActionLabel('Adicionar Novo Campo')
                                    ->collapsible(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * PD-3 — exibição condicional: par de seletores anexado a TODOS os blocos do construtor.
     * "Mostrar somente se o campo [X] tiver o valor [Y]" — o gatilho é um campo Seleção Única
     * ou Múltipla Escolha DA MESMA ETAPA. Campo oculto não é validado nem exigido.
     * O vínculo é pelo slug do rótulo do gatilho: renomear o gatilho depois quebra a regra.
     */
    protected static function camposCondicao(): array
    {
        return [
            Forms\Components\Fieldset::make('Exibição condicional (opcional)')
                ->schema([
                    Forms\Components\Select::make('visivel_se_campo')
                        ->label('Mostrar somente se o campo...')
                        ->placeholder('Sempre visível')
                        ->live()
                        ->options(function (Get $get) {
                            // '../../' = todos os itens do Builder (os campos desta etapa)
                            return collect($get('../../') ?? [])
                                ->filter(fn ($item) => in_array($item['type'] ?? '', ['selecao', 'checkbox']))
                                ->mapWithKeys(function ($item) {
                                    $label = $item['data']['label_campo'] ?? null;

                                    return filled($label) ? [\Illuminate\Support\Str::slug($label) => $label] : [];
                                })
                                ->toArray();
                        })
                        ->helperText('Gatilhos possíveis: campos "Seleção Única" e "Múltipla Escolha" desta etapa.'),

                    Forms\Components\Select::make('visivel_se_valor')
                        ->label('...tiver o valor')
                        ->visible(fn (Get $get) => filled($get('visivel_se_campo')))
                        ->required(fn (Get $get) => filled($get('visivel_se_campo')))
                        ->options(function (Get $get) {
                            $slugAlvo = $get('visivel_se_campo');
                            $gatilho = collect($get('../../') ?? [])
                                ->first(fn ($item) => \Illuminate\Support\Str::slug($item['data']['label_campo'] ?? '') === $slugAlvo);

                            $opcoes = $gatilho['data']['opcoes'] ?? [];

                            return array_combine($opcoes, $opcoes) ?: [];
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('ordem')->label('#')->sortable(),
                Tables\Columns\ColorColumn::make('cor_mapa')->label('Cor'),
                Tables\Columns\TextColumn::make('nome')->label('Etapa')->weight('bold'),
                Tables\Columns\TextColumn::make('executor')
                    ->label('Executor')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'solicitante' ? 'Cidadão' : 'Analista')
                    ->color(fn ($state) => $state === 'solicitante' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('setor.nome')->label('Setor')->badge()->color('gray')->default('—'),
                Tables\Columns\TextColumn::make('tempo_medio_minutos')->label('SLA (dias)'),
            ])
            // Item 206 — swimlanes: agrupamento visual por setor/departamento
            ->groups([
                Tables\Grouping\Group::make('setor.nome')->label('Setor / Departamento'),
            ])
            ->defaultGroup('setor.nome')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
