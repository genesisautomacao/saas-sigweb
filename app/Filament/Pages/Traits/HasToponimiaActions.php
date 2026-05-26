<?php

namespace App\Filament\Pages\Traits;

use App\Models\Toponimia;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HasToponimiaActions
{
    public ?string $toponimiaAtivaId = null;

    // -------------------------------------------------------------------------
    // Listener: JS envia lat/lon após clique no mapa em modo Toponímia
    // -------------------------------------------------------------------------
    #[On('abrirModalToponimia')]
    public function abrirModalToponimia(float $lat, float $lon): void
    {
        $this->mountAction('criarToponimiaAction', ['lat' => $lat, 'lon' => $lon]);
    }

    // -------------------------------------------------------------------------
    // Action: criar nova Toponímia
    // -------------------------------------------------------------------------
    public function criarToponimiaAction(): Action
    {
        return Action::make('criarToponimiaAction')
            ->modalHeading('Adicionar Texto no Mapa')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                TextInput::make('texto')
                    ->label('Texto')
                    ->required()
                    ->maxLength(120)
                    ->autofocus(),

                Select::make('tamanho')
                    ->label('Tamanho da fonte')
                    ->options([
                        '12' => 'Pequeno (12px)',
                        '16' => 'Médio (16px)',
                        '22' => 'Grande (22px)',
                        '30' => 'Muito grande (30px)',
                    ])
                    ->default('16'),

                Select::make('cor')
                    ->label('Cor do texto')
                    ->options([
                        '#1f2937' => 'Preto',
                        '#1e3a8a' => 'Azul escuro',
                        '#166534' => 'Verde escuro',
                        '#991b1b' => 'Vermelho escuro',
                        '#ffffff' => 'Branco',
                    ])
                    ->default('#1f2937'),
            ])
            ->action(function (array $data, array $arguments): void {
                $tenant = \Filament\Facades\Filament::getTenant();
                if (! $tenant) return;

                $lat = (float) ($arguments['lat'] ?? 0);
                $lon = (float) ($arguments['lon'] ?? 0);

                $top = new Toponimia();
                $top->tenant_id = $tenant->id;
                $top->texto     = $data['texto'];
                $top->lat       = $lat;
                $top->lon       = $lon;
                $top->estilo    = [
                    'tamanho' => $data['tamanho'] ?? '16',
                    'cor'     => $data['cor'] ?? '#1f2937',
                ];
                $top->save();

                // Atualiza a coluna geo via raw SQL (padrão PostGIS do projeto)
                \Illuminate\Support\Facades\DB::table('toponimias')
                    ->where('id', $top->id)
                    ->update(['geo' => \Illuminate\Support\Facades\DB::raw(
                        "ST_SetSRID(ST_MakePoint({$lon},{$lat}),4326)"
                    )]);

                $this->dispatch('adicionar-toponimia-mapa', [
                    'id'     => $top->id,
                    'lat'    => $lat,
                    'lon'    => $lon,
                    'texto'  => $top->texto,
                    'estilo' => $top->estilo,
                ]);

                Notification::make()
                    ->title('Texto adicionado ao mapa')
                    ->success()
                    ->send();
            });
    }

    // -------------------------------------------------------------------------
    // Action: editar Toponímia existente (disparada pelo clique no mapa)
    // -------------------------------------------------------------------------
    #[On('abrirOpcoesToponimiia')]
    public function abrirOpcoesToponimia(string $id): void
    {
        $this->toponimiaAtivaId = $id;
        $this->mountAction('opcoesToponimiiaAction');
    }

    public function opcoesToponimiiaAction(): Action
    {
        return Action::make('opcoesToponimiiaAction')
            ->modalHeading('Opções da Toponímia')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Salvar')
            ->fillForm(function (): array {
                $top = Toponimia::find($this->toponimiaAtivaId);
                return [
                    'texto'   => $top?->texto ?? '',
                    'tamanho' => $top?->estilo['tamanho'] ?? '16',
                    'cor'     => $top?->estilo['cor'] ?? '#1f2937',
                ];
            })
            ->form([
                TextInput::make('texto')->label('Texto')->required()->maxLength(120),
                Select::make('tamanho')
                    ->label('Tamanho da fonte')
                    ->options([
                        '12' => 'Pequeno (12px)',
                        '16' => 'Médio (16px)',
                        '22' => 'Grande (22px)',
                        '30' => 'Muito grande (30px)',
                    ]),
                Select::make('cor')
                    ->label('Cor do texto')
                    ->options([
                        '#1f2937' => 'Preto',
                        '#1e3a8a' => 'Azul escuro',
                        '#166534' => 'Verde escuro',
                        '#991b1b' => 'Vermelho escuro',
                        '#ffffff' => 'Branco',
                    ]),
            ])
            ->extraModalFooterActions([
                Action::make('excluirToponimia')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $idParaRemover = $this->toponimiaAtivaId;
                        Toponimia::find($idParaRemover)?->delete();
                        $this->toponimiaAtivaId = null;
                        $this->dispatch('remover-toponimia-mapa', ['id' => $idParaRemover]);
                        $this->dispatch('fechar-modal-filament');
                        Notification::make()->title('Texto removido')->success()->send();
                    }),
            ])
            ->action(function (array $data): void {
                $top = Toponimia::find($this->toponimiaAtivaId);
                if (! $top) return;

                $top->texto  = $data['texto'];
                $top->estilo = [
                    'tamanho' => $data['tamanho'] ?? '16',
                    'cor'     => $data['cor'] ?? '#1f2937',
                ];
                $top->save();

                $this->dispatch('atualizar-label-toponimia', [
                    'id'     => $top->id,
                    'texto'  => $top->texto,
                    'estilo' => $top->estilo,
                ]);
                Notification::make()->title('Texto atualizado')->success()->send();
            });
    }
}
