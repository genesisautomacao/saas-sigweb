<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstoqueMovimentacaoResource\Pages;
use App\Models\EstoqueMovimentacao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class EstoqueMovimentacaoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = EstoqueMovimentacao::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Movimentação';
    protected static ?string $pluralModelLabel = 'Movimentações (Entradas/Saídas)';
    protected static ?int $navigationSort = 5;

    // 🛑 LIVRO RAZÃO IMUTÁVEL: Não se edita nem apaga histórico! Se errou, faz uma movimentação reversa.
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    //public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Movimentação')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Operação')
                            ->options([
                                'entrada' => '🟢 Entrada (Compra/Doação)',
                                'saida' => '🔴 Saída (Consumo/Perda)',
                                'transferencia' => '🔵 Transferência entre Locais',
                            ])
                            ->required()
                            ->live(), // Recarrega a tela ao mudar

                        Forms\Components\Select::make('origem_id')
                            ->label('Local de Origem (Retirada)')
                            ->relationship('origem', 'name')
                            ->visible(fn(Forms\Get $get) => in_array($get('type'), ['saida', 'transferencia']))
                            ->required(fn(Forms\Get $get) => in_array($get('type'), ['saida', 'transferencia']))
                            ->live(), // 🛑 PRECISA SER LIVE para a validação saber de qual estoque checar o saldo

                        Forms\Components\Select::make('destino_id')
                            ->label('Local de Destino (Entrada)')
                            ->relationship('destino', 'name')
                            ->visible(fn(Forms\Get $get) => in_array($get('type'), ['entrada', 'transferencia']))
                            ->required(fn(Forms\Get $get) => in_array($get('type'), ['entrada', 'transferencia'])),

                        Forms\Components\TextInput::make('observacao')
                            ->label('Observação / Motivo')
                            ->placeholder('Ex: Compra NF 1234, Transferência para Viatura 02...')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Itens / Produtos')
                    ->schema([
                        Forms\Components\Repeater::make('itens')
                            ->relationship('itens')
                            ->schema([
                                Forms\Components\Select::make('produto_id')
                                    ->label('Produto')
                                    ->relationship('produto', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantidade')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(1)
                                    // 🛑 A MÁGICA DA VALIDAÇÃO AQUI
                                    ->rules([
                                        fn(Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $type = $get('../../type');
                                            $origemId = $get('../../origem_id');
                                            $produtoId = $get('produto_id');

                                            // Só faz a checagem se for saída ou transferência
                                            if (in_array($type, ['saida', 'transferencia']) && $origemId && $produtoId) {

                                                // Busca o saldo atual no banco
                                                $saldoAtual = \App\Models\Estoque::where('local_estoque_id', $origemId)
                                                    ->where('produto_id', $produtoId)
                                                    ->value('quantity') ?? 0;

                                                // Se tentar tirar mais do que tem, bloqueia o form!
                                                if ($value > $saldoAtual) {
                                                    $fail("Saldo insuficiente neste local! Disponível: {$saldoAtual}.");
                                                }
                                            }
                                        },
                                    ]),

                                Forms\Components\TextInput::make('unitary_value')
                                    ->label('Valor Unit. (R$)')
                                    ->numeric()
                                    ->visible(fn(Forms\Get $get) => $get('../../type') === 'entrada')
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                            ->addActionLabel('Adicionar Produto')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(), // 🔍 Pesquisa ativada aqui!

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'entrada' => 'success',
                        'saida' => 'danger',
                        'transferencia' => 'info',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('origem.name')
                    ->label('Origem')
                    ->searchable() // 🔍 Pesquisa ativada aqui!
                    ->default('-'),

                Tables\Columns\TextColumn::make('destino.name')
                    ->label('Destino')
                    ->searchable() // 🔍 Pesquisa ativada aqui!
                    ->default('-'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Operador')
                    ->searchable() // 🔍 Pesquisa ativada aqui!
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')

            // 🛑 AQUI ENTRAM OS FILTROS DO FUNIL 🛑
            ->filters([
                // 1. Filtro por Tipo (Entrada, Saída, etc)
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Operação')
                    ->options([
                        'entrada' => 'Entrada',
                        'saida' => 'Saída',
                        'transferencia' => 'Transferência',
                    ]),

                // 2. Filtro por Local de Origem
                Tables\Filters\SelectFilter::make('origem_id')
                    ->label('Filtrar Origem')
                    ->relationship('origem', 'name')
                    ->searchable()
                    ->preload(),

                // 3. Filtro por Local de Destino
                Tables\Filters\SelectFilter::make('destino_id')
                    ->label('Filtrar Destino')
                    ->relationship('destino', 'name')
                    ->searchable()
                    ->preload(),

                // 4. Filtro Avançado por Período (Data)
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('A partir de'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Até a data'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Estornar Movimentação')
                    ->modalDescription('Tem certeza? Isso irá excluir este registro e reverter automaticamente os saldos no estoque.')
                    ->modalSubmitActionLabel('Sim, estornar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Estornar Selecionados'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstoqueMovimentacaos::route('/'),
            'create' => Pages\CreateEstoqueMovimentacao::route('/create'),
            // Edit removido propositalmente!
        ];
    }
}