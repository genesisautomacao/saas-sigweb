<?php

namespace App\Filament\Pages\Traits;

use App\Models\Zona;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasZonaActions
{
    public ?int $zonaAtivaId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $zonaAreaCalculada = null;

    public function criarZonaAction(): Action
    {
        return Action::make('criarZona')
            ->modalHeading('Cadastrar Nova Zona de Uso')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Zona')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->zonaAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->zonaAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Nome da Zona')
                    ->required()
                    ->maxLength(255),

                TextInput::make('sigla')
                    ->label('Sigla (Ex: ZR-1)')
                    ->required()
                    ->maxLength(50),

                Select::make('perimetro_id')
                    ->label('Distrito')
                    ->options(fn() => \App\Models\PerimetroUrbano::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                ColorPicker::make('rgb')
                    ->label('Cor no Mapa (RGB)')
                    ->rgb()
                    ->formatStateUsing(function ($state) {
                        if ($state && !str_contains($state, 'rgb') && !str_contains($state, '#')) {
                            $clean = str_replace(['(', ')'], '', $state);
                            return "rgb({$clean})";
                        }
                        return $state;
                    })
                    ->required(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                $data['area_geo'] = $this->zonaAreaCalculada;

                // Tratamento da cor para o padrão do banco
                if (str_contains($data['rgb'], 'rgb')) {
                    $rgbLimpo = str_replace(['rgb(', ')', ' '], '', $data['rgb']);
                    $data['rgb'] = '(' . $rgbLimpo . ')';
                }

                $registro = Zona::create($data);

                // Atualiza a área se a coluna existir (padrão de segurança)
                try {
                    DB::statement("UPDATE zonas SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {}

                Notification::make()->title('Zona de Uso Criada!')->success()->send();

                // Atualiza a lista lateral do Livewire em tempo real
                $this->atualizarZonasTipos();

                // Dispara pro Javascript adicionar o polígono
                $this->dispatch('adicionar-zona-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'sigla' => $registro->sigla,
                    'rgb' => $registro->rgb,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->zonaAreaCalculada = null;
            });
    }

   public function opcoesZonaAction(): Action
    {
        return Action::make('opcoesZona')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Zona: ' . Zona::find($this->zonaAtivaId)?->name)
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Zona::find($this->zonaAtivaId);
                $rgbFormatado = $reg?->rgb;
                if ($rgbFormatado && !str_contains($rgbFormatado, 'rgb')) {
                    $clean = str_replace(['(', ')'], '', $rgbFormatado);
                    $rgbFormatado = "rgb({$clean})";
                }

                return [
                    'name' => $reg?->name,
                    'sigla' => $reg?->sigla,
                    'perimetro_id' => $reg?->perimetro_id,
                    'rgb' => $rgbFormatado,
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = Zona::find($this->zonaAtivaId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')->label('Nome da Zona')->required()->maxLength(255),
                TextInput::make('sigla')->label('Sigla')->required()->maxLength(50),
                Select::make('perimetro_id')
                    ->label('Distrito')
                    ->options(fn() => \App\Models\PerimetroUrbano::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                ColorPicker::make('rgb')
                    ->label('Cor no Mapa (RGB)')
                    ->rgb()
                    ->required(),
            ])
            ->action(function (array $data) {
                $reg = Zona::find($this->zonaAtivaId);
                if ($reg) {
                    if (str_contains($data['rgb'], 'rgb')) {
                        $rgbLimpo = str_replace(['rgb(', ')', ' '], '', $data['rgb']);
                        $data['rgb'] = '(' . $rgbLimpo . ')';
                    }

                    $reg->update($data);

                    $this->atualizarZonasTipos(); // Atualiza a sanfona lateral

                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-zona', [
                        'id' => $reg->id,
                        'name' => $data['name'],
                        'sigla' => $data['sigla'],
                        'rgb' => $data['rgb']
                    ]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_zona')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        // O Gatilho para o Javascript habilitar os nós do polígono!
                        $this->dispatch('iniciar-edicao-geometria-zona', id: $this->zonaAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_zona')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        $reg = Zona::find($this->zonaAtivaId);
                        if($reg) {
                            $siglaExcluida = $reg->sigla;
                            $reg->delete();
                            Notification::make()->title('Excluída!')->success()->send();

                            $this->atualizarZonasTipos(); // Atualiza menu lateral

                            $this->dispatch('remover-zona-mapa', ['id' => $this->zonaAtivaId, 'sigla' => $siglaExcluida]);
                            $this->dispatch('fechar-modal-filament');
                        }
                    }),
            ]);
    }
}
