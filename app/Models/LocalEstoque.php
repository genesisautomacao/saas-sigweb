<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalEstoque extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'locais_estoque';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'description',
    ];

    public function estoques()
    {
        return $this->hasMany(Estoque::class, 'local_estoque_id');
    }
}