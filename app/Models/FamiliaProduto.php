<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamiliaProduto extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'familia_produtos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'description',
    ];

    public function produtos()
    {
        return $this->hasMany(Produto::class);
    }
}
