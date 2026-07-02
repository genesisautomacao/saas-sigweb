<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PgvDepreciacao extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pgv_depreciacoes';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'estado_conservacao', 'idade_de', 'idade_ate', 'coeficiente',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:4',
    ];
}
