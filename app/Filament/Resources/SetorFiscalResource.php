<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SetorFiscalResource\Pages;
use App\Models\Bairro;
use App\Models\SetorFiscal;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

/**
 * B9 (2026-10-19) — Setor Fiscal da inscrição imobiliária. O número (`name`)
 * compõe a inscrição; o setor agrupa 1+ bairros (vínculo no próprio bairro ou
 * pela ação "Vincular bairros pela geometria"). O setor da PGV é o SetorPgvResource.
 */
class SetorFiscalResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'base_cartografica';

    protected static ?string $model = SetorFiscal::class;

    protected static ?string $slug = 'setores-fiscais';

    protected static ?string $tenantRelationshipName = 'setoresFiscais';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Base Cartográfica';

    protected static ?int $navigationSort = 0; // entre Distritos (-10) e Bairros (1)

    protected static ?string $modelLabel = 'Setor Fiscal';

    protected static ?string $pluralModelLabel = 'Setores Fiscais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação do Setor')->schema([
                static::campoNumero(),

                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->helperText('Calculada automaticamente da geometria.')
                    ->disabled()
                    ->dehydrated(false)
                    ->numeric()
                    ->suffix('m²'),

                Forms\Components\Placeholder::make('bairros_do_setor')
                    ->label('Bairros do setor')
                    ->visibleOn('edit')
                    ->content(fn (?SetorFiscal $record) => $record?->bairros()->orderBy('name')->pluck('name')->join(', ') ?: 'Nenhum bairro vinculado.'),
            ])->columns(2),

            // Item 75 — campos customizados do município nesta camada
            Forms\Components\Section::make('Campos do Município')
                ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('setor_fiscal')->isNotEmpty())
                ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('setor_fiscal'))
                ->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas do Perímetro (Ou GeoJSON)')
                    ->helperText('Cole um GeoJSON completo OU uma lista de coordenadas "longitude latitude", uma por linha.')
                    ->rows(12)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /** Número do setor — usado também nos modais do mapa. Único entre os vivos da prefeitura. */
    public static function campoNumero(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('name')
            ->label('Número do Setor')
            ->helperText('Compõe a inscrição imobiliária. Guarde os zeros à esquerda (ex.: 01).')
            ->required()
            ->maxLength(50)
            ->unique(
                table: 'setores_fiscais',
                column: 'name',
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule) => $rule
                    ->where('tenant_id', \Filament\Facades\Filament::getTenant()?->id)
                    ->whereNull('deleted_at'),
            )
            ->validationMessages(['unique' => 'Já existe um setor com este número nesta prefeitura.']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('bairros'))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Setor')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('bairros_count')->label('Bairros')->badge()->sortable(),
                Tables\Columns\TextColumn::make('bairros.name')
                    ->label('Nomes dos bairros')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (SetorFiscal $record) {
                        $ponto = \Illuminate\Support\Facades\DB::selectOne(
                            'SELECT ST_Y(ST_PointOnSurface(geo)) AS lat, ST_X(ST_PointOnSurface(geo)) AS lon FROM setores_fiscais WHERE id = ? AND geo IS NOT NULL',
                            [$record->id]
                        );
                        if (! $ponto) {
                            return null;
                        }
                        $tenant = \Filament\Facades\Filament::getTenant();

                        return url('/app/'.$tenant->slug.'/mapa-interativo?layer=setores_fiscais&focus_lat='.$ponto->lat.'&focus_lon='.$ponto->lon.'&zoom=14');
                    }),
                static::acaoVincularBairros(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    /** Liga ao setor os bairros cujo ponto interno cai dentro dele. */
    public static function acaoVincularBairros(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('vincular_bairros')
            ->label('Vincular bairros')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->visible(fn (SetorFiscal $record) => $record->geo_json !== null
                && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading(fn (SetorFiscal $record) => "Vincular bairros ao setor {$record->name}")
            ->modalDescription('Os bairros cujo ponto interno fica dentro deste setor passam a apontar para ele.')
            ->form([
                Forms\Components\Toggle::make('sobrescrever')
                    ->label('Trocar também bairros que já estão em outro setor')
                    ->default(false),
            ])
            ->action(function (SetorFiscal $record, array $data) {
                $n = $record->vincularBairrosPelaGeometria((bool) ($data['sobrescrever'] ?? false));

                Notification::make()
                    ->title($n ? "{$n} bairro(s) vinculado(s) ao setor {$record->name}" : 'Nenhum bairro novo para vincular')
                    ->success()
                    ->send();
            });
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSetorFiscais::route('/'),
            'create' => Pages\CreateSetorFiscal::route('/create'),
            'edit' => Pages\EditSetorFiscal::route('/{record}/edit'),
        ];
    }
}
