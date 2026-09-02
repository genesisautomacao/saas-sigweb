<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobFluxo;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

/**
 * Linha de desejo O/D no mapa (docs/piuma.txt §2.7, Onda 2) — espessura no
 * estilo é proporcional a `valores` (volume de deslocamentos).
 */
trait HasMobFluxoActions
{
    public ?int $mobFluxoAtivoId = null;

    protected function mobFluxoPayload(MobFluxo $f): array
    {
        return [
            'id' => $f->id,
            'layer' => 'mob_fluxos',
            'name' => 'Fluxo → '.(MobFluxo::DESTINOS[$f->destino] ?? ucfirst((string) $f->destino)),
            'sequential_id' => $f->sequential_id,
            'destino' => $f->destino,
            'valores' => (int) $f->valores,
        ];
    }

    protected function mobFluxoFormulario(): array
    {
        return [
            Select::make('destino')
                ->label('Região de destino')
                ->options(MobFluxo::DESTINOS)
                ->required(),
            TextInput::make('valores')
                ->label('Volume de deslocamentos')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->helperText('Define a espessura da linha no mapa.'),
        ];
    }

    public function criarMobFluxoAction(): Action
    {
        return Action::make('criarMobFluxo')
            ->modalHeading('Cadastrar Novo Fluxo O/D')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form($this->mobFluxoFormulario())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobFluxo::create($data);
                $registro->refresh();

                Notification::make()->title('Fluxo O/D criado!')->success()->send();
                $this->dispatch('adicionar-mob_fluxos-mapa', array_merge(
                    $this->mobFluxoPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesMobFluxoAction(): Action
    {
        return Action::make('opcoesMobFluxo')
            ->hiddenLabel()
            ->modalHeading(function () {
                $f = MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId);

                return 'Fluxo O/D #'.$f?->sequential_id.' → '.(MobFluxo::DESTINOS[$f?->destino] ?? $f?->destino);
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $f = MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId);

                return [
                    'destino' => $f?->destino,
                    'valores' => $f?->valores,
                ];
            })
            ->form($this->mobFluxoFormulario())
            ->action(function (array $data) {
                $f = MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId);
                if (! $f) {
                    return;
                }
                $f->update($data);
                $f->refresh();

                Notification::make()->title('Fluxo atualizado!')->success()->send();
                $this->dispatch('atualizar-mob_fluxos-mapa', $this->mobFluxoPayload($f));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_fluxo')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_fluxo', id: $this->mobFluxoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_fluxo')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId)?->delete();
                        Notification::make()->title('Fluxo excluído!')->success()->send();
                        $this->dispatch('remover-mob_fluxos-mapa', ['id' => $this->mobFluxoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobFluxo')]
    public function abrirOpcoesMobFluxo($id): void
    {
        $this->mobFluxoAtivoId = (int) $id;
        $this->mountAction('opcoesMobFluxo');
    }

    #[On('salvarNovaGeometriaMobFluxo')]
    public function salvarNovaGeometriaMobFluxo($id, $geoJson): void
    {
        $f = MobFluxo::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $f) {
            return;
        }
        $f->update(['geo' => $geoJson]);
        $f->refresh();

        Notification::make()->title('Geometria do fluxo atualizada!')->success()->send();
        $this->dispatch('atualizar-mob_fluxos-mapa', $this->mobFluxoPayload($f));
    }
}
