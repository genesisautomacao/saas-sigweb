<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class AuditoriaPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Auditoria';
    protected static ?string $title = 'Histórico de Operações';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 35;
    protected static string $view = 'filament.pages.auditoria-page';

    /**
     * T1.1 (PoC Tangará, item 14) — foco num lote: a ficha do mapa abre a auditoria
     * pré-filtrada pelo lote E seus filhos (unidades, edificações, testadas).
     */
    public ?int $loteId = null;

    public function mount(): void
    {
        $this->loteId = request()->integer('lote_id') ?: null;
    }

    public function getSubheading(): ?string
    {
        if (! $this->loteId) {
            return null;
        }

        $lote = \App\Models\Lote::query()->find($this->loteId);

        return $lote
            ? "Exibindo apenas o histórico do Lote #{$lote->sequential_id} e de suas unidades, edificações e testadas."
            : null;
    }

    public function limparFocoLote(): void
    {
        $this->loteId = null;
        $this->resetTable();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_auditoria') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        $tenantId = filament()->getTenant()?->id;

        $query = Activity::query()
            ->when($tenantId, fn($q) => $q->where(function ($q) use ($tenantId) {
                $q->whereHasMorph('subject', '*', fn($q) => $q->where('tenant_id', $tenantId))
                    ->orWhereHasMorph('causer', 'App\Models\User', fn($q) => $q->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId)));
            }));

        if ($this->loteId) {
            $unidadeIds = \App\Models\UnidadeImobiliaria::query()->withTrashed()
                ->where('lote_id', $this->loteId)->pluck('id');
            $edificacaoIds = \App\Models\Edificacao::query()->withTrashed()
                ->where('lote_id', $this->loteId)->pluck('id');
            $testadaIds = \App\Models\LoteTestada::query()->withTrashed()
                ->where('lote_id', $this->loteId)->pluck('id');

            $query->where(function ($q) use ($unidadeIds, $edificacaoIds, $testadaIds) {
                $q->where(fn($q) => $q->where('subject_type', \App\Models\Lote::class)->where('subject_id', $this->loteId))
                    ->orWhere(fn($q) => $q->where('subject_type', \App\Models\UnidadeImobiliaria::class)->whereIn('subject_id', $unidadeIds))
                    ->orWhere(fn($q) => $q->where('subject_type', \App\Models\Edificacao::class)->whereIn('subject_id', $edificacaoIds))
                    ->orWhere(fn($q) => $q->where('subject_type', \App\Models\LoteTestada::class)->whereIn('subject_id', $testadaIds));
            });
        }

        return $query->latest();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('limpar_foco')
                ->label('Limpar filtro do lote')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn () => $this->loteId !== null)
                ->action('limparFocoLote'),
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label('Data / Hora')
                ->dateTime('d/m/Y H:i:s')
                ->sortable(),

            Tables\Columns\TextColumn::make('causer.name')
                ->label('Usuário')
                ->default('Sistema')
                ->searchable(),

            Tables\Columns\BadgeColumn::make('event')
                ->label('Operação')
                ->colors([
                    'success' => 'created',
                    'warning' => 'updated',
                    'danger'  => 'deleted',
                ])
                ->formatStateUsing(fn($state) => match ($state) {
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Excluído',
                    default   => $state,
                }),

            Tables\Columns\TextColumn::make('subject_type')
                ->label('Entidade')
                ->formatStateUsing(fn($state) => class_basename($state))
                ->searchable(),

            Tables\Columns\TextColumn::make('subject_id')
                ->label('ID')
                ->sortable(),

            Tables\Columns\TextColumn::make('description')
                ->label('Descrição')
                ->formatStateUsing(fn(?string $state) => match ($state) {
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Excluído',
                    default   => $state,
                })
                ->limit(60),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('event')
                ->label('Operação')
                ->options([
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Excluído',
                ]),

            Tables\Filters\Filter::make('periodo')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('de')->label('De'),
                    \Filament\Forms\Components\DatePicker::make('ate')->label('Até'),
                ])
                ->query(function (Builder $query, array $data) {
                    return $query
                        ->when($data['de'],  fn($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['ate'], fn($q, $v) => $q->whereDate('created_at', '<=', $v));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('ver_propriedades')
                ->label('Ver detalhes')
                ->icon('heroicon-o-eye')
                ->modalContent(fn(Activity $record) => view('filament.pages.auditoria-detalhes', ['activity' => $record]))
                ->modalHeading('Detalhes da Operação')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [25, 50, 100];
    }
}
