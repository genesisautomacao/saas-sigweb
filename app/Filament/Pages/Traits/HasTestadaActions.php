<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use App\Models\LoteTestada;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

trait HasTestadaActions
{
    public function toggleTestadasLote(): void
    {
        $this->mostrarTestadasLoteAtivo = !$this->mostrarTestadasLoteAtivo;

        if ($this->mostrarTestadasLoteAtivo && $this->loteAtivoId) {
            $testadas = LoteTestada::where('lote_id', $this->loteAtivoId)
                ->whereNotNull('geo')
                ->get()
                ->map(fn($t) => [
                    'id'          => $t->id,
                    'tipo'        => $t->tipo,
                    'comprimento' => $t->comprimento ? (float) $t->comprimento : null,
                    'geo'         => $t->geo_json,
                ])
                ->filter(fn($t) => !is_null($t['geo']))
                ->values()
                ->toArray();

            $this->dispatch('mostrar-testadas-lote', testadas: $testadas);
        } else {
            $this->dispatch('esconder-testadas-lote');
        }
    }

    public function criarTestadaAction(): Action
    {
        return Action::make('criarTestada')
            ->modalHeading('Cadastrar Nova Testada')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Testada')
            ->form([
                Placeholder::make('comprimento_calculado')
                    ->label('Comprimento calculado pela linha desenhada')
                    ->content(fn(): HtmlString => new HtmlString(
                        $this->testadaExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->testadaExtensaoCalculada, 2, ',', '.') . ' m</strong>'
                            : '<em style="color:#9ca3af;">Desenhe a linha no mapa para calcular automaticamente.</em>'
                    )),

                Select::make('tipo')
                    ->label('Tipo de Testada')
                    ->options(['principal' => 'Principal', 'secundaria' => 'Secundária'])
                    ->default('secundaria')
                    ->required()
                    ->helperText('Apenas uma testada pode ser Principal por lote.'),

                TextInput::make('comprimento')
                    ->label('Comprimento (m) — ajuste manual')
                    ->numeric()
                    ->minValue(0.01)
                    ->nullable()
                    ->placeholder(fn() => $this->testadaExtensaoCalculada ? number_format($this->testadaExtensaoCalculada, 2, '.') : null),

                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->options(
                        fn() => Logradouro::where('tenant_id', $this->tenantId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->searchable()
                    ->nullable(),
            ])
            ->action(function (array $data) {
                // Usa comprimento manual se informado, senão o calculado pela linha
                if (empty($data['comprimento'])) {
                    $data['comprimento'] = $this->testadaExtensaoCalculada;
                }

                $data['tenant_id'] = $this->tenantId;
                $data['lote_id']   = $this->loteAtivoId;
                $data['geo']       = $this->geometriaRascunho;

                // Garante unicidade da testada principal neste lote
                if ($data['tipo'] === 'principal') {
                    LoteTestada::where('lote_id', $this->loteAtivoId)
                        ->where('tipo', 'principal')
                        ->update(['tipo' => 'secundaria']);
                }

                $testada = LoteTestada::create($data);

                // Recalcula comprimento direto do PostGIS se a geo foi desenhada
                if ($this->geometriaRascunho) {
                    try {
                        DB::statement(
                            'UPDATE lote_testadas SET comprimento = ST_Length(geo::geography) WHERE id = ? AND comprimento IS NULL',
                            [$testada->id]
                        );
                        $testada->refresh();
                    } catch (\Throwable $e) {
                    }
                }

                // Sincroniza main_facade_length no Lote
                if ($testada->tipo === 'principal' && $testada->comprimento) {
                    try {
                        DB::statement(
                            'UPDATE lotes SET main_facade_length = ? WHERE id = ?',
                            [(float) $testada->comprimento, $this->loteAtivoId]
                        );
                        $this->loteFacePrincipal = (float) $testada->comprimento;
                    } catch (\Throwable $e) {
                    }
                }

                Notification::make()->title('Testada criada!')->success()->send();
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->testadaExtensaoCalculada = null;

                // Atualiza a camada de testadas no mapa
                $this->mostrarTestadasLoteAtivo = false;
                $this->toggleTestadasLote();
            });
    }

    public function opcoesTestadaAction(): Action
    {
        return Action::make('opcoesTestada')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Testada #' . $this->testadaAtivaId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $t = LoteTestada::find($this->testadaAtivaId);
                return [
                    'tipo'         => $t?->tipo,
                    'comprimento'  => $t?->comprimento,
                    'logradouro_id' => $t?->logradouro_id,
                ];
            })
            ->form([
                Placeholder::make('extensao_atual')
                    ->label('Comprimento atual')
                    ->content(function (): HtmlString {
                        $t = LoteTestada::find($this->testadaAtivaId);
                        $v = $t?->comprimento;
                        return new HtmlString(
                            $v !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float)$v, 2, ',', '.') . ' m</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                Select::make('tipo')
                    ->label('Tipo de Testada')
                    ->options(['principal' => 'Principal', 'secundaria' => 'Secundária'])
                    ->required()
                    ->helperText('Mudar para Principal rebaixa a testada principal atual para Secundária.'),

                TextInput::make('comprimento')
                    ->label('Comprimento (m)')
                    ->numeric()
                    ->nullable(),

                Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->options(
                        fn() => Logradouro::where('tenant_id', $this->tenantId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->searchable()
                    ->nullable(),
            ])
            ->action(function (array $data) {
                $testada = LoteTestada::find($this->testadaAtivaId);
                if (!$testada) {
                    return;
                }

                // Garante unicidade da testada principal
                if ($data['tipo'] === 'principal' && $testada->tipo !== 'principal') {
                    LoteTestada::where('lote_id', $testada->lote_id)
                        ->where('tipo', 'principal')
                        ->where('id', '!=', $this->testadaAtivaId)
                        ->update(['tipo' => 'secundaria']);
                }

                $testada->update($data);
                $testada->refresh();

                // Sincroniza main_facade_length
                if ($testada->tipo === 'principal') {
                    try {
                        DB::statement(
                            'UPDATE lotes SET main_facade_length = ? WHERE id = ?',
                            [(float) ($testada->comprimento ?? 0), $testada->lote_id]
                        );
                        $this->loteFacePrincipal = (float) ($testada->comprimento ?? 0);
                    } catch (\Throwable $e) {
                    }
                }

                Notification::make()->title('Testada atualizada!')->success()->send();

                $this->mostrarTestadasLoteAtivo = false;
                $this->toggleTestadasLote();
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria_testada')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-testada', id: $this->testadaAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),

                Action::make('excluir_testada')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        $t = LoteTestada::find($this->testadaAtivaId);
                        $wasPrincipal = $t?->tipo === 'principal';
                        $loteId = $t?->lote_id ?? $this->loteAtivoId;

                        $t?->delete();

                        if ($wasPrincipal) {
                            try {
                                DB::statement('UPDATE lotes SET main_facade_length = NULL WHERE id = ?', [$loteId]);
                                $this->loteFacePrincipal = 0.0;
                            } catch (\Throwable $e) {
                            }
                        }

                        Notification::make()->title('Testada excluída!')->success()->send();
                        $this->mostrarTestadasLoteAtivo = false;
                        $this->toggleTestadasLote();
                    }),
            ]);
    }
}
