<?php

namespace App\Filament\Pages\Traits;

use App\Models\Bairro;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasBairroActions
{
    public ?int $bairroAtivoId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $bairroAreaCalculada = null;

    public function criarBairroAction(): Action
    {
        return Action::make('criarBairro')
            ->modalHeading('Cadastrar Novo Bairro')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Bairro')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->bairroAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->bairroAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Nome do Bairro')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                $data['area_geo'] = $this->bairroAreaCalculada;

                $registro = Bairro::create($data);

                // Atualiza a área automaticamente no PostGIS (envolto em try-catch caso a coluna area_geo não exista na sua model de Bairros)
                try {
                    DB::statement("UPDATE bairros SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {}

                Notification::make()->title('Bairro Criado!')->success()->send();

                $this->dispatch('adicionar-bairro-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                $this->bairroAreaCalculada = null;
            });
    }

   public function opcoesBairroAction(): Action
    {
        return Action::make('opcoesBairro')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Bairro: ' . Bairro::find($this->bairroAtivoId)?->name)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Bairro::find($this->bairroAtivoId);
                return [
                    'name' => $reg?->name,
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = Bairro::find($this->bairroAtivoId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')->label('Nome do Bairro')->required()->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = Bairro::find($this->bairroAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-bairro', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_bairro')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        // O Gatilho para o Javascript habilitar os nós do polígono!
                        $this->dispatch('iniciar-edicao-geometria-bairro', id: $this->bairroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_bairro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Bairro::where('id', $this->bairroAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-bairro-mapa', ['id' => $this->bairroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
