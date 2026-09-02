<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobPontoInteresse;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

/** Ponto de interesse da mobilidade no mapa (docs/piuma.txt §2.3, Onda 2). */
trait HasMobPontoInteresseActions
{
    public ?int $mobPontoInteresseAtivoId = null;

    protected function mobPontoInteressePayload(MobPontoInteresse $p): array
    {
        return [
            'id' => $p->id,
            'layer' => 'mob_pontos_interesse',
            'name' => $p->name ?: 'POI #'.$p->sequential_id,
            'sequential_id' => $p->sequential_id,
            'categoria' => $p->categoria,
            'numero' => $p->numero,
        ];
    }

    protected function mobPontoInteresseFormulario(): array
    {
        return [
            Select::make('categoria')
                ->label('Categoria')
                ->options(MobPontoInteresse::CATEGORIAS)
                ->required(),
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('numero')->label('Número / referência')->maxLength(50)->nullable(),
        ];
    }

    public function criarMobPontoInteresseAction(): Action
    {
        return Action::make('criarMobPontoInteresse')
            ->modalHeading('Cadastrar Novo Ponto de Interesse')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form($this->mobPontoInteresseFormulario())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobPontoInteresse::create($data);
                $registro->refresh();

                Notification::make()->title('Ponto de interesse criado!')->success()->send();
                $this->dispatch('adicionar-mob_pontos_interesse-mapa', array_merge(
                    $this->mobPontoInteressePayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesMobPontoInteresseAction(): Action
    {
        return Action::make('opcoesMobPontoInteresse')
            ->hiddenLabel()
            ->modalHeading(fn () => 'POI #'.MobPontoInteresse::withoutGlobalScopes()->find($this->mobPontoInteresseAtivoId)?->sequential_id)
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $p = MobPontoInteresse::withoutGlobalScopes()->find($this->mobPontoInteresseAtivoId);

                return [
                    'categoria' => $p?->categoria,
                    'name' => $p?->name,
                    'numero' => $p?->numero,
                ];
            })
            ->form($this->mobPontoInteresseFormulario())
            ->action(function (array $data) {
                $p = MobPontoInteresse::withoutGlobalScopes()->find($this->mobPontoInteresseAtivoId);
                if (! $p) {
                    return;
                }
                $p->update($data);
                $p->refresh();

                Notification::make()->title('Ponto de interesse atualizado!')->success()->send();
                $this->dispatch('atualizar-mob_pontos_interesse-mapa', $this->mobPontoInteressePayload($p));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_ponto_interesse')
                    ->label('Reposicionar')
                    ->color('warning')
                    ->icon('heroicon-o-map-pin')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_ponto_interesse', id: $this->mobPontoInteresseAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_ponto_interesse')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobPontoInteresse::withoutGlobalScopes()->find($this->mobPontoInteresseAtivoId)?->delete();
                        Notification::make()->title('Ponto excluído!')->success()->send();
                        $this->dispatch('remover-mob_pontos_interesse-mapa', ['id' => $this->mobPontoInteresseAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobPontoInteresse')]
    public function abrirOpcoesMobPontoInteresse($id): void
    {
        $this->mobPontoInteresseAtivoId = (int) $id;
        $this->mountAction('opcoesMobPontoInteresse');
    }

    #[On('salvarNovaGeometriaMobPontoInteresse')]
    public function salvarNovaGeometriaMobPontoInteresse($id, $geoJson): void
    {
        $p = MobPontoInteresse::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $p) {
            return;
        }
        $p->update(['geo' => $geoJson]);

        Notification::make()->title('Posição do ponto atualizada!')->success()->send();
    }
}
