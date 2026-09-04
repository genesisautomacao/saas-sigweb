<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use App\Models\MobVia;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Via urbana no mapa (Mobilidade Urbana — docs/piuma.txt, Onda 6).
 * É AQUI que mora o fluxo: sentido (mão única/dupla), "Inverter Sentido"
 * (ST_Reverse — a direção é a ordem dos vértices) e a caneta de classificação
 * em massa. O trecho de levantamento não tem nada disso (sua direção define
 * as calçadas e nunca vira).
 */
trait HasMobViaActions
{
    public ?int $mobViaAtivoId = null;

    /** Props enviadas ao engine (estilo/setas e ficha do clique). */
    protected function mobViaPayload(MobVia $v): array
    {
        return [
            'id' => $v->id,
            'layer' => 'mob_vias',
            'name' => $v->rotulo(),
            'sequential_id' => $v->sequential_id,
            'nome' => $v->nome,
            'sentido' => $v->sentido,
            'azimute' => $v->azimute,
            'extensao_geo' => $v->extensao_geo !== null ? (float) $v->extensao_geo : null,
        ];
    }

    protected function mobViaFormulario(): array
    {
        return [
            TextInput::make('nome')
                ->label('Nome da via (opcional)')
                ->maxLength(255)
                ->nullable(),
            Select::make('sentido')
                ->label('Sentido da via')
                ->options(MobVia::SENTIDOS)
                ->placeholder('Não classificado')
                ->helperText('Mão única: o fluxo segue a DIREÇÃO DO DESENHO da linha (setas no mapa). Use "Inverter Sentido" se a via andar ao contrário.')
                ->nullable(),
            Select::make('logradouro_id')
                ->label('Logradouro (opcional)')
                ->options(fn () => Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->nullable(),
        ];
    }

    public function criarMobViaAction(): Action
    {
        return Action::make('criarMobVia')
            ->modalHeading('Cadastrar Nova Via Urbana')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->mobExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#1d4ed8;">'.number_format($this->mobExtensaoCalculada, 2, ',', '.').' m</strong> — a direção do desenho define o sentido'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                ...$this->mobViaFormulario(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobVia::create($data);
                $registro->atualizarMetadataGeo();
                $registro->refresh();

                Notification::make()->title('Via urbana criada!')->success()->send();

                $this->dispatch('adicionar-mob_vias-mapa', array_merge(
                    $this->mobViaPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
                $this->mobExtensaoCalculada = null;
            });
    }

    public function opcoesMobViaAction(): Action
    {
        return Action::make('opcoesMobVia')
            ->hiddenLabel()
            ->modalHeading(function () {
                $v = MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId);

                return $v ? 'Via Urbana: '.$v->rotulo() : 'Via Urbana';
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $v = MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId);

                return [
                    'nome' => $v?->nome,
                    'sentido' => $v?->sentido,
                    'logradouro_id' => $v?->logradouro_id,
                ];
            })
            ->form([
                Placeholder::make('resumo_geo')
                    ->label('Geometria')
                    ->content(function (): HtmlString {
                        $v = MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId);
                        $ext = $v?->extensao_geo !== null ? number_format((float) $v->extensao_geo, 2, ',', '.').' m' : '—';
                        $azi = $v?->azimute !== null ? number_format((float) $v->azimute, 1, ',', '.').'°' : '—';
                        $trechos = $v ? $v->trechos()->withoutGlobalScopes()->whereNull('deleted_at')->pluck('sequential_id')->all() : [];
                        $vinculo = $trechos
                            ? ' · trecho(s) do levantamento: #'.implode(', #', $trechos)
                            : ' · sem trecho de levantamento vinculado';

                        return new HtmlString("<strong style=\"color:#1d4ed8;\">{$ext}</strong> · azimute {$azi} <span style=\"color:#9ca3af;\">(calculado da geometria){$vinculo}</span>");
                    }),
                ...$this->mobViaFormulario(),
            ])
            ->action(function (array $data) {
                $v = MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId);
                if (! $v) {
                    return;
                }
                $v->update($data);
                $v->refresh();

                Notification::make()->title('Via atualizada!')->success()->send();
                $this->dispatch('atualizar-mob_vias-mapa', $this->mobViaPayload($v));
            })
            ->extraModalFooterActions([
                Action::make('inverter_sentido_mob_via')
                    ->label('Inverter Sentido')
                    ->color('info')
                    ->icon('heroicon-o-arrows-right-left')
                    ->action(function () {
                        $v = MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId);
                        if (! $v) {
                            return;
                        }
                        $v->inverterDirecao();
                        $v->refresh();

                        Notification::make()->title('Sentido invertido!')
                            ->body('A direção da via foi revertida — as setas seguem o novo fluxo.')
                            ->success()->send();

                        // geo junto: o engine troca a geometria e redesenha as setas
                        $this->dispatch('atualizar-mob_vias-mapa', array_merge(
                            $this->mobViaPayload($v),
                            ['geo' => $v->geo_json],
                        ));
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('editar_geo_mob_via')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_via', id: $this->mobViaAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_via')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalDescription('Os trechos de levantamento vinculados continuam existindo (só perdem o vínculo).')
                    ->action(function () {
                        MobVia::withoutGlobalScopes()->find($this->mobViaAtivoId)?->delete();
                        Notification::make()->title('Via excluída!')->success()->send();
                        $this->dispatch('remover-mob_vias-mapa', ['id' => $this->mobViaAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobVia')]
    public function abrirOpcoesMobVia($id): void
    {
        $this->mobViaAtivoId = (int) $id;
        $this->mountAction('opcoesMobVia');
    }

    /**
     * Caneta de classificação em massa (piuma.txt Onda 4 → vias na Onda 6):
     * clique na via aplica a ação armada. Sem notificação por clique — o
     * feedback é visual (cor/setas) + contador "sem sentido" no painel.
     */
    #[On('aplicarSentidoVia')]
    public function aplicarSentidoVia($id, $acao): void
    {
        $v = MobVia::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $v) {
            return;
        }

        $extra = [];
        if ($acao === 'inverter') {
            $v->update(['sentido' => 'mao_unica']);
            $v->inverterDirecao();
            $v->refresh();
            $extra = ['geo' => $v->geo_json]; // engine troca a geometria (setas viram)
        } elseif (in_array($acao, ['mao_unica', 'mao_dupla'], true)) {
            $v->update(['sentido' => $acao]);
            $v->refresh();
        } else {
            return;
        }

        $this->dispatch('atualizar-mob_vias-mapa', array_merge($this->mobViaPayload($v), $extra));

        $restantes = MobVia::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->whereNull('sentido')
            ->count();
        $this->dispatch('sigweb-mob-sentido-restantes', restantes: $restantes);
    }

    #[On('salvarNovaGeometriaMobVia')]
    public function salvarNovaGeometriaMobVia($id, $geoJson): void
    {
        $v = MobVia::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $v) {
            return;
        }
        $v->update(['geo' => $geoJson]); // dispara o croqui Antes/Depois (LogsGeometryChanges)
        $v->atualizarMetadataGeo();
        $v->refresh();

        Notification::make()->title('Geometria da via atualizada!')->success()->send();
        $this->dispatch('atualizar-mob_vias-mapa', array_merge(
            $this->mobViaPayload($v),
            ['geo' => $v->geo_json],
        ));
    }
}
