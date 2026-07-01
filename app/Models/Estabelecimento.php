<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estabelecimento extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'estabelecimentos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'cnpj', 'telefone', 'email', 'endereco',
    ];

    public function locais()
    {
        return $this->hasMany(LocalEstoque::class);
    }
}
