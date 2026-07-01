<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteEstoqueResource\Pages;
use App\Models\Fornecedor;
use App\Models\LoteEstoque;
use App\Models\Produto;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoteEstoqueResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = LoteEstoque::class;
    protected static ?string $tenantRelationshipName = 'loteEstoques';
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Lote / Série';
    protected static ?string $pluralModelLabel = 'Lotes / Séries';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('numero_lote')->label('Número do Lote / Série')->required()->maxLength(255),
            Forms\Components\Select::make('produto_id')->label('Produto')
                ->options(fn() => Produto::pluck('name', 'id'))->searchable()->required(),
            Forms\Components\Select::make('fornecedor_id')->label('Fornecedor')
                ->options(fn() => Fornecedor::pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('quantidade_inicial')->label('Quantidade Inicial')->numeric()->default(0),
            Forms\Components\DatePicker::make('data_fabricacao')->label('Fabricação'),
            Forms\Components\DatePicker::make('data_validade')->label('Validade'),
            Forms\Components\DatePicker::make('data_garantia')->label('Fim da Garantia'),
            Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_garantia', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('numero_lote')->label('Lote / Série')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('produto.name')->label('Produto')->searchable()->default('—'),
                Tables\Columns\TextColumn::make('fornecedor.name')->label('Fornecedor')->default('—'),
                Tables\Columns\TextColumn::make('quantidade_inicial')->label('Qtd Inicial')->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('data_validade')->label('Validade')->date('d/m/Y')->default('—'),
                Tables\Columns\TextColumn::make('data_garantia')->label('Garantia até')->date('d/m/Y')->default('—'),
                Tables\Columns\TextColumn::make('dias_garantia')
                    ->label('Situação Garantia')
                    ->badge()
                    ->getStateUsing(function (LoteEstoque $record): string {
                        $d = $record->dias_garantia;
                        if ($d === null) return 'Sem garantia';
                        if ($d < 0) return 'Vencida há ' . abs($d) . 'd';
                        if ($d <= 30) return 'Vence em ' . $d . 'd';
                        return 'Vigente (' . $d . 'd)';
                    })
                    ->color(function (LoteEstoque $record): string {
                        $d = $record->dias_garantia;
                        if ($d === null) return 'gray';
                        if ($d < 0) return 'danger';
                        if ($d <= 30) return 'warning';
                        return 'success';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('produto_id')->label('Produto')
                    ->relationship('produto', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('fornecedor_id')->label('Fornecedor')
                    ->relationship('fornecedor', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('familia')->label('Família de Produto')
                    ->options(fn() => \App\Models\FamiliaProduto::pluck('name', 'id'))
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'],
                        fn($q, $v) => $q->whereHas('produto', fn($p) => $p->where('familia_produto_id', $v))
                    )),
                Tables\Filters\Filter::make('garantia_vencida')->label('Garantia vencida')
                    ->query(fn($query) => $query->whereNotNull('data_garantia')->whereDate('data_garantia', '<', now()))
                    ->toggle(),
                Tables\Filters\Filter::make('garantia_a_vencer')->label('Vence em 30 dias')
                    ->query(fn($query) => $query->whereNotNull('data_garantia')
                        ->whereDate('data_garantia', '>=', now())
                        ->whereDate('data_garantia', '<=', now()->addDays(30)))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLoteEstoques::route('/')];
    }
}
