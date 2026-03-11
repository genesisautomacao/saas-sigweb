<?php

namespace App\Filament\Pages\Traits;

use App\Models\Jazigo;
use App\Models\QuadraCemiterio;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasJazigoActions
{
    public ?int $jazigoAtivoId = null;
    public ?int $quadraCemiterioPreSelecionadaId = null; // 🛑 Preenchido pelo PostGIS!

    public function criarJazigoAction(): Action
    {
        return Action::make('criarJazigo')
            ->modalHeading('Cadastrar Novo Jazigo / Túmulo')
            ->modalSubmitActionLabel('Salvar Jazigo')
            ->modalWidth('xl')
            ->form([
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('quadra_cemiterio_id')
                        ->label('Quadra (Detectada no Mapa)')
                        ->options(fn() => QuadraCemiterio::pluck('name', 'id'))
                        ->default(fn () => $this->quadraCemiterioPreSelecionadaId)
                        ->required(),
                        
                    TextInput::make('codigo')
                        ->label('Código (Ex: J-15)')
                        ->required()
                        ->maxLength(255),
                ]),
                
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(['gaveta' => 'Gaveta', 'chao' => 'Chão / Terra', 'mausoleu' => 'Mausoléu'])
                        ->required(),
                        
                    Select::make('status')
                        ->label('Status')
                        ->options(['disponivel' => '🟢 Disponível', 'ocupado' => '🔴 Ocupado', 'manutencao' => '🟡 Manutenção'])
                        ->default('disponivel')
                        ->required(),
                ]),

                Select::make('proprietario_id')
                    ->label('Proprietário / Responsável (Opcional)')
                    ->options(fn() => Pessoa::pluck('name', 'id'))
                    ->searchable()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $jazigo = Jazigo::create($data);
                DB::statement("UPDATE jazigos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$jazigo->id]);
                
                Notification::make()->title('Jazigo criado com sucesso!')->success()->send();

                $this->dispatch('adicionar-jazigo-mapa', [
                    'id' => $jazigo->id,
                    'name' => $jazigo->codigo,
                    'geo' => $this->geometriaRascunho
                ]);
                
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesJazigoAction(): Action
    {
        return Action::make('opcoesJazigo')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Jazigo #' . $this->jazigoAtivoId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $jazigo = Jazigo::find($this->jazigoAtivoId);
                return [
                    'quadra_cemiterio_id' => $jazigo ? $jazigo->quadra_cemiterio_id : null,
                    'codigo' => $jazigo ? $jazigo->codigo : null,
                    'tipo' => $jazigo ? $jazigo->tipo : null,
                    'status' => $jazigo ? $jazigo->status : null,
                    'proprietario_id' => $jazigo ? $jazigo->proprietario_id : null,
                ];
            })
            ->form([
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('quadra_cemiterio_id')->label('Quadra')->options(fn() => QuadraCemiterio::pluck('name', 'id'))->required(),
                    TextInput::make('codigo')->label('Código')->required(),
                ]),
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('tipo')->label('Tipo')->options(['gaveta' => 'Gaveta', 'chao' => 'Chão', 'mausoleu' => 'Mausoléu'])->required(),
                    Select::make('status')->label('Status')->options(['disponivel' => '🟢 Disponível', 'ocupado' => '🔴 Ocupado', 'manutencao' => '🟡 Manutenção'])->required(),
                ]),
                Select::make('proprietario_id')->label('Proprietário')->options(fn() => Pessoa::pluck('name', 'id'))->searchable()->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $jazigo = Jazigo::find($this->jazigoAtivoId);
                if ($jazigo) {
                    $jazigo->update($data);
                    Notification::make()->title('Dados do Jazigo Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-jazigo', ['id' => $jazigo->id, 'name' => $data['codigo']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-jazigo', id: $this->jazigoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_jazigo')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Jazigo::where('id', $this->jazigoAtivoId)->delete();
                        Notification::make()->title('Jazigo Excluído!')->success()->send();
                        $this->dispatch('remover-jazigo-mapa', ['id' => $this->jazigoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}