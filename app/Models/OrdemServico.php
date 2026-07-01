<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdemServico extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'solicitacao_id',
        'asset_type',
        'asset_id',
        'status',
        'prioridade',
        'data_prevista',
        'data_inicio',
        'data_fim',
        'descricao_servico',
        'laudo_tecnico',
        'foto_antes',
        'foto_depois',
    ];

    protected $casts = [
        'data_prevista' => 'date',
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(SolicitacaoManutencao::class, 'solicitacao_id');
    }

    public function asset()
    {
        return $this->morphTo();
    }

    // 🛑 A SUA REFATORAÇÃO: Link direto com Pessoas (A Equipe de Rua)
    public function equipe()
    {
        return $this->belongsToMany(Pessoa::class, 'os_equipe', 'ordem_servico_id', 'pessoa_id');
    }

    public function materiais()
    {
        return $this->hasMany(OsMaterial::class, 'ordem_servico_id');
    }

    // 🛑 A MÁGICA DA BAIXA AUTOMÁTICA (CORRIGIDA)
    protected static function booted(): void
    {

        // 🟢 1. FORÇA A ATUALIZAÇÃO DO STATUS DA SOLICITAÇÃO DIRETO NO BANCO
        static::created(function (OrdemServico $os) {
            if ($os->solicitacao_id) {
                \App\Models\SolicitacaoManutencao::where('id', $os->solicitacao_id)
                    ->update(['status' => 'aprovada_os']);
            }
        });

        // 2. Quando ATUALIZAR para concluída, faz a baixa do estoque
        static::updated(function (OrdemServico $os) {

            // Só executa se o status foi alterado AGORA para 'concluida'
            if ($os->isDirty('status') && $os->status === 'concluida') {

                // 🟢 Serviço executado: encerra a solicitação para o poste/árvore
                // voltar à cor original no mapa (a camada só colore chamados ativos).
                if ($os->solicitacao_id) {
                    \App\Models\SolicitacaoManutencao::where('id', $os->solicitacao_id)
                        ->update(['status' => 'concluida']);
                }

                if ($os->materiais()->count() > 0) {

                    // 🟢 CORREÇÃO: Agrupa os materiais pelo Local de Estoque (Origem)
                    $materiaisPorLocal = $os->materiais->groupBy('local_estoque_id');

                    foreach ($materiaisPorLocal as $localId => $materiais) {

                        // 1. Cria o Cabeçalho com a Origem Correta para este grupo de materiais!
                        $movimentacao = \App\Models\EstoqueMovimentacao::create([
                            'tenant_id' => $os->tenant_id,
                            'type' => 'saida',
                            'user_id' => \Filament\Facades\Filament::auth()->id() ?? 1,
                            'origem_id' => $localId, // 🟢 AGORA SETA A ORIGEM PERFEITAMENTE
                            'referencia_type' => OrdemServico::class,
                            'referencia_id' => $os->id,
                            'observacao' => "Consumo automático ref. OS #{$os->sequential_id}",
                        ]);

                        // 2. Transfere os itens e baixa o saldo real
                        foreach ($materiais as $material) {

                            \App\Models\MovimentacaoItem::create([
                                'movimentacao_id' => $movimentacao->id,
                                'produto_id' => $material->produto_id,
                                'quantity' => $material->quantidade,
                            ]);

                            $estoque = \App\Models\Estoque::firstOrCreate([
                                'tenant_id' => $os->tenant_id,
                                'produto_id' => $material->produto_id,
                                'local_estoque_id' => $localId, // Usa a origem correta
                            ]);

                            $estoque->quantity -= $material->quantidade;
                            $estoque->save();
                        }
                    }
                }
            }
        });
    }
}