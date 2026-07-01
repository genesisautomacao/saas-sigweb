<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Embalagem extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'embalagens';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'quantidade', 'unidade_medida_id',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
    ];

    public function unidadeMedida()
    {
        return $this->belongsTo(UnidadeMedida::class);
    }
}
