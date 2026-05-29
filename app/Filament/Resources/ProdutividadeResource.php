<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutividadeResource\Pages;
use App\Models\Lote;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProdutividadeResource extends Resource
{
    protected static ?string $model = Lote::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Coletas Cadastradas';
    protected static ?string $navigationGroup = 'Coleta cadastral';
    protected static ?string $modelLabel = 'Coleta';
    protected static ?string $pluralModelLabel = 'Coletas';
    protected static ?int $navigationSort = 32;
    protected static ?string $slug = 'coletas-produtividade';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_produtividade') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('coletado_por_id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('coletado_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('numero_lote')
                    ->label('Lote nº')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quadra.name')
                    ->label('Quadra')
                    ->sortable(),

                Tables\Columns\TextColumn::make('coletor.name')
                    ->label('Cadastrador')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('coletado_em')
                    ->label('Data Coleta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_cadastro')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'nao_visitado'   => 'Não Visitado',
                        'coletado'       => 'Coletado',
                        'pendente'       => 'Pendente',
                        'inconformidade' => 'Inconformidade',
                        default          => '—',
                    })
                    ->color(fn ($state) => match ($state) {
                        'coletado'       => 'success',
                        'pendente'       => 'warning',
                        'inconformidade' => 'danger',
                        default          => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Fotos')
                    ->state(function (Lote $record): string {
                        $count = 0;
                        if ($record->foto_frontal)     $count++;
                        if ($record->foto_lateral_esq) $count++;
                        if ($record->foto_lateral_dir) $count++;
                        return $count . '/3';
                    })
                    ->badge()
                    ->color(fn ($state) => str_starts_with($state, '3') ? 'success' : (str_starts_with($state, '0') ? 'danger' : 'warning')),
            ])
            ->filters([
                SelectFilter::make('coletado_por_id')
                    ->label('Cadastrador')
                    ->options(function (): array {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if (!$tenant) {
                            return [];
                        }

                        // Apenas usuários do tenant atual que tenham AO MENOS uma role atribuída no tenant
                        return DB::table('users as u')
                            ->join('tenant_user as tu', 'tu.user_id', '=', 'u.id')
                            ->where('tu.tenant_id', $tenant->id)
                            ->whereExists(function ($q) use ($tenant) {
                                $q->select(DB::raw(1))
                                    ->from('model_has_roles as mhr')
                                    ->whereColumn('mhr.model_id', 'u.id')
                                    ->where('mhr.model_type', \App\Models\User::class)
                                    ->where('mhr.tenant_id', $tenant->id);
                            })
                            ->orderBy('u.name')
                            ->pluck('u.name', 'u.id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status_cadastro')
                    ->label('Status')
                    ->options([
                        'coletado'       => 'Coletado',
                        'pendente'       => 'Pendente',
                        'inconformidade' => 'Inconformidade',
                    ])
                    ->multiple(),

                Filter::make('data_coleta')
                    ->form([
                        Forms\Components\DatePicker::make('data_inicio')
                            ->label('Data Início'),
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Data Fim'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['data_inicio'] ?? null, fn ($q, $d) => $q->whereDate('coletado_em', '>=', $d))
                            ->when($data['data_fim'] ?? null, fn ($q, $d) => $q->whereDate('coletado_em', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['data_inicio'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['data_inicio'])->format('d/m/Y');
                        }
                        if ($data['data_fim'] ?? null) {
                            $indicators[] = 'Até: ' . \Carbon\Carbon::parse($data['data_fim'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdutividade::route('/'),
        ];
    }
}
