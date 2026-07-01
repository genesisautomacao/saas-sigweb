<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'fornecedores';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'cnpj', 'telefone', 'email', 'contato', 'endereco',
    ];

    public function lotes()
    {
        return $this->hasMany(LoteEstoque::class);
    }
}
