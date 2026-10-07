<?php

namespace App\Filament\Pages\Traits;

use App\Models\Edificacao;
use App\Models\Lote;
use App\Services\Coleta\CampoCustomizadoService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * Edificações no mapa.
 *
 * R78-1 (Engenharia, 2026-10-07): a edificação é desacoplada da geometria do lote —
 * pode ultrapassar a divisa (beiral de sobrado), só o CENTRÓIDE precisa estar no lote
 * ao qual pertence. A ficha do lote lista as edificações num acordeon, cada uma com
 * olhinho próprio (vários pavimentos sobrepostos) e Editar / Excluir / Geometria /
 * Vincular a outro lote.
 */
trait HasEdificacaoActions
{
    /** Edificações do lote ativo exibidas no acordeon da ficha. */
    public array $loteEdificacoes = [];

    /** Ids das edificações ligadas (olhinho) na camada laranja do mapa. */
    public array $edificacoesVisiveis = [];

    /** Lote que contém o centróide do rascunho desenhado (criação). */
    public ?int $edificacaoRascunhoLoteId = null;

    /** Destino escolhido no fluxo "Vincular a outro lote". */
    public ?int $vinculoLoteDestinoId = null;

    // =========================================================================
    // ACORDEON DA FICHA + VISIBILIDADE NO MAPA
    // =========================================================================

    /** Recarrega a lista do acordeon (chamado ao abrir a ficha e após cada alteração). */
    public function carregarEdificacoesLote(): void
    {
        if (! $this->loteAtivoId) {
            $this->loteEdificacoes = [];

            return;
        }

        $this->loteEdificacoes = Edificacao::query()
            ->where('lote_id', $this->loteAtivoId)
            ->orderBy('sequential_id')
            ->get(['id', 'sequential_id', 'area_geo', 'dados_customizados', 'geo'])
            ->map(function (Edificacao $edif) {
                // Resumo = os 2 primeiros campos do município preenchidos (degrada
                // limpo: prefeitura sem campos mostra só a área).
                $resumo = collect(CampoCustomizadoService::colunasExport('edificacao', $edif->dados_customizados, $this->tenantId))
                    ->reject(fn ($v) => $v === '-')
                    ->take(2)
                    ->map(fn ($v, $label) => "{$label}: {$v}")
                    ->values()
                    ->all();

                return [
                    'id' => $edif->id,
                    'rotulo' => 'Edificação #'.($edif->sequential_id ?? $edif->id),
                    'area' => $edif->area_geo !== null ? number_format((float) $edif->area_geo, 2, ',', '.').' m²' : null,
                    'resumo' => implode(' · ', $resumo),
                    'tem_geo' => $edif->getRawOriginal('geo') !== null,
                ];
            })
            ->all();

        $this->loteAreaConstruida = (float) Edificacao::query()->where('lote_id', $this->loteAtivoId)->sum('area_geo');

        // Some da lista (excluída/desvinculada) ⇒ some do mapa também.
        $this->edificacoesVisiveis = array_values(array_intersect(
            $this->edificacoesVisiveis,
            array_column($this->loteEdificacoes, 'id'),
        ));
    }

    /** Olhinho do topo do acordeon: liga/desliga TODAS as edificações do lote. */
    public function toggleEdificacoesLote(): void
    {
        $ids = array_column($this->loteEdificacoes, 'id');
        $todasLigadas = $ids !== [] && array_diff($ids, $this->edificacoesVisiveis) === [];

        $this->edificacoesVisiveis = $todasLigadas ? [] : $ids;
        $this->desenharEdificacoesVisiveis();
    }

    /** Olhinho de cada linha: liga/desliga UMA edificação (vários pavimentos). */
    public function alternarEdificacaoVisivel(int $id): void
    {
        $this->edificacoesVisiveis = in_array($id, $this->edificacoesVisiveis)
            ? array_values(array_diff($this->edificacoesVisiveis, [$id]))
            : [...$this->edificacoesVisiveis, $id];

        $this->desenharEdificacoesVisiveis();
    }

    /** Envia ao mapa só as edificações ligadas (a camada laranja é redesenhada inteira). */
    public function desenharEdificacoesVisiveis(): void
    {
        $this->mostrarEdificacoesLoteAtivo = $this->edificacoesVisiveis !== [];

        if (! $this->mostrarEdificacoesLoteAtivo) {
            $this->dispatch('esconder-edificacoes-lote');

            return;
        }

        $edificacoes = Edificacao::query()
            ->whereIn('id', $this->edificacoesVisiveis)
            ->select('id', 'geo')
            ->get()
            ->map(fn ($edif) => ['id' => $edif->id, 'geo' => $edif->geo_json])
            ->toArray();

        $this->dispatch('mostrar-edificacoes-lote', edificacoes: $edificacoes);
    }

    // =========================================================================
    // REGRA DO CENTRÓIDE (R78-1)
    // =========================================================================

    /** Lote da prefeitura que contém o centróide da geometria (GeoJSON array). */
    protected function loteDoCentroideEdificacao(array $geoJson): ?Lote
    {
        return Lote::query()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw(
                'ST_Contains(geo::geometry, ST_Centroid(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))))',
                [json_encode($geoJson)],
            )
            ->first();
    }

    protected function rotuloLote(?Lote $lote): string
    {
        if (! $lote) {
            return '—';
        }

        $numero = $lote->numero_lote ?: $lote->sequential_id;
        $quadra = $lote->quadra?->name;

        return 'Lote '.$numero.($quadra ? " (Quadra {$quadra})" : '');
    }

    /** O centróide da edificação (geometria gravada) está dentro do lote informado? */
    private function centroideDentroDoLote(Edificacao $edif, ?int $loteId): bool
    {
        if (! $loteId) {
            return false;
        }

        return (bool) DB::selectOne('
            SELECT ST_Contains(l.geo::geometry, ST_Centroid(e.geo::geometry)) AS dentro
            FROM edificacoes e, lotes l
            WHERE e.id = ? AND l.id = ?
        ', [$edif->id, $loteId])?->dentro;
    }

    /** Lote (≠ do vínculo atual) que contém o centróide da edificação — a sugestão. */
    private function loteSugeridoParaEdificacao(Edificacao $edif): ?Lote
    {
        $id = DB::selectOne('
            SELECT l.id FROM lotes l, edificacoes e
            WHERE e.id = ? AND l.tenant_id = ? AND l.deleted_at IS NULL
              AND ST_Contains(l.geo::geometry, ST_Centroid(e.geo::geometry))
            LIMIT 1
        ', [$edif->id, $this->tenantId])?->id;

        return ($id && $id != $edif->lote_id) ? Lote::query()->find($id) : null;
    }

    // =========================================================================
    // CRIAR / EDITAR / EXCLUIR
    // =========================================================================

    /**
     * Ação: Criar Nova Edificação
     */
    public function criarEdificacaoAction(): Action
    {
        return Action::make('criarEdificacao')
            ->modalHeading('Cadastrar Nova Edificação')
            ->modalSubmitActionLabel('Salvar Edificação')
            ->modalWidth('md')
            ->form([
                // Refatoração PoC Tangará: TODOS os atributos descritivos da edificação
                // são campos customizados (o kit inicial cria tipo_edificacao, pavimento,
                // tp_construcao e estado_conservacao — o município ajusta como quiser).
                \Filament\Forms\Components\Section::make('Dados da Edificação')
                    ->schema(fn () => CampoCustomizadoService::componentes('edificacao'))
                    ->columns(2),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['lote_id'] = $this->edificacaoRascunhoLoteId ?? $this->loteAtivoId;
                $data['code'] = (string) Str::uuid();

                $edif = Edificacao::create($data);

                DB::statement('UPDATE edificacoes SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$edif->id]);

                Notification::make()->title('Edificação Criada!')->success()->send();

                $this->geometriaRascunho = null;
                $this->edificacaoRascunhoLoteId = null;
                $this->dispatch('limpar-rascunho-mapa');

                // A recém-criada já nasce ligada no mapa.
                $this->carregarEdificacoesLote();
                $this->edificacoesVisiveis = array_values(array_unique([...$this->edificacoesVisiveis, $edif->id]));
                $this->desenharEdificacoesVisiveis();
            });
    }

    /**
     * Ação: Opções da Edificação (Modal de Edição/Exclusão) — aberta pelo "Editar"
     * do acordeon ou pelo clique na edificação (camada laranja ou camada geral).
     */
    public function opcoesEdificacaoAction(): Action
    {
        return Action::make('opcoesEdificacao')
            ->hiddenLabel()
            ->modalHeading(function () {
                $edif = Edificacao::query()->with('lote')->find($this->edificacaoAtivaId);

                return 'Edificação #'.($edif?->sequential_id ?? $this->edificacaoAtivaId).' — '.$this->rotuloLote($edif?->lote);
            })
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $edif = Edificacao::find($this->edificacaoAtivaId);

                return [
                    'area_geo' => $edif?->area_geo,
                    'dados_customizados' => $edif?->dados_customizados ?? [], // R67-1
                ];
            })
            ->form([
                // Refatoração PoC Tangará: atributos descritivos = campos customizados.
                \Filament\Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->readOnly(),

                \Filament\Forms\Components\Section::make('Dados da Edificação')
                    ->schema(fn () => CampoCustomizadoService::componentes('edificacao'))
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $edif = Edificacao::find($this->edificacaoAtivaId);
                if ($edif) {
                    // area_geo é calculada pela geometria — nunca vem do form.
                    unset($data['area_geo']);
                    $edif->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->carregarEdificacoesLote();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->editarGeometriaEdificacao($this->edificacaoAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),

                Action::make('vincular_lote')
                    ->label('Vincular a outro lote')
                    ->color('gray')
                    ->icon('heroicon-o-link')
                    ->cancelParentActions()
                    // Abre o modal do vínculo na PRÓXIMA requisição (o atual fecha antes).
                    ->action(fn () => $this->dispatch('abrirVinculoEdificacao', id: $this->edificacaoAtivaId)),

                Action::make('excluir_edif')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->cancelParentActions()
                    ->action(fn () => $this->excluirEdificacao($this->edificacaoAtivaId)),
            ]);
    }

    /** Ação de exclusão chamada pelo botão da linha do acordeon (com confirmação). */
    public function excluirEdificacaoAction(): Action
    {
        return Action::make('excluirEdificacao')
            ->requiresConfirmation()
            ->modalHeading('Excluir edificação?')
            ->modalDescription('A edificação vai para a lixeira (recuperável).')
            ->color('danger')
            ->action(fn (array $arguments) => $this->excluirEdificacao((int) ($arguments['id'] ?? 0)));
    }

    private function excluirEdificacao(?int $id): void
    {
        $edif = $id ? Edificacao::find($id) : null;
        if (! $edif) {
            return;
        }

        $edif->delete();
        Notification::make()->title('Edificação Excluída!')->success()->send();

        $this->edificacoesVisiveis = array_values(array_diff($this->edificacoesVisiveis, [$id]));
        $this->dispatch('remover-edificacao-mapa', id: $id);
        $this->carregarEdificacoesLote();
        $this->desenharEdificacoesVisiveis();
    }

    /**
     * Geometria (acordeon ou modal): garante a edificação na camada laranja e entra
     * no modo de edição. Vale também para a edificação clicada na camada geral, cujo
     * lote pode não ser o da ficha aberta.
     */
    public function editarGeometriaEdificacao(?int $id): void
    {
        if (! $id || ! Edificacao::query()->whereKey($id)->exists()) {
            return;
        }

        $this->edificacaoAtivaId = $id;

        if (! in_array($id, $this->edificacoesVisiveis)) {
            $this->edificacoesVisiveis[] = $id;
        }
        $this->desenharEdificacoesVisiveis();

        $this->showFicha = false;
        $this->dispatch('iniciar-edicao-geometria-edificacao', id: $id);
    }

    /** Salva a geometria editada da edificação — regra do centróide (R78-1). */
    public function salvarGeometriaEdificacao(int $id, array $geoJson): void
    {
        $edif = Edificacao::query()->find($id);
        if (! $edif) {
            return;
        }

        $loteCentro = $this->loteDoCentroideEdificacao($geoJson);

        if (! $loteCentro || $loteCentro->id != $edif->lote_id) {
            Notification::make()->title('Erro Topológico')
                ->body($loteCentro
                    ? 'O centro da edificação caiu no '.$this->rotuloLote($loteCentro).'. Ela pode ultrapassar a divisa, mas o centro precisa ficar no lote dela — ou use "Vincular a outro lote".'
                    : 'O centro da edificação precisa ficar dentro de um lote.')
                ->danger()
                ->persistent()
                ->send();
            $this->dispatch('desfazer-edicao-geometria');

            return;
        }

        $edif->update(['geo' => $geoJson]);
        DB::statement('UPDATE edificacoes SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$edif->id]);

        Notification::make()->title('Geometria Atualizada!')->success()->send();

        // A camada geral "Edificações" (se ligada) recebe a geometria nova.
        $this->dispatch('atualizar-geometria-edificacao', id: $edif->id, geo: $edif->fresh()->geo_json);
        $this->carregarEdificacoesLote();
        $this->desenharEdificacoesVisiveis();
    }

    // =========================================================================
    // VINCULAR A OUTRO LOTE (mapa + sugestão)
    // =========================================================================

    /** Abre o modal do vínculo já com a sugestão (lote que contém o centróide). */
    #[On('abrirVinculoEdificacao')]
    public function iniciarVinculoEdificacao(?int $id): void
    {
        $edif = $id ? Edificacao::query()->find($id) : null;
        if (! $edif) {
            return;
        }

        $this->edificacaoAtivaId = $edif->id;
        $this->vinculoLoteDestinoId = $this->loteSugeridoParaEdificacao($edif)?->id;
        $this->mountAction('vincularEdificacao');
    }

    /** Clique no mapa durante o modo "escolher lote" (engine: window.vinculoEdificacaoId). */
    #[On('escolherLoteVinculoEdificacao')]
    public function escolherLoteVinculoEdificacao($id, $lon, $lat): void
    {
        $this->edificacaoAtivaId = (int) $id;

        $loteId = Lote::query()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw('ST_Contains(geo::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [(float) $lon, (float) $lat])
            ->value('id');

        if (! $loteId) {
            Notification::make()->title('Nenhum lote nesse ponto')
                ->body('Clique dentro do lote de destino.')
                ->warning()->send();
            $this->dispatch('iniciar-vinculo-edificacao', id: $this->edificacaoAtivaId);

            return;
        }

        $this->vinculoLoteDestinoId = $loteId;
        $this->mountAction('vincularEdificacao');
    }

    public function vincularEdificacaoAction(): Action
    {
        return Action::make('vincularEdificacao')
            ->modalHeading('Vincular edificação a outro lote')
            ->modalIcon('heroicon-o-link')
            ->modalWidth('md')
            ->form(function () {
                $edif = Edificacao::query()->with('lote')->find($this->edificacaoAtivaId);
                $destino = $this->vinculoLoteDestinoId ? Lote::query()->find($this->vinculoLoteDestinoId) : null;
                $sugerido = $edif ? $this->loteSugeridoParaEdificacao($edif) : null;

                $avisos = [];
                if ($destino && $sugerido && $sugerido->id == $destino->id) {
                    $avisos[] = '💡 Sugestão do sistema: o centro da edificação está neste lote.';
                }
                if ($destino && $edif && ! $this->centroideDentroDoLote($edif, $destino->id)) {
                    $avisos[] = '⚠️ O centro da edificação não fica dentro deste lote.';
                }
                if ($destino && $edif && $destino->id == $edif->lote_id) {
                    $avisos[] = 'A edificação já pertence a este lote.';
                }

                return [
                    Placeholder::make('edificacao')
                        ->label('Edificação')
                        ->content('Edificação #'.($edif?->sequential_id ?? $this->edificacaoAtivaId)),
                    Placeholder::make('lote_atual')
                        ->label('Lote atual')
                        ->content($this->rotuloLote($edif?->lote)),
                    Placeholder::make('lote_destino')
                        ->label('Novo lote')
                        ->content(new HtmlString($destino
                            ? '<strong>'.e($this->rotuloLote($destino)).'</strong>'
                            : '<span style="color:#6b7280">Nenhum escolhido — use "Escolher no mapa".</span>')),
                    Placeholder::make('avisos')
                        ->hiddenLabel()
                        ->visible($avisos !== [])
                        ->content(new HtmlString(implode('<br>', array_map('e', $avisos)))),
                ];
            })
            ->modalSubmitActionLabel('Confirmar vínculo')
            ->modalSubmitAction(fn ($action) => $action->disabled(
                ! $this->vinculoLoteDestinoId
                || Edificacao::query()->where('id', $this->edificacaoAtivaId)->value('lote_id') == $this->vinculoLoteDestinoId
            ))
            ->extraModalFooterActions([
                Action::make('escolher_no_mapa')
                    ->label('Escolher no mapa')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('gray')
                    ->cancelParentActions()
                    ->action(function () {
                        $this->showFicha = false;
                        $this->dispatch('iniciar-vinculo-edificacao', id: $this->edificacaoAtivaId);
                    }),
            ])
            ->action(function () {
                $edif = Edificacao::query()->find($this->edificacaoAtivaId);
                $destino = $this->vinculoLoteDestinoId ? Lote::query()->find($this->vinculoLoteDestinoId) : null;
                if (! $edif || ! $destino || $destino->tenant_id != $this->tenantId) {
                    return;
                }

                $edif->update(['lote_id' => $destino->id]); // Eloquent ⇒ entra na Auditoria

                Notification::make()->title('Edificação vinculada ao '.$this->rotuloLote($destino))->success()->send();

                $this->vinculoLoteDestinoId = null;
                $this->carregarEdificacoesLote();
                $this->desenharEdificacoesVisiveis();
            });
    }
}
