<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PgvCub extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pgv_cubs';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'tipologia', 'tipo_estrutura', 'padrao', 'coeficiente', 'valor_m2', 'mes_referencia',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:4',
        'valor_m2'    => 'decimal:2',
    ];
}
