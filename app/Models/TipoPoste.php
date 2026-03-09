<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoPoste extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'tipos_poste';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
    ];

    public function postes(): HasMany
    {
        return $this->hasMany(Poste::class, 'tipo_poste_id');
    }
}