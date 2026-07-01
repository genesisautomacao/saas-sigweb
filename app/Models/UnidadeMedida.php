<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadeMedida extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'unidade_medidas';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'sigla',
    ];

    public function embalagens()
    {
        return $this->hasMany(Embalagem::class);
    }
}
