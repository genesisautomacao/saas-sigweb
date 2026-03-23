<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteValorHistoricoResource\Pages;
use App\Models\LoteValorHistorico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LoteValorHistoricoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'pgv'; // Módulo PGV
    protected static ?string $model = LoteValorHistorico::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $modelLabel = 'Histórico de Valor Venal';
    protected static ?string $pluralModelLabel = 'Valores Venais (Histórico)';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Avaliação')
                    ->description('Lançamento manual de valor venal. (Nota: O ideal é que estes valores sejam gerados automaticamente pelo Mapa).')
                    ->schema([
                        Forms\Components\Select::make('lote_id')
                            ->label('Lote Avaliado')
                            ->relationship('lote', 'numero_lote')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('ano_vigente')
                            ->label('Ano de Referência')
                            ->numeric()
                            ->default(now()->year)
                            ->required(),

                        Forms\Components\Select::make('setor_fiscal_id')
                            ->label('Setor Fiscal Aplicado')
                            ->relationship('setor', 'nome')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('valor_terreno')
                            ->label('Valor Venal do Terreno')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),

                        Forms\Components\TextInput::make('valor_edificacao')
                            ->label('Valor Venal da Edificação')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),

                        Forms\Components\TextInput::make('valor_total')
                            ->label('Valor Venal Total')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->default(0.00),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ano_vigente')
                    ->label('Ano')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // NOVO: Coluna do Bairro (Puxando lá do Lote -> Quadra -> Bairro)
                Tables\Columns\TextColumn::make('lote.quadra.bairro.name')
                    ->label('Bairro')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                // ATUALIZADO: Lote com Descrição Inteligente das Unidades
                Tables\Columns\TextColumn::make('lote.numero_lote')
                    ->label('Lote nº / Unidades')
                    ->sortable()
                    ->weight('bold')
                    // A mágica visual: Coloca os códigos embaixo do número do lote
                    ->description(function (LoteValorHistorico $record) {
                        if (!$record->lote) return '';
                        $unidades = $record->lote->unidadesImobiliarias->pluck('codigo_imovel_tributario')->filter();
                        return $unidades->isNotEmpty() ? 'Códigos Fiscais: ' . $unidades->join(', ') : 'Sem unidades atreladas';
                    })
                    // A mágica do motor de busca: Ensina o Laravel a pesquisar nos filhos
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->whereHas('lote', function ($qLote) use ($search) {
                            $qLote->where('numero_lote', 'ilike', "%{$search}%")
                                ->orWhereHas('unidadesImobiliarias', function ($qUnid) use ($search) {
                                    $qUnid->where('codigo_imovel_tributario', 'ilike', "%{$search}%")
                                        ->orWhere('inscricao_imobiliaria', 'ilike', "%{$search}%");
                                });
                        });
                    }),

                Tables\Columns\TextColumn::make('setor.nome')
                    ->label('Setor Fiscal')
                    ->searchable()
                    ->sortable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('valor_total')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
            ])
            ->defaultSort('ano_vigente', 'desc')
            ->filters([
                // NOVO: Filtro cruzado blindado por Bairro
                Tables\Filters\SelectFilter::make('bairro_id')
                    ->label('Filtrar por Bairro')
                    ->options(fn() => \App\Models\Bairro::where('tenant_id', \Filament\Facades\Filament::getTenant()->id)->pluck('name', 'id')->toArray())
                    ->multiple()
                    ->preload()
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            $query->whereHas('lote.quadra', function ($q) use ($data) {
                                $q->whereIn('bairro_id', $data['values']);
                            });
                        }
                    }),

                Tables\Filters\SelectFilter::make('ano_vigente')
                    ->label('Filtrar por Ano')
                    ->options(function () {
                        return LoteValorHistorico::query()
                            ->select('ano_vigente')
                            ->distinct()
                            ->orderBy('ano_vigente', 'desc')
                            ->pluck('ano_vigente', 'ano_vigente')
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('setor_fiscal_id')
                    ->label('Filtrar por Setor Fiscal')
                    ->relationship('setor', 'nome')
                    ->multiple()
                    ->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoteValorHistoricos::route('/'),
            'create' => Pages\CreateLoteValorHistorico::route('/create'),
            'edit' => Pages\EditLoteValorHistorico::route('/{record}/edit'),
        ];
    }
}
