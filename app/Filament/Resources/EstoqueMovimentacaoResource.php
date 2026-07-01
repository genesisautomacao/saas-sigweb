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
    protected static ?int $navigationSort = 14;

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
                        Forms\Components\Select::make('operacao_interna_id')
                            ->label('Operação Interna (configurada)')
                            ->options(fn() => \App\Models\OperacaoInterna::where('is_active', true)->pluck('name', 'id'))
                            ->helperText('Preenche o tipo automaticamente conforme o sentido cadastrado (item 054).')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $op = \App\Models\OperacaoInterna::find($state);
                                    if ($op) {
                                        $set('type', $op->sentido);
                                    }
                                }
                            })
                            ->columnSpan(3),

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

                        Forms\Components\Select::make('tipo_estoque_origem_id')
                            ->label('Tipo de Estoque (Origem)')
                            ->options(fn() => \App\Models\TipoEstoque::pluck('name', 'id'))
                            ->visible(fn(Forms\Get $get) => in_array($get('type'), ['saida', 'transferencia']))
                            ->searchable(),

                        Forms\Components\Select::make('tipo_estoque_destino_id')
                            ->label('Tipo de Estoque (Destino)')
                            ->options(fn() => \App\Models\TipoEstoque::pluck('name', 'id'))
                            ->visible(fn(Forms\Get $get) => in_array($get('type'), ['entrada', 'transferencia']))
                            ->searchable(),

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
                                    ->live()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('lote_estoque_id')
                                    ->label('Lote / Série')
                                    ->options(function (Forms\Get $get) {
                                        $produtoId = $get('produto_id');
                                        return $produtoId
                                            ? \App\Models\LoteEstoque::where('produto_id', $produtoId)->pluck('numero_lote', 'id')
                                            : [];
                                    })
                                    ->searchable()
                                    ->columnSpan(2),

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
                            ->columns(6)
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
                    }),

                // 5. Filtro por Produto (via itens da movimentação) — item 057
                Tables\Filters\SelectFilter::make('produto')
                    ->label('Filtrar por Produto')
                    ->options(fn() => \App\Models\Produto::pluck('name', 'id'))
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'],
                        fn($q, $v) => $q->whereHas('itens', fn($i) => $i->where('produto_id', $v))
                    )),

                // 6. Filtro por Lote / Série (via itens) — item 057
                Tables\Filters\SelectFilter::make('lote')
                    ->label('Filtrar por Lote / Série')
                    ->options(fn() => \App\Models\LoteEstoque::pluck('numero_lote', 'id'))
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'],
                        fn($q, $v) => $q->whereHas('itens', fn($i) => $i->where('lote_estoque_id', $v))
                    )),

                // 7. Filtro por Tipo de Estoque (origem ou destino) — item 057
                Tables\Filters\SelectFilter::make('tipo_estoque')
                    ->label('Filtrar por Tipo de Estoque')
                    ->options(fn() => \App\Models\TipoEstoque::pluck('name', 'id'))
                    ->query(fn($query, array $data) => $query->when(
                        $data['value'],
                        fn($q, $v) => $q->where(fn($sub) => $sub->where('tipo_estoque_origem_id', $v)->orWhere('tipo_estoque_destino_id', $v))
                    )),
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