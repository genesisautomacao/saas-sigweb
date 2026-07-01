<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fabricante extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'fabricantes';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'cnpj', 'pais', 'site',
    ];

    public function marcas()
    {
        return $this->hasMany(Marca::class);
    }
}
