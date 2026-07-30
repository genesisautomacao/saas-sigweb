<?php

namespace App\Filament\Resources\ColetaAtribuicaoResource\Widgets;

use App\Models\ColetaAtribuicao;
use Filament\Widgets\Widget;

/**
 * R67-4 — mapa geral das regiões de coleta: mostra, em cores distintas, onde cada
 * cadastrador está atuando hoje (quadras das atribuições vigentes).
 *
 * ⚠️ Vive DENTRO do Resource de propósito: o painel auto-descobre widgets de
 * `app/Filament/Widgets` e os exibe na Dashboard — aqui ele é usado apenas no
 * `getHeaderWidgets()` da listagem de Atribuições de Região.
 */
class MapaRegioesColetaWidget extends Widget
{
    protected static string $view = 'filament.widgets.mapa-regioes-coleta';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * Widget NÃO-lazy de propósito: lazy faz o HTML chegar via Livewire e o navegador
     * não executa <script> injetado assim — o mapa nunca inicializava.
     */
    protected static bool $isLazy = false;

    /** Cadastradores com atribuição vigente + cor (determinística por posição). */
    public function getCadastradores(): array
    {
        $paleta = ['#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#475569', '#c2410c'];

        return ColetaAtribuicao::query()
            ->with('user:id,name')
            ->vigentes()
            ->get()
            ->groupBy('user_id')
            ->values()
            ->map(fn ($grupo, $i) => [
                'user_id' => (int) $grupo->first()->user_id,
                'nome' => $grupo->first()->user?->name ?? 'Cadastrador',
                'cor' => $paleta[$i % count($paleta)],
                'quadras' => count(array_unique($grupo->flatMap(fn ($a) => $a->quadra_ids ?? [])->all())),
            ])
            ->all();
    }
}
