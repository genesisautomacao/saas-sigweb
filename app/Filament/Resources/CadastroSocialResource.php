<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CadastroSocialResource\Pages;
use App\Models\CadastroSocial;
use App\Models\UnidadeImobiliaria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasTenantModule; // <-- Importando a Trait

class CadastroSocialResource extends Resource
{
    use HasTenantModule; // <-- Usando a Trait

    protected static ?string $tenantModule = 'social'; // <-- Definindo o módulo[cite: 7]

    protected static ?string $model = CadastroSocial::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Cadastro Social';
    protected static ?string $pluralModelLabel = 'Cadastros Sociais';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([

                    // SESSÃO 1: Quem é e Onde Mora
                    Forms\Components\Section::make('Identificação e Moradia')
                        ->description('Vínculo do cidadão com a geografia do município.')
                        ->schema([
                            Forms\Components\Select::make('pessoa_id')
                                ->label('Responsável Familiar (RF)')
                                ->relationship('responsavel', 'name', fn(Builder $query) => $query->where('tenant_id', \Filament\Facades\Filament::getTenant()->id))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->unique(table: 'cadastros_sociais', column: 'pessoa_id', ignoreRecord: true)
                                ->validationMessages([
                                    'unique' => 'Atenção: Esta pessoa já possui um Cadastro Social aberto como Responsável.',
                                ])
                                ->rule(function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $isMembro = \App\Models\MembroFamilia::where('pessoa_id', $value)->exists();
                                        if ($isMembro) {
                                            $fail('Atenção: Esta pessoa já está cadastrada como dependente em outra família. Remova-a de lá primeiro.');
                                        }
                                    };
                                })
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->label('Nome Completo')->required(),
                                    Forms\Components\TextInput::make('cpf')->label('CPF')->mask('999.999.999-99'),
                                    Forms\Components\Hidden::make('type')->default('fisica'),
                                    Forms\Components\Hidden::make('tenant_id')->default(fn() => \Filament\Facades\Filament::getTenant()->id),
                                    Forms\Components\Hidden::make('code')->default(fn() => (string) \Illuminate\Support\Str::uuid()),
                                ]),

                            Forms\Components\Select::make('unidade_imobiliaria_id')
                                ->label('Endereço Físico (Vínculo com o Mapa)')
                                ->options(function () {
                                    // Mostra a Rua, Número e Inscrição para a assistente achar fácil
                                    return UnidadeImobiliaria::where('tenant_id', \Filament\Facades\Filament::getTenant()->id)
                                        ->whereNotNull('logradouro_nome')
                                        ->limit(100) // Limite para não travar, o searchable faz o resto
                                        ->get()
                                        ->mapWithKeys(function ($unidade) {
                                            $endereco = "{$unidade->logradouro_nome}, {$unidade->numero_imovel}";
                                            $inscricao = $unidade->inscricao_imobiliaria ? " (Insc: {$unidade->inscricao_imobiliaria})" : "";
                                            return [$unidade->id => $endereco . $inscricao];
                                        });
                                })
                                ->searchable()
                                ->preload()
                                ->helperText('Ao vincular um endereço, esta família aparecerá no Mapa de Calor da Prefeitura.'),

                            Forms\Components\Select::make('situacao_moradia')
                                ->label('Situação da Moradia')
                                ->options([
                                    'propria' => 'Própria',
                                    'alugada' => 'Alugada',
                                    'cedida' => 'Cedida',
                                    'ocupacao_irregular' => 'Ocupação Irregular (Invasão)',
                                    'situacao_de_rua' => 'Situação de Rua',
                                ])
                                ->default('propria')
                                ->required(),

                            Forms\Components\Select::make('empreendimento_id')
                                ->label('Empreendimento (Moradia de Benefício)')
                                ->options(fn() => \App\Models\Empreendimento::pluck('name', 'id'))
                                ->searchable()
                                ->helperText('Vincule se a família foi contemplada em um empreendimento habitacional.'),
                        ]),

                    // SESSÃO: Terreno próprio (item 095)
                    Forms\Components\Section::make('Terreno da Família')
                        ->description('Informe se a família possui terreno e sua localização geográfica.')
                        ->collapsed()
                        ->schema([
                            Forms\Components\Toggle::make('possui_terreno')
                                ->label('Possui terreno?')
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\Select::make('terreno_loteamento_id')
                                ->label('Loteamento')
                                ->options(fn() => \App\Models\Loteamento::pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn(Forms\Get $get) => $get('possui_terreno')),

                            Forms\Components\Select::make('terreno_quadra_id')
                                ->label('Quadra')
                                ->options(fn() => \App\Models\Quadra::pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn(Forms\Get $get) => $get('possui_terreno')),

                            Forms\Components\Select::make('terreno_lote_id')
                                ->label('Lote')
                                ->options(fn() => \App\Models\Lote::query()->limit(200)->pluck('numero_lote', 'id'))
                                ->searchable()
                                ->visible(fn(Forms\Get $get) => $get('possui_terreno')),

                            Forms\Components\Select::make('terreno_titularidade')
                                ->label('Titularidade')
                                ->options([
                                    'proprio' => 'Próprio (com escritura)',
                                    'posse' => 'Posse',
                                    'cedido' => 'Cedido',
                                    'irregular' => 'Irregular',
                                ])
                                ->visible(fn(Forms\Get $get) => $get('possui_terreno')),
                        ])->columns(2),

                    // SESSÃO 2: Dados Sociais e CadÚnico
                    Forms\Components\Section::make('Indicadores Sociais (CadÚnico)')
                        ->schema([
                            Forms\Components\Select::make('situacao_cadastro')
                                ->label('Situação do Cadastro')
                                ->options([
                                    'cadastrado' => 'Cadastrado',
                                    'beneficiado' => 'Beneficiado',
                                    'aprovado' => 'Aprovado',
                                    'sorteado' => 'Sorteado',
                                    'nao_localizado' => 'Não Localizado',
                                    'apresentou_documentos' => 'Apresentou Documentos',
                                ])
                                ->default('cadastrado')
                                ->required(),

                            Forms\Components\TextInput::make('nis')
                                ->label('Número do NIS')
                                ->maxLength(15),

                            Forms\Components\TextInput::make('quantidade_membros')
                                ->label('Pessoas na Residência')
                                ->numeric()
                                ->default(1)
                                ->required(),

                            Forms\Components\TextInput::make('renda_familiar_total')
                                ->label('Renda Familiar Total (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Calculada automaticamente a partir das rendas dos membros (que compõem a renda familiar).'),
                        ])->columns(3),

                    // SESSÃO 3: Parecer Técnico
                    Forms\Components\Section::make('Parecer Técnico')
                        ->schema([
                            Forms\Components\Textarea::make('observacoes_tecnicas')
                                ->label('Anotações da Assistência Social')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                ])->columnSpan(['lg' => 2]),

                // BARRA LATERAL: Vulnerabilidades (Isso alimenta o mapa!)
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Fatores de Vulnerabilidade')
                        ->description('Estes marcadores acendem alertas no mapa e nos dashboards.')
                        ->schema([
                            Forms\Components\Toggle::make('em_area_de_risco')
                                ->label('Em Área de Risco')
                                ->helperText('Deslizamento, enchente, etc.')
                                ->onColor('danger'),

                            Forms\Components\Toggle::make('recebe_beneficios')
                                ->label('Recebe Benefícios')
                                ->helperText('Bolsa Família, BPC, etc.')
                                ->onColor('info'),

                            Forms\Components\Toggle::make('possui_membro_com_deficiencia')
                                ->label('Membro com Deficiência')
                                ->helperText('Física, mental, autismo, etc.')
                                ->onColor('warning'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('responsavel.name')
                    ->label('Responsável (RF)')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('situacao_cadastro')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'cadastrado' => 'Cadastrado', 'beneficiado' => 'Beneficiado', 'aprovado' => 'Aprovado',
                        'sorteado' => 'Sorteado', 'nao_localizado' => 'Não Localizado',
                        'apresentou_documentos' => 'Apresentou Docs', default => $state ?? '—',
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'beneficiado', 'aprovado' => 'success',
                        'sorteado', 'apresentou_documentos' => 'info',
                        'nao_localizado' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('indice_vulnerabilidade')
                    ->label('Vulnerab.')
                    ->badge()
                    ->formatStateUsing(fn(?int $state) => $state === null ? '—' : $state . '/7')
                    ->color(fn(?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 5 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidadeImobiliaria.logradouro_nome')
                    ->label('Endereço')
                    ->description(fn(CadastroSocial $record): string => $record->unidadeImobiliaria ? "Nº {$record->unidadeImobiliaria->numero_imovel}" : 'Sem endereço fixo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('renda_per_capita')
                    ->label('Renda Per Capita')
                    ->money('BRL')
                    ->sortable(),

                // Nova coluna de Benefícios
                Tables\Columns\IconColumn::make('recebe_beneficios')
                    ->label('Benefícios')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('em_area_de_risco')
                    ->label('Risco')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),

                Tables\Columns\IconColumn::make('possui_membro_com_deficiencia')
                    ->label('PCD')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('situacao_cadastro')
                    ->label('Situação do Cadastro')
                    ->options([
                        'cadastrado' => 'Cadastrado', 'beneficiado' => 'Beneficiado', 'aprovado' => 'Aprovado',
                        'sorteado' => 'Sorteado', 'nao_localizado' => 'Não Localizado', 'apresentou_documentos' => 'Apresentou Documentos',
                    ]),
                Tables\Filters\TernaryFilter::make('em_area_de_risco')->label('Moram em Área de Risco?'),
                Tables\Filters\TernaryFilter::make('recebe_beneficios')->label('Recebem Benefício?'),
                // Novo filtro adicionado
                Tables\Filters\TernaryFilter::make('possui_membro_com_deficiencia')->label('Possui Membro PCD?'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CadastroSocialResource\RelationManagers\MembrosRelationManager::class,
            CadastroSocialResource\RelationManagers\DefinicaoSocialRelationManager::class,
            CadastroSocialResource\RelationManagers\OcorrenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCadastroSocials::route('/'),
            'create' => Pages\CreateCadastroSocial::route('/create'),
            'edit' => Pages\EditCadastroSocial::route('/{record}/edit'),
        ];
    }
}
