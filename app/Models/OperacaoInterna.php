<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperacaoInterna extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'operacao_internas';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'sentido', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function movimentacoes()
    {
        return $this->hasMany(EstoqueMovimentacao::class);
    }
}
