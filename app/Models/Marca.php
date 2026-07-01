<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marca extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'marcas';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'fabricante_id',
    ];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }

    public function fabricante()
    {
        return $this->belongsTo(Fabricante::class);
    }
}