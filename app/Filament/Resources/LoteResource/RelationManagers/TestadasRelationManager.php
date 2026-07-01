<?php

namespace App\Filament\Resources\LoteResource\RelationManagers;

use App\Models\Logradouro;
use App\Models\SecaoLogradouro;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TestadasRelationManager extends RelationManager
{
    protected static string $relationship = 'testadas';
    protected static ?string $title = 'Testadas do Lote';
    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    public function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->id;

        return $form
            ->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo de Testada')
                    ->options([
                        'principal'  => 'Principal',
                        'secundaria' => 'Secundária',
                    ])
                    ->default('secundaria')
                    ->required()
                    ->helperText('Apenas uma testada pode ser Principal por lote.'),

                Forms\Components\TextInput::make('comprimento')
                    ->label('Comprimento (m)')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(9999.99)
                    ->step(0.01)
                    ->nullable(),

                Forms\Components\Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->options(
                        fn () => Logradouro::where('tenant_id', $tenantId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live(),

                Forms\Components\Select::make('secao_logradouro_id')
                    ->label('Seção de Logradouro')
                    ->helperText('Selecione o Logradouro acima para filtrar as seções disponíveis.')
                    ->options(function (Get $get) {
                        $logradouroId = $get('logradouro_id');
                        if (!$logradouroId) {
                            return [];
                        }
                        return SecaoLogradouro::where('logradouro_id', $logradouroId)
                            ->orderBy('sequential_id')
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->id => $s->name ?: ('Seção #' . $s->sequential_id)])
                            ->toArray();
                    })
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo')
            ->defaultSort('tipo')
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'principal'  => 'success',
                        'secundaria' => 'gray',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'principal'  => 'Principal',
                        'secundaria' => 'Secundária',
                        default      => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('logradouro.name')
                    ->label('Logradouro')
                    ->default('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('secaoLogradouro.name')
                    ->label('Seção')
                    ->default('—')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->secaoLogradouro
                            ? ($record->secaoLogradouro->name ?: 'Seção #' . $record->secaoLogradouro->sequential_id)
                            : '—'
                    ),

                Tables\Columns\TextColumn::make('comprimento')
                    ->label('Comprimento')
                    ->suffix(' m')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->default('—')
                    ->alignEnd(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Testada')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Garante unicidade da testada principal
                        if (($data['tipo'] ?? '') === 'principal') {
                            \App\Models\LoteTestada::where('lote_id', $this->getOwnerRecord()->id)
                                ->where('tipo', 'principal')
                                ->update(['tipo' => 'secundaria']);
                        }
                        return $data;
                    })
                    ->after(function (\App\Models\LoteTestada $record): void {
                        if ($record->tipo === 'principal' && $record->comprimento) {
                            \Illuminate\Support\Facades\DB::statement(
                                'UPDATE lotes SET main_facade_length = ? WHERE id = ?',
                                [(float) $record->comprimento, $record->lote_id]
                            );
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, \App\Models\LoteTestada $record): array {
                        if (($data['tipo'] ?? '') === 'principal' && $record->tipo !== 'principal') {
                            \App\Models\LoteTestada::where('lote_id', $record->lote_id)
                                ->where('tipo', 'principal')
                                ->where('id', '!=', $record->id)
                                ->update(['tipo' => 'secundaria']);
                        }
                        return $data;
                    })
                    ->after(function (\App\Models\LoteTestada $record): void {
                        $record->refresh();
                        if ($record->tipo === 'principal') {
                            \Illuminate\Support\Facades\DB::statement(
                                'UPDATE lotes SET main_facade_length = ? WHERE id = ?',
                                [(float) ($record->comprimento ?? 0), $record->lote_id]
                            );
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function (\App\Models\LoteTestada $record): void {
                        if ($record->tipo === 'principal') {
                            \Illuminate\Support\Facades\DB::statement(
                                'UPDATE lotes SET main_facade_length = NULL WHERE id = ?',
                                [$record->lote_id]
                            );
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
