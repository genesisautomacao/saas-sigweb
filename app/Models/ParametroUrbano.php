<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ParametroUrbano extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'parametros_urbanos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'zona_id',
        'area_minima',
        'area_maxima',
        'testada_minima',
        'testada_maxima',
    ];

    protected static function booted()
    {
        static::creating(fn ($model) => $model->code = (string) Str::uuid());
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }
}