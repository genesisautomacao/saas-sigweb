<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoEntidade extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'tipo_entidades';

    protected $fillable = ['tenant_id', 'sequential_id', 'name'];

    public function entidades()
    {
        return $this->hasMany(Entidade::class);
    }
}
