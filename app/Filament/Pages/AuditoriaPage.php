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
    protected static ?string $navigationGroup = 'Administração';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.auditoria-page';

    protected function getTableQuery(): Builder
    {
        $tenantId = filament()->getTenant()?->id;

        return Activity::query()
            ->when($tenantId, fn ($q) => $q->where(function ($q) use ($tenantId) {
                $q->whereHasMorph('subject', '*', fn ($q) => $q->where('tenant_id', $tenantId))
                  ->orWhereHasMorph('causer', 'App\Models\User', fn ($q) => $q->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId)));
            }))
            ->latest();
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
                ->formatStateUsing(fn ($state) => match ($state) {
                    'created' => 'Criado',
                    'updated' => 'Atualizado',
                    'deleted' => 'Excluído',
                    default   => $state,
                }),

            Tables\Columns\TextColumn::make('subject_type')
                ->label('Entidade')
                ->formatStateUsing(fn ($state) => class_basename($state))
                ->searchable(),

            Tables\Columns\TextColumn::make('subject_id')
                ->label('ID')
                ->sortable(),

            Tables\Columns\TextColumn::make('description')
                ->label('Descrição')
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
                        ->when($data['de'],  fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['ate'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('ver_propriedades')
                ->label('Ver detalhes')
                ->icon('heroicon-o-eye')
                ->modalContent(fn (Activity $record) => view('filament.pages.auditoria-detalhes', ['activity' => $record]))
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
