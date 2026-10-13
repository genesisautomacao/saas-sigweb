<?php

namespace App\Filament\Pages\Concerns;

use App\Support\MapaPermissoes;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Release 83 — guards de permissão do mapa interativo (fonte: config/mapa.php via
 * App\Support\MapaPermissoes). O SERVIDOR manda; blade/engine só espelham.
 *
 * Como usar:
 *  - Ação Filament:  ->authorize(fn () => $this->podeNoMapa('lotes', 'criar'))
 *    (esconde o botão E recusa mount/call — isDisabled() inclui isHidden()) e, dentro
 *    do ->action(), $this->exigirNoMapa('lotes', 'criar') (lança Halt: nada grava).
 *  - Modal "opções" (ficha + edição): authorize('mapa') + disabledForm(! editar) +
 *    modalSubmitAction(false quando não edita) = "apenas visualizar".
 *  - Listener #[On] / método público: if (! $this->autorizarNoMapa('lotes', 'editar')) return;
 *  - Ferramenta: podeFerramenta('numeracao') / exigirFerramenta() / autorizarFerramenta().
 * Entidades FILHAS do lote (edificacao, testada, unidade) resolvem para EDITAR lotes.
 */
trait ChecaPermissoesMapa
{
    private ?array $permissoesMapaCache = null;

    public function podeNoMapa(string $entidade, string $acao): bool
    {
        return MapaPermissoes::pode($entidade, $acao);
    }

    public function podeFerramenta(string $id): bool
    {
        return MapaPermissoes::podeFerramenta($id);
    }

    /** Listener/método: notifica e devolve false quando não pode. */
    protected function autorizarNoMapa(string $entidade, string $acao): bool
    {
        if ($this->podeNoMapa($entidade, $acao)) {
            return true;
        }
        $this->negarNoMapa();

        return false;
    }

    protected function autorizarFerramenta(string $id): bool
    {
        if ($this->podeFerramenta($id)) {
            return true;
        }
        $this->negarNoMapa('Seu papel não tem esta ferramenta do mapa liberada. Peça ao gestor da prefeitura (Configurações → Papéis de Acesso → Mapa — Ferramentas).');

        return false;
    }

    /** Dentro de ->action(): notifica e interrompe a ação (Halt) — nada é gravado. */
    protected function exigirNoMapa(string $entidade, string $acao): void
    {
        if (! $this->podeNoMapa($entidade, $acao)) {
            $this->negarNoMapa();
            throw new Halt;
        }
    }

    protected function exigirFerramenta(string $id): void
    {
        if (! $this->podeFerramenta($id)) {
            $this->negarNoMapa('Seu papel não tem esta ferramenta do mapa liberada. Peça ao gestor da prefeitura (Configurações → Papéis de Acesso → Mapa — Ferramentas).');
            throw new Halt;
        }
    }

    protected function negarNoMapa(?string $corpo = null): void
    {
        Notification::make()
            ->title('Sem permissão')
            ->body($corpo ?? 'Seu papel não permite esta ação no mapa. Peça ao gestor da prefeitura para liberar em Configurações → Papéis de Acesso.')
            ->danger()
            ->send();
    }

    /** Resumo (entidades, camadas, artefatos, ferramentas) para o blade e o engine — uma vez por requisição. */
    public function permissoesMapa(): array
    {
        return $this->permissoesMapaCache ??= MapaPermissoes::resumo();
    }
}
