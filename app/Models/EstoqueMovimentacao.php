<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EstoqueMovimentacao extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'estoque_movimentacoes';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'type', // entrada, saida, transferencia
        'user_id',
        'origem_id',
        'destino_id',
        'referencia_type',
        'referencia_id',
        'observacao',
    ];

    // ... seus fillables e relations ...

    // 🛑 A MÁGICA: Escuta quando a movimentação vai ser deletada e faz o estorno!
    protected static function booted(): void
    {
        static::deleting(function (EstoqueMovimentacao $movimentacao) {
            $tenantId = $movimentacao->tenant_id;
            
            // Pega os itens antes de apagar do banco
            foreach ($movimentacao->itens as $item) {
                
                // Se foi ENTRADA, o estorno é uma SAÍDA (Subtrai do Destino)
                if ($movimentacao->type === 'entrada' && $movimentacao->destino_id) {
                    $estoque = \App\Models\Estoque::where('tenant_id', $tenantId)
                        ->where('produto_id', $item->produto_id)
                        ->where('local_estoque_id', $movimentacao->destino_id)
                        ->first();
                    if ($estoque) {
                        $estoque->quantity -= $item->quantity;
                        $estoque->save();
                    }
                } 
                
                // Se foi SAÍDA, o estorno é uma ENTRADA (Devolve para a Origem)
                elseif ($movimentacao->type === 'saida' && $movimentacao->origem_id) {
                    $estoque = \App\Models\Estoque::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'produto_id' => $item->produto_id,
                        'local_estoque_id' => $movimentacao->origem_id,
                    ]);
                    $estoque->quantity += $item->quantity;
                    $estoque->save();
                } 
                
                // Se foi TRANSFERÊNCIA, devolve pra Origem e tira do Destino
                elseif ($movimentacao->type === 'transferencia' && $movimentacao->origem_id && $movimentacao->destino_id) {
                    // Devolve pra origem
                    $estoqueOrigem = \App\Models\Estoque::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'produto_id' => $item->produto_id,
                        'local_estoque_id' => $movimentacao->origem_id,
                    ]);
                    $estoqueOrigem->quantity += $item->quantity;
                    $estoqueOrigem->save();
                    
                    // Tira do destino
                    $estoqueDestino = \App\Models\Estoque::where('tenant_id', $tenantId)
                        ->where('produto_id', $item->produto_id)
                        ->where('local_estoque_id', $movimentacao->destino_id)
                        ->first();
                    if ($estoqueDestino) {
                        $estoqueDestino->quantity -= $item->quantity;
                        $estoqueDestino->save();
                    }
                }
            }
        });
    }

    // O relacionamento Polimórfico (Vai apontar para OS, Nota Fiscal, etc)
    public function referencia()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function origem()
    {
        return $this->belongsTo(LocalEstoque::class, 'origem_id');
    }

    public function destino()
    {
        return $this->belongsTo(LocalEstoque::class, 'destino_id');
    }

    public function itens()
    {
        return $this->hasMany(MovimentacaoItem::class, 'movimentacao_id');
    }
}