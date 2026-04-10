<?php

namespace App\Filament\Pages\Traits;

use App\Models\Zona;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ColorPicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasZonaActions
{
    public ?int $zonaAtivaId = null;

    public function criarZonaAction(): Action
    {
        return Action::make('criarZona')
            ->modalHeading('Cadastrar Nova Zona de Uso')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Zona')
            ->form([
                TextInput::make('name')
                    ->label('Nome da Zona')
                    ->required()
                    ->maxLength(255),
                
                TextInput::make('sigla')
                    ->label('Sigla (Ex: ZR-1)')
                    ->required()
                    ->maxLength(50),

                Select::make('perimetro_id')
                    ->label('Perímetro Urbano')
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
                TextInput::make('name')->label('Nome da Zona')->required()->maxLength(255),
                TextInput::make('sigla')->label('Sigla')->required()->maxLength(50),
                Select::make('perimetro_id')
                    ->label('Perímetro Urbano')
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