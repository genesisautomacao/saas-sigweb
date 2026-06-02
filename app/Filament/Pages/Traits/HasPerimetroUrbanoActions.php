<?php

namespace App\Filament\Pages\Traits;

use App\Models\PerimetroUrbano;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasPerimetroUrbanoActions
{
    public ?int $perimetroUrbanoAtivoId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $perimetroUrbanoAreaCalculada = null;

    public function criarPerimetroUrbanoAction(): Action
    {
        return Action::make('criarPerimetroUrbano')
            ->modalHeading('Cadastrar Novo Distrito / Limite')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Distrito')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->perimetroUrbanoAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->perimetroUrbanoAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('distrito')
                    ->label('Distrito (opcional)')
                    ->helperText('Ex: Distrito, Limite Municipal, Perímetro Urbano...')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo']       = $this->geometriaRascunho;
                $data['code']      = (string) Str::uuid();
                $data['area_geo']  = $this->perimetroUrbanoAreaCalculada;

                $registro = PerimetroUrbano::create($data);

                // Cacheia área (m²) calculada via PostGIS — falha silenciosa caso a coluna
                // ainda não exista em ambientes legados.
                try {
                    DB::statement('UPDATE perimetros_urbanos SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$registro->id]);
                } catch (\Throwable $e) {
                }

                Notification::make()->title('Distrito / Limite criado!')->success()->send();

                $this->dispatch('adicionar-perimetro-urbano-mapa', [
                    'id'   => $registro->id,
                    'name' => $registro->name,
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->perimetroUrbanoAreaCalculada = null;
            });
    }

    public function opcoesPerimetroUrbanoAction(): Action
    {
        return Action::make('opcoesPerimetroUrbano')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Editar Distrito: ' . PerimetroUrbano::find($this->perimetroUrbanoAtivoId)?->name)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = PerimetroUrbano::find($this->perimetroUrbanoAtivoId);
                return [
                    'name'     => $reg?->name,
                    'distrito' => $reg?->distrito,
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = PerimetroUrbano::find($this->perimetroUrbanoAtivoId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')->label('Nome')->required()->maxLength(255),
                TextInput::make('distrito')->label('Distrito (opcional)')->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = PerimetroUrbano::find($this->perimetroUrbanoAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-perimetro-urbano', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_perimetro_urbano')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-perimetro_urbano', id: $this->perimetroUrbanoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_perimetro_urbano')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        PerimetroUrbano::where('id', $this->perimetroUrbanoAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-perimetro-urbano-mapa', ['id' => $this->perimetroUrbanoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
