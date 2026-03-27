<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasRuralLocalidadeActions
{
    public ?int $ruralLocalidadeAtivaId = null;

    public function criarRuralLocalidadeAction(): Action
    {
        return Action::make('criarRuralLocalidade')
            ->modalHeading('Cadastrar Nova Localidade / Distrito')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Localidade')
            ->form([
                TextInput::make('nome')
                    ->label('Nome da Localidade')
                    ->required()
                    ->maxLength(255),
                Select::make('tipo')
                    ->options([
                        'Distrito' => 'Distrito',
                        'Vila' => 'Vila',
                        'Povoado' => 'Povoado',
                        'Assentamento' => 'Assentamento',
                    ])->required(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $registro = RuralLocalidade::create($data);
                
                // Atualiza área via PostGIS
                DB::statement("UPDATE rural_localidades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);

                Notification::make()->title('Localidade Criada!')->success()->send();

                $this->dispatch('adicionar-rural_localidade-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

   public function opcoesRuralLocalidadeAction(): Action
    {
        return Action::make('opcoesRuralLocalidade')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Localidade: ' . RuralLocalidade::find($this->ruralLocalidadeAtivaId)?->nome)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralLocalidade::find($this->ruralLocalidadeAtivaId);
                return [
                    'nome' => $reg?->nome,
                    'tipo' => $reg?->tipo,
                ];
            })
            ->form([
                TextInput::make('nome')->label('Nome da Localidade')->required(),
                Select::make('tipo')->options([
                    'Distrito' => 'Distrito',
                    'Vila' => 'Vila',
                    'Povoado' => 'Povoado',
                    'Assentamento' => 'Assentamento'
                ])->required(),
            ])
            ->action(function (array $data) {
                $reg = RuralLocalidade::find($this->ruralLocalidadeAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_localidade', ['id' => $reg->id, 'name' => $data['nome']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_localidade')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        // Este evento será capturado pelo JS no próximo passo!
                        $this->dispatch('iniciar-edicao-geometria-rural_localidade', id: $this->ruralLocalidadeAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_localidade')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        RuralLocalidade::where('id', $this->ruralLocalidadeAtivaId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-rural_localidade-mapa', ['id' => $this->ruralLocalidadeAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}