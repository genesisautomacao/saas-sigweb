<?php

namespace App\Filament\Pages\Traits;

use App\Models\Loteamento;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasLoteamentoActions
{
    public ?int $loteamentoAtivoId = null;

    public function criarLoteamentoAction(): Action
    {
        return Action::make('criarLoteamento')
            ->modalHeading('Cadastrar Novo Loteamento')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Loteamento')
            ->form([
                // 🛑 CORREÇÃO: Alterado de 'nome' para 'name'
                TextInput::make('name') 
                    ->label('Nome do Loteamento')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $registro = Loteamento::create($data);
                
                try {
                    DB::statement("UPDATE loteamentos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {}

                Notification::make()->title('Loteamento Criado!')->success()->send();

                $this->dispatch('adicionar-loteamento-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name, // 🛑 CORREÇÃO: Enviando 'name'
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

   public function opcoesLoteamentoAction(): Action
    {
        return Action::make('opcoesLoteamento')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Loteamento: ' . Loteamento::find($this->loteamentoAtivoId)?->name) // 🛑 CORREÇÃO
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Loteamento::find($this->loteamentoAtivoId);
                return [
                    'name' => $reg?->name, // 🛑 CORREÇÃO
                ];
            })
            ->form([
                TextInput::make('name')->label('Nome do Loteamento')->required()->maxLength(255), // 🛑 CORREÇÃO
            ])
            ->action(function (array $data) {
                $reg = Loteamento::find($this->loteamentoAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-loteamento', ['id' => $reg->id, 'name' => $data['name']]); // 🛑 CORREÇÃO
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_loteamento')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-loteamento', id: $this->loteamentoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_loteamento')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Loteamento::where('id', $this->loteamentoAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-loteamento-mapa', ['id' => $this->loteamentoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}