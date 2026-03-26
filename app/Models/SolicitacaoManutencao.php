<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; 

class SolicitacaoManutencao extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'solicitacoes_manutencao';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'asset_type',
        'asset_id',
        'tipo_servico',
        'prioridade',
        'status',
        'solicitante_nome',
        'observacao',
        'foto_ocorrencia',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Se estiver criando e não tiver um 'code' (UUID), o sistema gera sozinho!
            if (empty($model->code)) {
                $model->code = (string) Str::uuid();
            }
        });
    }

    /**
     * O Relacionamento Polimórfico Mágico!
     * Pode retornar um Poste, uma Árvore, um Lote...
     */
    public function asset()
    {
        return $this->morphTo();
    }

    public function ordensServico()
    {
        return $this->hasMany(OrdemServico::class, 'solicitacao_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }
}