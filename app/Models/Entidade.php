<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entidade extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'entidades';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'tipo_entidade_id', 'cnpj', 'telefone', 'email', 'endereco',
    ];

    public function tipoEntidade()
    {
        return $this->belongsTo(TipoEntidade::class);
    }

    public function servicos()
    {
        return $this->hasMany(ServicoSocial::class);
    }
}
