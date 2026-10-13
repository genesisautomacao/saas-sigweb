<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralHidrografia;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralHidrografiaActions
{
    public ?int $ruralHidrografiaAtivaId = null;

    public function criarRuralHidrografiaAction(): Action
    {
        return Action::make('criarRuralHidrografia')
            ->authorize(fn () => $this->podeNoMapa('rural_hidrografias', 'criar')) // R83
            ->modalHeading('Cadastrar Recurso Hídrico')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                TextInput::make('nome')->label('Nome (Opcional)')->placeholder('Ex: Rio Paraná, Lago Azul...')->maxLength(255),
                Select::make('tipo')
                    ->label('Tipo')
                    ->options(['Rio' => 'Rio', 'Córrego' => 'Córrego', 'Lago' => 'Lago', 'Represa' => 'Represa', 'Nascente' => 'Nascente', 'Canal' => 'Canal'])
                    ->required(),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Base')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralLocalidadePreSelecionadaId) // Injeção automática da trava
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('rural_hidrografias', 'criar'); // R83

                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralHidrografia::create($data);

                Notification::make()->title('Recurso Hídrico Cadastrado!')->success()->send();

                $this->dispatch('adicionar-rural_hidrografia-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome ?? $registro->tipo, // Manda o Tipo se não tiver nome
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralHidrografiaAction(): Action
    {
        return Action::make('opcoesRuralHidrografia')
            ->hiddenLabel()
            // R83: ver = abre a ficha; sem "editar" o formulário fica só leitura e sem Salvar
            ->authorize(fn () => $this->podeNoMapa('rural_hidrografias', 'mapa'))
            ->disabledForm(fn () => ! $this->podeNoMapa('rural_hidrografias', 'editar'))
            ->modalSubmitAction(fn ($action) => $this->podeNoMapa('rural_hidrografias', 'editar') ? $action : false)
            ->modalHeading(fn () => 'Editar Hidrografia: ' . (RuralHidrografia::find($this->ruralHidrografiaAtivaId)?->nome ?? 'Sem Nome'))
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralHidrografia::find($this->ruralHidrografiaAtivaId);
                return [
                    'nome' => $reg?->nome,
                    'tipo' => $reg?->tipo,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                ];
            })
            ->form([
                TextInput::make('nome')->label('Nome (Opcional)')->maxLength(255),
                Select::make('tipo')->label('Tipo')->options(['Rio' => 'Rio', 'Córrego' => 'Córrego', 'Lago' => 'Lago', 'Represa' => 'Represa', 'Nascente' => 'Nascente', 'Canal' => 'Canal'])->required(),
                Select::make('rural_localidade_id')->label('Localidade / Distrito Base')->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))->searchable()->required(),
            ])
            ->action(function (array $data) {
                $this->exigirNoMapa('rural_hidrografias', 'editar'); // R83

                $reg = RuralHidrografia::find($this->ruralHidrografiaAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_hidrografia', ['id' => $reg->id, 'name' => $data['nome'] ?? $data['tipo']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_hidrografia')
                    ->label('Editar Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->visible(fn () => $this->podeNoMapa('rural_hidrografias', 'editar')) // R83
                    ->action(function () {
                        $this->exigirNoMapa('rural_hidrografias', 'editar');
                        $this->dispatch('iniciar-edicao-geometria-rural_hidrografia', id: $this->ruralHidrografiaAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_hidrografia')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->visible(fn () => $this->podeNoMapa('rural_hidrografias', 'excluir')) // R83
                    ->action(function() {
                        $this->exigirNoMapa('rural_hidrografias', 'excluir');
                        RuralHidrografia::find($this->ruralHidrografiaAtivaId)?->delete();
                        Notification::make()->title('Excluída!')->success()->send();
                        $this->dispatch('remover-rural_hidrografia-mapa', ['id' => $this->ruralHidrografiaAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    })
            ]);
    }
}