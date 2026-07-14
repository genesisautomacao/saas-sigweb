<?php

namespace App\Http\Controllers;

use App\Models\ProcessoDigital;
use App\Services\Processo\RequerimentoPdfService;

/**
 * PD-1 — Download do requerimento em PDF de um Processo Digital.
 * Rota GET dedicada (padrão do processo-anexo.anotar): o cidadão gera/baixa o PDF,
 * assina fora do sistema e anexa o assinado pelo Portal do Cidadão.
 */
class ProcessoRequerimentoController extends Controller
{
    public function gerar(ProcessoDigital $processo)
    {
        $this->autorizar($processo);

        abort_unless(
            $processo->fluxo?->exige_requerimento && filled($processo->fluxo->template_requerimento),
            404,
            'Este processo não exige requerimento.'
        );

        return app(RequerimentoPdfService::class)->gerar($processo);
    }

    /**
     * Fora do painel Filament o escopo de tenant é inerte — autorização manual:
     * o usuário precisa pertencer ao tenant do processo e, se for cidadão, ser o requerente.
     */
    protected function autorizar(ProcessoDigital $processo): void
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        abort_unless($user && $user->tenants()->whereKey($processo->tenant_id)->exists(), 403);

        if ($user->isCidadao()) {
            abort_unless((int) $processo->requerente_id === (int) $user->id, 403);
        }
    }
}
