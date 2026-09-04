<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobZona;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Zona de estudo da mobilidade no mapa (docs/piuma.txt §2.6, Onda 2):
 * zonas O/D (% origens/destinos), quadrantes, polo industrial, setores IBGE.
 */
trait HasMobZonaActions
{
    public ?int $mobZonaAtivoId = null;

    protected function mobZonaPayload(MobZona $z): array
    {
        return [
            'id' => $z->id,
            'layer' => 'mob_zonas',
            'name' => $z->name ?: 'Zona #'.$z->sequential_id,
            'sequential_id' => $z->sequential_id,
            'tipo' => $z->tipo,
            'codigo' => $z->codigo,
            'situacao' => $z->situacao,
            'origens' => $z->origens,
            'destinos' => $z->destinos,
            'area_geo' => $z->area_geo !== null ? (float) $z->area_geo : null,
            'populacao' => $z->populacao,
            'densidade' => $z->densidade !== null ? (float) $z->densidade : null,
            'renda' => $z->renda !== null ? (float) $z->renda : null,
        ];
    }

    /** Densidade (hab/ha) derivada quando o usuário informa população e deixa a densidade vazia. */
    protected function mobZonaCompletarDensidade(array $data, ?MobZona $z): array
    {
        if (($data['tipo'] ?? $z?->tipo) === 'setor_censitario'
            && filled($data['populacao'] ?? null) && blank($data['densidade'] ?? null)
            && $z && (float) $z->area_geo > 0) {
            $data['densidade'] = round((int) $data['populacao'] / ((float) $z->area_geo / 10000), 2);
        }

        return $data;
    }

    protected function mobZonaFormulario(): array
    {
        return [
            Select::make('tipo')
                ->label('Tipo da zona')
                ->options(MobZona::TIPOS)
                ->live()
                ->required(),
            TextInput::make('name')->label('Nome')->maxLength(255)->nullable(),
            TextInput::make('codigo')
                ->label('Código (setor IBGE)')
                ->maxLength(50)
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            Select::make('situacao')
                ->label('Situação')
                ->options(['Urbana' => 'Urbana', 'Rural' => 'Rural'])
                ->nullable()
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            TextInput::make('origens')
                ->label('% Origens (estudo O/D)')
                ->numeric()
                ->nullable()
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'zona_od'),
            TextInput::make('destinos')
                ->label('% Destinos (estudo O/D)')
                ->numeric()
                ->nullable()
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'zona_od'),
            // Demografia do setor (Censo 2022 — arquivo "Densidade Demográfica" da Líder)
            TextInput::make('populacao')
                ->label('População (hab)')
                ->numeric()->integer()->minValue(0)
                ->nullable()
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            TextInput::make('densidade')
                ->label('Densidade (hab/ha)')
                ->numeric()->minValue(0)
                ->nullable()
                ->helperText('Vazio = calculada pela população ÷ área.')
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            TextInput::make('renda')
                ->label('Renda média (R$)')
                ->numeric()->minValue(0)
                ->nullable()
                ->visible(fn (\Filament\Forms\Get $get) => $get('tipo') === 'setor_censitario'),
        ];
    }

    protected function mobZonaRecalcularArea(MobZona $z): void
    {
        try {
            DB::update('UPDATE mob_zonas SET area_geo = ST_Area(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$z->id]);
        } catch (\Throwable) {
            // tolerante (padrão das demais entidades)
        }
    }

    public function criarMobZonaAction(): Action
    {
        return Action::make('criarMobZona')
            ->modalHeading('Cadastrar Nova Zona de Estudo')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form($this->mobZonaFormulario())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobZona::create($data);
                $this->mobZonaRecalcularArea($registro);
                $registro->refresh();

                Notification::make()->title('Zona de estudo criada!')->success()->send();
                $this->dispatch('adicionar-mob_zonas-mapa', array_merge(
                    $this->mobZonaPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesMobZonaAction(): Action
    {
        return Action::make('opcoesMobZona')
            ->hiddenLabel()
            ->modalHeading(function () {
                $z = MobZona::withoutGlobalScopes()->find($this->mobZonaAtivoId);

                return ($z?->name ?: 'Zona #'.$z?->sequential_id).' ('.(MobZona::TIPOS[$z?->tipo] ?? $z?->tipo).')';
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $z = MobZona::withoutGlobalScopes()->find($this->mobZonaAtivoId);

                return [
                    'tipo' => $z?->tipo,
                    'name' => $z?->name,
                    'codigo' => $z?->codigo,
                    'situacao' => $z?->situacao,
                    'origens' => $z?->origens,
                    'destinos' => $z?->destinos,
                    'populacao' => $z?->populacao,
                    'densidade' => $z?->densidade,
                    'renda' => $z?->renda,
                ];
            })
            ->form($this->mobZonaFormulario())
            ->action(function (array $data) {
                $z = MobZona::withoutGlobalScopes()->find($this->mobZonaAtivoId);
                if (! $z) {
                    return;
                }
                $z->update($this->mobZonaCompletarDensidade($data, $z));
                $z->refresh();

                Notification::make()->title('Zona atualizada!')->success()->send();
                $this->dispatch('atualizar-mob_zonas-mapa', $this->mobZonaPayload($z));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_zona')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_zona', id: $this->mobZonaAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_zona')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobZona::withoutGlobalScopes()->find($this->mobZonaAtivoId)?->delete();
                        Notification::make()->title('Zona excluída!')->success()->send();
                        $this->dispatch('remover-mob_zonas-mapa', ['id' => $this->mobZonaAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobZona')]
    public function abrirOpcoesMobZona($id): void
    {
        $this->mobZonaAtivoId = (int) $id;
        $this->mountAction('opcoesMobZona');
    }

    #[On('salvarNovaGeometriaMobZona')]
    public function salvarNovaGeometriaMobZona($id, $geoJson): void
    {
        $z = MobZona::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $z) {
            return;
        }
        $z->update(['geo' => $geoJson]);
        $this->mobZonaRecalcularArea($z);
        $z->refresh();

        Notification::make()->title('Geometria da zona atualizada!')->success()->send();
        $this->dispatch('atualizar-mob_zonas-mapa', $this->mobZonaPayload($z));
    }
}
