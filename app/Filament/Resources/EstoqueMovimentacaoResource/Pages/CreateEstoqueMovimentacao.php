<?php

namespace App\Filament\Resources\EstoqueMovimentacaoResource\Pages;

use App\Filament\Resources\EstoqueMovimentacaoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEstoqueMovimentacao extends CreateRecord
{
    protected static string $resource = EstoqueMovimentacaoResource::class;

    // 1. Injeta o ID do usuário logado antes de salvar o cabeçalho
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = \Filament\Facades\Filament::auth()->id();
        return $data;
    }

    // 2. Após salvar o Cabeçalho e os Itens (Repeater), atualiza o Saldo!
    protected function afterCreate(): void
    {
        $movimentacao = $this->record;
        $tenantId = \Filament\Facades\Filament::getTenant()->id;
        
        // Pega todos os itens salvos via Repeater
        $itens = $movimentacao->itens;

        foreach ($itens as $item) {
            
            // Lógica de Entrada
            if ($movimentacao->type === 'entrada' && $movimentacao->destino_id) {
                $this->addEstoque($tenantId, $item->produto_id, $movimentacao->destino_id, $item->quantity);
            } 
            
            // Lógica de Saída
            elseif ($movimentacao->type === 'saida' && $movimentacao->origem_id) {
                $this->subEstoque($tenantId, $item->produto_id, $movimentacao->origem_id, $item->quantity);
            } 
            
            // Lógica de Transferência
            elseif ($movimentacao->type === 'transferencia' && $movimentacao->origem_id && $movimentacao->destino_id) {
                $this->subEstoque($tenantId, $item->produto_id, $movimentacao->origem_id, $item->quantity);
                $this->addEstoque($tenantId, $item->produto_id, $movimentacao->destino_id, $item->quantity);
            }
        }
    }

    // Helper: Soma no Saldo
    private function addEstoque($tenantId, $produtoId, $localId, $qtd)
    {
        $estoque = \App\Models\Estoque::firstOrCreate([
            'tenant_id' => $tenantId,
            'produto_id' => $produtoId,
            'local_estoque_id' => $localId,
        ]);
        $estoque->quantity += $qtd;
        $estoque->save();
    }

    // Helper: Subtrai do Saldo
    private function subEstoque($tenantId, $produtoId, $localId, $qtd)
    {
        $estoque = \App\Models\Estoque::firstOrCreate([
            'tenant_id' => $tenantId,
            'produto_id' => $produtoId,
            'local_estoque_id' => $localId,
        ]);
        $estoque->quantity -= $qtd;
        $estoque->save();
    }
}