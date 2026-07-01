<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicoSocial extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'servico_sociais';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'entidade_id', 'descricao',
    ];

    public function entidade()
    {
        return $this->belongsTo(Entidade::class);
    }
}
