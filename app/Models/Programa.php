<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Programa extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'programas';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'descricao', 'data_inicio', 'data_fim',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim'    => 'date',
    ];

    public function eventos()
    {
        return $this->hasMany(Evento::class);
    }
}
