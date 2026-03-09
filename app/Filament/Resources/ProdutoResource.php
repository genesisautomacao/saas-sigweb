<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutoResource\Pages;
use App\Models\Produto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ProdutoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = Produto::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Produto / Material';
    protected static ?string $pluralModelLabel = 'Produtos e Materiais';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Produto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU / Código de Barras')
                            ->placeholder('Código interno ou barras')
                            ->maxLength(255),

                        Forms\Components\Select::make('marca_id')
                            ->label('Marca')
                            ->relationship('marca', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome da Marca')
                                    ->required(),
                            ]),
                    ])->columns(4),

                Forms\Components\Section::make('Controle e Medidas')
                    ->schema([
                        Forms\Components\Select::make('unit')
                            ->label('Unidade de Medida')
                            ->options([
                                'UN' => 'Unidade (UN)',
                                'KG' => 'Quilograma (KG)',
                                'M' => 'Metro (M)',
                                'CX' => 'Caixa (CX)',
                                'LT' => 'Litro (LT)',
                                'PC' => 'Peça (PC)',
                            ])
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('min_stock')
                            ->label('Estoque Mínimo de Alerta')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Produto Ativo')
                            ->default(true)
                            ->inline(false),
                    ])->columns(3),

                Forms\Components\Section::make('Detalhes')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição Adicional')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('marca.name')
                    ->label('Marca')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unidade')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marca_id')
                    ->relationship('marca', 'name')
                    ->label('Filtrar por Marca'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status de Atividade'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListProdutos::route('/'),
            'create' => Pages\CreateProduto::route('/create'),
            'edit' => Pages\EditProduto::route('/{record}/edit'),
        ];
    }
}