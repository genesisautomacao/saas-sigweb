<?php

namespace App\Filament\Pages\Traits;

use App\Models\Loteamento;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasLoteamentoActions
{
    public ?int $loteamentoAtivoId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $loteamentoAreaCalculada = null;

    public function criarLoteamentoAction(): Action
    {
        return Action::make('criarLoteamento')
            ->modalHeading('Cadastrar Novo Loteamento')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Loteamento')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->loteamentoAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->loteamentoAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Nome do Loteamento')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                $data['area_geo'] = $this->loteamentoAreaCalculada;

                $registro = Loteamento::create($data);

                try {
                    DB::statement("UPDATE loteamentos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {}

                Notification::make()->title('Loteamento Criado!')->success()->send();

                $this->dispatch('adicionar-loteamento-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->loteamentoAreaCalculada = null;
            });
    }

   public function opcoesLoteamentoAction(): Action
    {
        return Action::make('opcoesLoteamento')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Loteamento: ' . Loteamento::find($this->loteamentoAtivoId)?->name)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Loteamento::find($this->loteamentoAtivoId);
                return [
                    'name' => $reg?->name,
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = Loteamento::find($this->loteamentoAtivoId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')->label('Nome do Loteamento')->required()->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = Loteamento::find($this->loteamentoAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-loteamento', ['id' => $reg->id, 'name' => $data['name']]);
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
