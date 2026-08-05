<?php

namespace App\Filament\Pages;

use App\Models\CampoCustomizado;
use App\Models\CampoDominio;
use App\Services\Coleta\CampoDominioService;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * R67-2 — LISTA (tabela Filament padrão) dos campos padrão do sistema, agrupada
 * por entidade, com busca e filtro — mesmo jeitão do CampoCustomizadoResource.
 * "Personalizar" leva ao detalhe da entidade (CamposPadraoEntidadePage).
 *
 * As linhas são registros reais de `campo_dominios`, SEMEADOS sob demanda no
 * mount() com os valores-fallback (label null, opcoes null, visivel true) — linha
 * semeada é funcionalmente idêntica a linha inexistente, então nada muda para o
 * município. Extensível: nova entidade (ex.: Árvore) = registrar em
 * CampoDominioService::PADROES — a semeadura e a tabela a incluem sozinhas.
 */
class CamposPadraoPage extends Page implements HasForms, HasTable
{
    use HasTenantModule, InteractsWithForms, InteractsWithTable;

    protected static ?string $tenantModule = 'imobiliario';

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Campos Padrão do Sistema';

    protected static ?string $title = 'Campos Padrão do Sistema';

    protected static ?string $navigationGroup = 'Customizações';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.campos-padrao';

    protected static ?string $slug = 'campos-padrao';

    public static function canAccess(): bool
    {
        return auth()->user()?->temPermissao('gerenciar_campos_customizados') ?? false;
    }

    public function mount(): void
    {
        $this->semearLinhas();
    }

    /** Garante uma linha em campo_dominios para cada campo padrão (valores = fallback). */
    protected function semearLinhas(): void
    {
        $tenantId = Filament::getTenant()?->id;

        if (! $tenantId) {
            return;
        }

        $existentes = CampoDominio::query()
            ->get(['entidade', 'campo'])
            ->map(fn ($d) => $d->entidade.'.'.$d->campo)
            ->flip();

        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            foreach (array_keys($campos) as $campo) {
                if (! isset($existentes[$entidade.'.'.$campo])) {
                    CampoDominio::create([
                        'tenant_id' => $tenantId,
                        'entidade' => $entidade,
                        'campo' => $campo,
                        'label' => null,
                        'opcoes' => null,
                        'visivel' => true,
                        'na_coleta' => true,
                        'obrigatorio_coleta' => false,
                    ]);
                }
            }
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                // Só os campos que o sistema realmente oferece (registrados em PADROES)
                return CampoDominio::query()->where(function (Builder $q) {
                    foreach (CampoDominioService::PADROES as $entidade => $campos) {
                        $q->orWhere(fn (Builder $qq) => $qq
                            ->where('entidade', $entidade)
                            ->whereIn('campo', array_keys($campos)));
                    }
                });
            })
            ->defaultGroup(
                Tables\Grouping\Group::make('entidade')
                    ->label('Entidade')
                    ->getTitleFromRecordUsing(fn (CampoDominio $record) => CampoCustomizado::ENTIDADES[$record->entidade] ?? ucfirst($record->entidade))
                    ->orderQueryUsing(fn (Builder $query) => $query->orderByRaw(
                        "case entidade when 'lote' then 1 when 'edificacao' then 2 when 'unidade' then 3 else 9 end"
                    ))
            )
            ->columns([
                Tables\Columns\TextColumn::make('entidade')
                    ->label('Entidade')
                    ->badge()
                    ->formatStateUsing(fn ($state) => CampoCustomizado::ENTIDADES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'lote' => 'primary',
                        'edificacao' => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('campo')
                    ->label('Campo do sistema')
                    ->formatStateUsing(fn (CampoDominio $record) => CampoDominioService::PADROES[$record->entidade][$record->campo]['label'] ?? $record->campo)
                    ->description(fn (CampoDominio $record) => $record->campo)
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Nome no município')
                    ->placeholder('Padrão do sistema')
                    ->searchable(),

                Tables\Columns\TextColumn::make('opcoes')
                    ->label('Lista de valores')
                    ->state(function (CampoDominio $record): string {
                        $padrao = CampoDominioService::PADROES[$record->entidade][$record->campo]['opcoes'] ?? [];

                        if (! empty($record->opcoes)) {
                            return count($record->opcoes).' do município';
                        }

                        return ! empty($padrao) ? count($padrao).' padrão' : '—';
                    })
                    ->badge()
                    ->color(fn (CampoDominio $record) => ! empty($record->opcoes) ? 'success' : 'gray'),

                Tables\Columns\ToggleColumn::make('visivel')
                    ->label('Usar este campo')
                    ->tooltip('Desligado: some dos formulários e do app (dados já gravados são preservados).')
                    ->afterStateUpdated(fn () => CampoDominioService::limparCache()),

                Tables\Columns\IconColumn::make('personalizado')
                    ->label('Personalizado')
                    ->state(fn (CampoDominio $record): bool => filled($record->label) || ! empty($record->opcoes) || ! $record->visivel)
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entidade')
                    ->label('Entidade')
                    ->options(CampoCustomizado::ENTIDADES),

                Tables\Filters\TernaryFilter::make('personalizado')
                    ->label('Personalizado')
                    ->placeholder('Todos')
                    ->trueLabel('Só personalizados')
                    ->falseLabel('Só padrão do sistema')
                    ->queries(
                        true: fn (Builder $q) => $q->where(fn (Builder $qq) => $qq
                            ->whereNotNull('label')->orWhereNotNull('opcoes')->orWhere('visivel', false)),
                        false: fn (Builder $q) => $q
                            ->whereNull('label')->whereNull('opcoes')->where('visivel', true),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('personalizar')
                    ->label('Personalizar')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (CampoDominio $record) => CamposPadraoEntidadePage::getUrl(['entidade' => $record->entidade])),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nenhum campo padrão registrado')
            ->emptyStateDescription('Os campos padrão do sistema aparecem aqui automaticamente.');
    }
}
