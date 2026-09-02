<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobEixo;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Eixo de mobilidade no mapa (docs/piuma.txt §2.5, Onda 2): ciclovia, eixo
 * comercial, rota de carga, rodovia. Extensão em METROS, exibida em km.
 */
trait HasMobEixoActions
{
    public ?int $mobEixoAtivoId = null;

    protected function mobEixoPayload(MobEixo $e): array
    {
        return [
            'id' => $e->id,
            'layer' => 'mob_eixos',
            'name' => $e->name ?: 'Eixo #'.$e->sequential_id,
            'sequential_id' => $e->sequential_id,
            'tipo' => $e->tipo,
            'extensao_geo' => $e->extensao_geo !== null ? (float) $e->extensao_geo : null,
        ];
    }

    protected function mobEixoFormulario(): array
    {
        return [
            Select::make('tipo')
                ->label('Tipo do eixo')
                ->options(MobEixo::TIPOS)
                ->required(),
            TextInput::make('name')->label('Nome')->maxLength(255)->nullable(),
            ...\App\Services\Coleta\CampoCustomizadoService::componentes('mob_eixo'),
        ];
    }

    protected function mobEixoRecalcularExtensao(MobEixo $e): void
    {
        try {
            DB::update('UPDATE mob_eixos SET extensao_geo = ST_Length(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$e->id]);
        } catch (\Throwable) {
            // tolerante (padrão das demais entidades)
        }
    }

    public function criarMobEixoAction(): Action
    {
        return Action::make('criarMobEixo')
            ->modalHeading('Cadastrar Novo Eixo de Mobilidade')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->mobExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">'.number_format($this->mobExtensaoCalculada / 1000, 2, ',', '.').' km</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                ...$this->mobEixoFormulario(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobEixo::create($data);
                $this->mobEixoRecalcularExtensao($registro);
                $registro->refresh();

                Notification::make()->title('Eixo criado!')->success()->send();
                $this->dispatch('adicionar-mob_eixos-mapa', array_merge(
                    $this->mobEixoPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
                $this->mobExtensaoCalculada = null;
            });
    }

    public function opcoesMobEixoAction(): Action
    {
        return Action::make('opcoesMobEixo')
            ->hiddenLabel()
            ->modalHeading(function () {
                $e = MobEixo::withoutGlobalScopes()->find($this->mobEixoAtivoId);

                return ($e?->name ?: 'Eixo #'.$e?->sequential_id).' ('.(MobEixo::TIPOS[$e?->tipo] ?? $e?->tipo).')';
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $e = MobEixo::withoutGlobalScopes()->find($this->mobEixoAtivoId);

                return [
                    'tipo' => $e?->tipo,
                    'name' => $e?->name,
                    'dados_customizados' => $e?->dados_customizados ?? [],
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Extensão')
                    ->content(function (): HtmlString {
                        $e = MobEixo::withoutGlobalScopes()->find($this->mobEixoAtivoId);

                        return new HtmlString(
                            $e?->extensao_geo !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">'.number_format((float) $e->extensao_geo / 1000, 2, ',', '.').' km</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),
                ...$this->mobEixoFormulario(),
            ])
            ->action(function (array $data) {
                $e = MobEixo::withoutGlobalScopes()->find($this->mobEixoAtivoId);
                if (! $e) {
                    return;
                }
                $e->update($data);
                $e->refresh();

                Notification::make()->title('Eixo atualizado!')->success()->send();
                $this->dispatch('atualizar-mob_eixos-mapa', $this->mobEixoPayload($e));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_eixo')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_eixo', id: $this->mobEixoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_eixo')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobEixo::withoutGlobalScopes()->find($this->mobEixoAtivoId)?->delete();
                        Notification::make()->title('Eixo excluído!')->success()->send();
                        $this->dispatch('remover-mob_eixos-mapa', ['id' => $this->mobEixoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobEixo')]
    public function abrirOpcoesMobEixo($id): void
    {
        $this->mobEixoAtivoId = (int) $id;
        $this->mountAction('opcoesMobEixo');
    }

    #[On('salvarNovaGeometriaMobEixo')]
    public function salvarNovaGeometriaMobEixo($id, $geoJson): void
    {
        $e = MobEixo::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $e) {
            return;
        }
        $e->update(['geo' => $geoJson]);
        $this->mobEixoRecalcularExtensao($e);
        $e->refresh();

        Notification::make()->title('Geometria do eixo atualizada!')->success()->send();
        $this->dispatch('atualizar-mob_eixos-mapa', $this->mobEixoPayload($e));
    }
}
