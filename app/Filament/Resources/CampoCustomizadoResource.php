<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampoCustomizadoResource\Pages;
use App\Models\CampoCustomizado;
use App\Services\Coleta\CampoCustomizadoService;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * R67-1 — o município define campos adicionais para lote/edificação/unidade imobiliária.
 * Aparecem nos formulários web, no boletim do app, nos relatórios e na importação GIS.
 */
class CampoCustomizadoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';

    protected static ?string $model = CampoCustomizado::class;

    // Multi-tenant: nome da relação no model Tenant (o plural automático "campoCustomizados" não existe)
    protected static ?string $tenantRelationshipName = 'camposCustomizados';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Customizações';

    protected static ?string $modelLabel = 'Campo Customizado';

    protected static ?string $pluralModelLabel = 'Campos Customizados';

    protected static ?string $slug = 'campos-customizados';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Definição do campo')
                ->description('Campos adicionais aos que o sistema já oferece. O identificador é usado no app, nos relatórios e como propriedade do GeoJSON na importação.')
                ->schema([
                    Forms\Components\Select::make('entidade')
                        ->label('Onde este campo aparece?')
                        ->options(CampoCustomizado::ENTIDADES)
                        ->required()
                        ->live()
                        ->disabled(fn (?CampoCustomizado $record) => $record !== null)
                        ->helperText('Não pode ser alterado depois de criado.'),

                    Forms\Components\TextInput::make('label')
                        ->label('Nome do campo (o que o usuário vê)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?CampoCustomizado $record) {
                            if ($record === null && filled($state)) {
                                $set('slug', Str::slug($state, '_'));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Identificador técnico')
                        ->required()
                        ->maxLength(60)
                        ->disabled(fn (?CampoCustomizado $record) => $record !== null)
                        ->dehydrated()
                        ->rules(['regex:/^[a-z][a-z0-9_]*$/'])
                        ->helperText('Só letras minúsculas, números e _ . É a chave dos dados: NÃO muda depois de criado (o app e os arquivos importados dependem dele).')
                        ->unique(
                            table: 'campos_customizados',
                            column: 'slug',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule, Get $get) => $rule
                                ->where('tenant_id', Filament::getTenant()?->id)
                                ->where('entidade', $get('entidade'))
                                ->whereNull('deleted_at'),
                        )
                        ->validationMessages(['unique' => 'Já existe um campo com este identificador nesta entidade.'])
                        ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $reservadas = CampoCustomizadoService::colunasReservadas((string) $get('entidade'));
                            if (in_array($value, $reservadas, true)) {
                                $fail('Este identificador é reservado pelo sistema. Escolha outro.');
                            }
                        }),

                    Forms\Components\Select::make('tipo')
                        ->label('Tipo de preenchimento')
                        ->options(CampoCustomizado::TIPOS)
                        ->default('texto')
                        ->required()
                        ->live()
                        ->native(false),

                    Forms\Components\TagsInput::make('opcoes')
                        ->label('Opções (Enter para separar)')
                        ->visible(fn (Get $get) => in_array($get('tipo'), ['selecao', 'multipla']))
                        ->required(fn (Get $get) => in_array($get('tipo'), ['selecao', 'multipla']))
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Comportamento')
                ->description('A exibição no app de coleta é definida em "Coleta cadastral → Boletim de Coleta".')
                ->schema([
                    Forms\Components\Toggle::make('obrigatorio')->label('Preenchimento obrigatório')->default(false),
                    Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
                    Forms\Components\TextInput::make('ordem')->label('Ordem de exibição')->numeric()->default(0),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('entidade')
                    ->label('Entidade')
                    ->badge()
                    ->formatStateUsing(fn ($state) => CampoCustomizado::ENTIDADES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'lote' => 'primary',
                        'edificacao' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('label')->label('Campo')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Identificador')->copyable()->color('gray'),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => CampoCustomizado::TIPOS[$state] ?? $state),
                Tables\Columns\IconColumn::make('obrigatorio')->label('Obrigatório')->boolean(),
                Tables\Columns\IconColumn::make('na_coleta')
                    ->label('No app de coleta')
                    ->boolean()
                    ->tooltip('Configurado em Coleta cadastral → Boletim de Coleta'),
                Tables\Columns\ToggleColumn::make('ativo')->label('Ativo'),
                Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entidade')->label('Entidade')->options(CampoCustomizado::ENTIDADES),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhum campo adicional cadastrado')
            ->emptyStateDescription('Crie campos próprios do município para lotes, edificações e unidades imobiliárias.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampoCustomizados::route('/'),
            'create' => Pages\CreateCampoCustomizado::route('/create'),
            'edit' => Pages\EditCampoCustomizado::route('/{record}/edit'),
        ];
    }
}
