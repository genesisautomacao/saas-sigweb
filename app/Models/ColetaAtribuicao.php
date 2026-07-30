<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * R67-4 — região de trabalho de um cadastrador (quadras/bairros) num período.
 * Consumida por ColetaRegiaoService para filtrar o pull/nearest do app.
 */
class ColetaAtribuicao extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'coleta_atribuicoes';

    protected $fillable = [
        'tenant_id', 'user_id', 'data_inicio', 'data_fim',
        'quadra_ids', 'bairro_ids', 'ativo', 'observacao',
    ];

    protected $casts = [
        'quadra_ids' => 'array',
        'bairro_ids' => 'array',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'ativo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** Atribuições valendo HOJE (ativas e dentro do período). */
    public function scopeVigentes(Builder $query): Builder
    {
        $hoje = now()->toDateString();

        return $query->where('ativo', true)
            ->whereDate('data_inicio', '<=', $hoje)
            ->where(fn (Builder $q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $hoje));
    }
}
