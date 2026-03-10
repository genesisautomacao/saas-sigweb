<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Resources\OrdemServicoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrdemServico extends CreateRecord
{
    protected static string $resource = OrdemServicoResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (request()->filled('solicitacao_id')) {
            $solicitacao = \App\Models\SolicitacaoManutencao::find(request()->query('solicitacao_id'));
            
            if ($solicitacao) {
                $data['solicitacao_id'] = $solicitacao->id;
                $data['descricao_servico'] = "Ref. Chamado #{$solicitacao->sequential_id}: " . $solicitacao->observacao;
                $data['prioridade'] = $solicitacao->prioridade;
                
                // 🛑 ESTAS DUAS LINHAS ESTAVAM FALTANDO NO SEU ARQUIVO:
                // São elas que preenchem o Artefato (Poste/Árvore) na tela nova!
                $data['asset_type'] = $solicitacao->asset_type;
                $data['asset_id'] = $solicitacao->asset_id;
            }
        }
        return $data;
    }
}