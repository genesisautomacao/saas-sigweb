<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Resources\OrdemServicoResource;
use App\Models\SolicitacaoManutencao;
use Filament\Resources\Pages\CreateRecord;

class CreateOrdemServico extends CreateRecord
{
    protected static string $resource = OrdemServicoResource::class;

    public function mount(): void
    {
        parent::mount();

        $solicitacaoId = request()->query('solicitacao_id');
        if (!$solicitacaoId) return;

        $sol = SolicitacaoManutencao::find($solicitacaoId);
        if (!$sol) return;

        $this->form->fill([
            'solicitacao_id'    => $sol->id,
            'asset_type'        => $sol->asset_type,
            'asset_id'          => $sol->asset_id,
            'prioridade'        => $sol->prioridade,
            'descricao_servico' => "Ref. Chamado #{$sol->sequential_id}: {$sol->observacao}",
        ]);
    }
}
