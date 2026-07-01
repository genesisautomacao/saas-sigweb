<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstoqueResource\Pages;
use App\Models\Estoque;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class EstoqueResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = Estoque::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Saldo em Estoque';
    protected static ?string $pluralModelLabel = 'Saldos em Estoque';
    protected static ?int $navigationSort = 13;

    // Removemos os botões de criar e editar pois Saldo não se cria na mão
    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('localEstoque.name')
                    ->label('Local de Estoque')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipoEstoque.name')
                    ->label('Tipo de Estoque')
                    ->badge()
                    ->color('info')
                    ->default('—'),

                Tables\Columns\TextColumn::make('produto.name')
                    ->label('Produto')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('produto.familia.name')
                    ->label('Família')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('loteEstoque.numero_lote')
                    ->label('Lote / Série')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('produto.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade (Saldo)')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('produto.unit')
                    ->label('UN'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('local_estoque_id')
                    ->relationship('localEstoque', 'name')
                    ->label('Filtrar por Local'),

                Tables\Filters\SelectFilter::make('tipo_estoque_id')
                    ->relationship('tipoEstoque', 'name')
                    ->label('Filtrar por Tipo de Estoque'),

                Tables\Filters\SelectFilter::make('produto_id')
                    ->relationship('produto', 'name')
                    ->label('Filtrar por Produto')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('familia')
                    ->label('Filtrar por Família')
                    ->options(fn() => \App\Models\FamiliaProduto::pluck('name', 'id'))
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'],
                        fn($q, $v) => $q->whereHas('produto', fn($p) => $p->where('familia_produto_id', $v))
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstoques::route('/'),
        ];
    }
}