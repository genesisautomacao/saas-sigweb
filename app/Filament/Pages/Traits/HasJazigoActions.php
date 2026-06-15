<?php

namespace App\Filament\Pages\Traits;

use App\Models\Jazigo;
use App\Models\JazigoFalecido;
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
            ->modalWidth('3xl')
            ->form([
                \Filament\Forms\Components\Grid::make(2)->schema([
                    Select::make('quadra_cemiterio_id')
                        ->label('Quadra (Detectada no Mapa)')
                        ->options(fn() => QuadraCemiterio::pluck('name', 'id'))
                        ->default(fn() => $this->quadraCemiterioPreSelecionadaId)
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
            ->modalHeading(fn() => 'Jazigo #' . $this->jazigoAtivoId)
            ->modalDescription(function (): string {
                $count = JazigoFalecido::query()->where('jazigo_id', $this->jazigoAtivoId)->count();
                return $count > 0
                    ? "{$count} falecido(s) registrado(s) neste jazigo."
                    : 'Nenhum falecido registrado ainda.';
            })
            ->modalWidth('3xl')
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
                Action::make('ver_falecidos')
                    ->label('Falecidos')
                    ->color('info')
                    ->icon('heroicon-o-heart')
                    ->action(fn() => $this->replaceMountedAction('verFalecidosJazigo')),

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
                    ->action(function () {
                        Jazigo::where('id', $this->jazigoAtivoId)->delete();
                        Notification::make()->title('Jazigo Excluído!')->success()->send();
                        $this->dispatch('remover-jazigo-mapa', ['id' => $this->jazigoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    public function verFalecidosJazigoAction(): Action
    {
        return Action::make('verFalecidosJazigo')
            ->modalHeading(fn() => 'Falecidos — Jazigo #' . $this->jazigoAtivoId)
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function () {
                $falecidos = JazigoFalecido::query()
                    ->with('pessoa')
                    ->where('jazigo_id', $this->jazigoAtivoId)
                    ->orderBy('data_sepultamento', 'desc')
                    ->get();

                $bladeView = <<<'BLADE'
                    @if($falecidos->isEmpty())
                        <div class="text-center py-8 text-gray-500 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                            <x-heroicon-o-heart class="w-10 h-10 mx-auto text-gray-400 mb-2 opacity-50" />
                            Nenhum falecido registrado neste jazigo.
                        </div>
                    @else
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-3">Nome</th>
                                        <th class="px-4 py-3">Óbito</th>
                                        <th class="px-4 py-3">Sepultamento</th>
                                        <th class="px-4 py-3">Certidão</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($falecidos as $f)
                                        <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                                                {{ $f->pessoa?->name ?? $f->nome_falecido ?? '—' }}
                                                @if($f->pessoa_id)
                                                    <x-filament::badge color="success" size="sm" class="ml-1">Cadastrado</x-filament::badge>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">{{ $f->data_obito?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="px-4 py-3">{{ $f->data_sepultamento?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="px-4 py-3 text-xs text-gray-500">{{ $f->numero_certidao_obito ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                BLADE;

                return new \Illuminate\Support\HtmlString(
                    \Illuminate\Support\Facades\Blade::render($bladeView, ['falecidos' => $falecidos])
                );
            })
            ->extraModalFooterActions([
                Action::make('voltar_opcoes')
                    ->label('Voltar')
                    ->color('gray')
                    ->action(fn() => $this->replaceMountedAction('opcoesJazigo')),
            ]);
    }
}
