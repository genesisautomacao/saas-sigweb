<?php

namespace App\Filament\Pages\Traits;

use App\Models\MobFluxo;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Linha de desejo O/D no mapa (docs/piuma.txt §2.7, Onda 2 → ajuste 2026-09-04).
 *
 * A camada é SÓ LEITURA no mapa (decisão da equipe da mobilidade): o clique
 * abre uma ficha com origem → destino (zonas derivadas da geometria) e os
 * percentuais — sem editar, mover ou excluir, e sem criação pelo "Criar
 * Artefatos". A manutenção dos dados fica na tela "Fluxos O/D" do menu e na
 * importação do JSON. Só percentuais: a quantidade absoluta não aparece aqui.
 */
trait HasMobFluxoActions
{
    public ?int $mobFluxoAtivoId = null;

    public function opcoesMobFluxoAction(): Action
    {
        return Action::make('opcoesMobFluxo')
            ->hiddenLabel()
            ->modalHeading(function () {
                $f = MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId);

                return $f ? 'Fluxo O/D #'.$f->sequential_id.': '.$f->origemRotulo().' → '.$f->destinoRotulo() : 'Fluxo O/D';
            })
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (): HtmlString {
                $f = MobFluxo::withoutGlobalScopes()->find($this->mobFluxoAtivoId);
                if (! $f) {
                    return new HtmlString('Fluxo não encontrado.');
                }

                $dist = MobFluxo::distribuicao($this->tenantId);
                $total = $dist['total'];
                $destino = $dist['destinos'][MobFluxo::slugZona($f->destino_zona)] ?? null;
                $cor = $destino['cor'] ?? MobFluxo::COR_SEM_ZONA;
                $pct = $total > 0 ? round($f->valores * 100 / $total, 1) : 0.0;
                $pctDestino = ($destino && $destino['valores'] > 0) ? round($f->valores * 100 / $destino['valores'], 1) : 0.0;
                $fmt = fn (float $v) => number_format($v, 1, ',', '.').'%';
                $origem = e($f->origemRotulo());
                $destinoRotulo = e($f->destinoRotulo());
                $intrazonal = $f->origem_zona && $f->origem_zona === $f->destino_zona;
                $grupo = MobFluxo::REGIOES[$f->origem_regiao] ?? ucfirst((string) $f->origem_regiao);

                $linha = fn (string $k, string $v) => '<div style="display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid #f3f4f6; font-size:13px;">'
                    .'<span style="color:#6b7280;">'.$k.'</span><strong style="text-align:right;">'.$v.'</strong></div>';

                return new HtmlString(
                    '<div style="display:flex; flex-direction:column; gap:2px;">'
                    .'<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:14px;">'
                    .'<span style="font-weight:700;">'.$origem.'</span>'
                    .'<span style="color:#9ca3af;">&#8594;</span>'
                    .'<span style="width:14px; height:14px; border-radius:3px; background:'.$cor.'; display:inline-block;"></span>'
                    .'<span style="font-weight:700;">'.$destinoRotulo.'</span>'
                    .($intrazonal ? '<span style="font-size:11px; color:#b45309; font-weight:600;">intrazonal (sem linha)</span>' : '')
                    .'</div>'
                    .$linha('Origem (zona O/D)', $origem.($grupo && mb_strtolower($grupo) !== mb_strtolower($f->origem_zona ?? '') ? ' <span style="color:#9ca3af; font-weight:400;">(grupo "'.e($grupo).'" do levantamento)</span>' : ''))
                    .$linha('Destino (zona O/D)', $destinoRotulo)
                    .$linha('% do total de deslocamentos', $fmt($pct))
                    .$linha('% dentro do destino '.$destinoRotulo, $fmt($pctDestino))
                    .$linha('O destino '.$destinoRotulo.' concentra', $fmt($destino['percentual'] ?? 0.0).' <span style="color:#9ca3af; font-weight:400;">do total</span>')
                    .'<p style="font-size:11px; color:#9ca3af; margin:10px 0 0;">Origem e destino são derivados da geometria da linha e das zonas O/D. Camada só de leitura no mapa; para ajustar dados, use a tela "Fluxos O/D" do menu ou a importação do JSON.</p>'
                    .'</div>'
                );
            });
    }

    #[On('abrirOpcoesMobFluxo')]
    public function abrirOpcoesMobFluxo($id): void
    {
        $this->mobFluxoAtivoId = (int) $id;
        $this->mountAction('opcoesMobFluxo');
    }
}
