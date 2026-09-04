<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use App\Models\MobTrecho;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Trecho viário no mapa (Mobilidade Urbana — docs/piuma.txt §3, Onda 2/6).
 * Direção = ordem dos vértices = direção do MAPEAMENTO (define calçada
 * direita/esquerda) — por isso NÃO há "Inverter Sentido" nem sentido de
 * tráfego aqui: o fluxo mora na Via Urbana (HasMobViaActions).
 */
trait HasMobTrechoActions
{
    public ?int $mobTrechoAtivoId = null;

    /** Select com o valor atual incorporado (registro legado fora da lista não abre em branco). */
    protected function mobTrechoSelect(string $campo, string $label): Select
    {
        return Select::make($campo)
            ->label($label)
            ->options(function () use ($campo): array {
                $opcoes = array_combine(MobTrecho::VOCABULARIOS[$campo], MobTrecho::VOCABULARIOS[$campo]);
                $atual = $this->mobTrechoAtivoId
                    ? MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId)?->{$campo}
                    : null;
                if (filled($atual) && ! isset($opcoes[$atual])) {
                    $opcoes[$atual] = "{$atual} (valor atual)";
                }

                return $opcoes;
            })
            ->placeholder('Selecione...')
            ->nullable();
    }

    /** Props enviadas ao engine (estilo/setas/tematização e ficha do clique). */
    protected function mobTrechoPayload(MobTrecho $t): array
    {
        return [
            'id' => $t->id,
            'layer' => 'mob_trechos',
            'name' => 'Trecho #'.$t->sequential_id,
            'sequential_id' => $t->sequential_id,
            'via_id' => $t->via_id,
            'azimute' => $t->azimute,
            'extensao_geo' => $t->extensao_geo !== null ? (float) $t->extensao_geo : null,
            'tipologia_da_via' => $t->tipologia_da_via,
            'tipo_de_pavimentacao' => $t->tipo_de_pavimentacao,
            'estado_conservacao_pavimentacao' => $t->estado_conservacao_pavimentacao,
            'classe_faixa_rodagem' => $t->classe_faixa_rodagem,
            'dimensionamento_da_via' => $t->dimensionamento_da_via,
            'custom' => $t->dados_customizados,
        ];
    }

    protected function mobTrechoFormulario(): array
    {
        return [
            $this->mobTrechoSelect('tipologia_da_via', 'Tipologia da via'),
            $this->mobTrechoSelect('tipo_de_pavimentacao', 'Pavimentação'),
            $this->mobTrechoSelect('estado_conservacao_pavimentacao', 'Estado da pavimentação'),
            $this->mobTrechoSelect('classe_faixa_rodagem', 'Classe da faixa de rodagem'),
            $this->mobTrechoSelect('dimensionamento_da_via', 'Largura da via'),
            Select::make('logradouro_id')
                ->label('Logradouro (opcional)')
                ->options(fn () => Logradouro::where('tenant_id', $this->tenantId)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->nullable(),
            ...\App\Services\Coleta\CampoCustomizadoService::componentes('mob_trecho'),
        ];
    }

    public function criarMobTrechoAction(): Action
    {
        return Action::make('criarMobTrecho')
            ->modalHeading('Cadastrar Novo Trecho Viário')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                Placeholder::make('extensao_calculada')
                    ->label('Extensão calculada')
                    ->content(fn (): HtmlString => new HtmlString(
                        $this->mobExtensaoCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">'.number_format($this->mobExtensaoCalculada, 2, ',', '.').' m</strong> — a direção do desenho = direção do mapeamento (calçada direita/esquerda)'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a linha no mapa primeiro.</em>'
                    )),
                ...$this->mobTrechoFormulario(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;

                $registro = MobTrecho::create($data);
                $registro->atualizarMetadataGeo();
                $registro->refresh();

                Notification::make()->title('Trecho viário criado!')->success()->send();

                $this->dispatch('adicionar-mob_trechos-mapa', array_merge(
                    $this->mobTrechoPayload($registro),
                    ['geo' => $this->geometriaRascunho],
                ));
                $this->dispatch('limpar-rascunho-mapa');
                $this->mobExtensaoCalculada = null;
            });
    }

    public function opcoesMobTrechoAction(): Action
    {
        return Action::make('opcoesMobTrecho')
            ->hiddenLabel()
            ->modalHeading(function () {
                $t = MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId);

                return 'Trecho Viário #'.$t?->sequential_id;
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $t = MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId);

                return [
                    'tipologia_da_via' => $t?->tipologia_da_via,
                    'tipo_de_pavimentacao' => $t?->tipo_de_pavimentacao,
                    'estado_conservacao_pavimentacao' => $t?->estado_conservacao_pavimentacao,
                    'classe_faixa_rodagem' => $t?->classe_faixa_rodagem,
                    'dimensionamento_da_via' => $t?->dimensionamento_da_via,
                    'logradouro_id' => $t?->logradouro_id,
                    'dados_customizados' => $t?->dados_customizados ?? [],
                ];
            })
            ->form([
                Placeholder::make('resumo_geo')
                    ->label('Geometria')
                    ->content(function (): HtmlString {
                        $t = MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId);
                        $ext = $t?->extensao_geo !== null ? number_format((float) $t->extensao_geo, 2, ',', '.').' m' : '—';
                        $azi = $t?->azimute !== null ? number_format((float) $t->azimute, 1, ',', '.').'°' : '—';
                        $via = $t?->via_id ? $t->via()->withoutGlobalScopes()->first() : null;
                        $viaTxt = $via
                            ? ' · via urbana: <strong>'.e($via->rotulo()).'</strong> ('.(\App\Models\MobVia::SENTIDOS[$via->sentido] ?? 'sentido não classificado').')'
                            : ' · sem via urbana vinculada';

                        return new HtmlString("<strong style=\"color:#0369a1;\">{$ext}</strong> · azimute {$azi} <span style=\"color:#9ca3af;\">(direção do mapeamento — calçada direita/esquerda; a linha do trecho nunca é invertida){$viaTxt}</span>");
                    }),
                ...$this->mobTrechoFormulario(),
            ])
            ->action(function (array $data) {
                $t = MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId);
                if (! $t) {
                    return;
                }
                $t->update($data);
                $t->refresh();

                Notification::make()->title('Trecho atualizado!')->success()->send();
                $this->dispatch('atualizar-mob_trechos-mapa', $this->mobTrechoPayload($t));
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_mob_trecho')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-mob_trecho', id: $this->mobTrechoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_mob_trecho')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        MobTrecho::withoutGlobalScopes()->find($this->mobTrechoAtivoId)?->delete();
                        Notification::make()->title('Trecho excluído!')->success()->send();
                        $this->dispatch('remover-mob_trechos-mapa', ['id' => $this->mobTrechoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    #[On('abrirOpcoesMobTrecho')]
    public function abrirOpcoesMobTrecho($id): void
    {
        $this->mobTrechoAtivoId = (int) $id;
        $this->mountAction('opcoesMobTrecho');
    }

    #[On('salvarNovaGeometriaMobTrecho')]
    public function salvarNovaGeometriaMobTrecho($id, $geoJson): void
    {
        $t = MobTrecho::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->find($id);
        if (! $t) {
            return;
        }
        $t->update(['geo' => $geoJson]); // dispara o croqui Antes/Depois (LogsGeometryChanges)
        $t->atualizarMetadataGeo();
        $t->refresh();

        Notification::make()->title('Geometria do trecho atualizada!')->success()->send();
        $this->dispatch('atualizar-mob_trechos-mapa', array_merge(
            $this->mobTrechoPayload($t),
            ['geo' => $t->geo_json], // setas de direção acompanham a nova linha
        ));
    }
}
