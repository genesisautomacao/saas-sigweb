<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoPatrimonio extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'tipo_patrimonios';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'description',
    ];

    public function patrimoniosPublicos()
    {
        return $this->hasMany(PatrimonioPublico::class, 'tipo_patrimonio_id');
    }
}