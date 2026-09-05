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

        return $query->with(['causer', 'subject'])->latest();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('limpar_foco')
                ->label('Limpar filtro do lote')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn() => $this->loteId !== null)
                ->action('limparFocoLote'),

            // PoC AC item 6 — exporta respeitando os filtros da tabela
            // (usuário/operação/período). Teto de 10.000 linhas por arquivo.
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function (\App\Services\Exports\AuditoriaExportService $service) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();

                        return $service->exportToExcel(
                            $this->getFilteredTableQuery()->with(['causer', 'subject'])->limit(10000)->get()
                        );
                    }),

                \Filament\Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function (\App\Services\Exports\AuditoriaExportService $service) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();

                        return $service->exportToPdf(
                            $this->getFilteredTableQuery()->with(['causer', 'subject'])->limit(10000)->get()
                        );
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),
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
                // Usuário soft-deletado: o morphTo devolve null e a trilha
                // perderia o autor — recupera o nome na lixeira (withTrashed).
                ->getStateUsing(function (Activity $record) {
                    return $record->causer->name
                        ?? ($record->causer_type === \App\Models\User::class && $record->causer_id
                            ? \App\Models\User::withTrashed()->find($record->causer_id)?->name
                            : null)
                        ?? 'Sistema';
                })
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
                ->label('Registro')
                // PoC AC — além do ID, o identificador legível da entidade
                // (numero_lote p/ Lote, name/nome/inscrição p/ as demais),
                // inclusive de registros já excluídos (busca sem escopos).
                ->getStateUsing(fn(Activity $record) => \App\Services\Exports\AuditoriaExportService::rotuloRegistro($record))
                ->sortable(),

            Tables\Columns\TextColumn::make('description')
                ->label('Descrição')
                ->formatStateUsing(fn(?string $state) => match ($state) {
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Excluído',
                    default   => $state,
                })
                ->wrap()
                ->limit(60),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            // PoC AC item 6 — histórico POR USUÁRIO. O filtro oferece SÓ a
            // equipe ATIVA da prefeitura (tipo 'prefeitura', sem soft-deletados,
            // isolado por tenant; sem tenant = lista vazia, falha fechada).
            // As LINHAS/exports continuam mostrando o nome de autores já
            // excluídos (trilha preservada via withTrashed na coluna).
            Tables\Filters\SelectFilter::make('usuario')
                ->label('Usuário')
                ->searchable()
                ->options(function () {
                    $tenantId = filament()->getTenant()?->id;
                    if (! $tenantId) {
                        return [];
                    }

                    return \App\Models\User::query()
                        ->where('tipo', 'prefeitura')
                        ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->query(fn(Builder $query, array $data) => $query->when(
                    $data['value'] ?? null,
                    fn($q, $v) => $q->where('causer_type', \App\Models\User::class)->where('causer_id', $v)
                )),

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
