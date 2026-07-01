<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'eventos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'name', 'data', 'local', 'programa_id',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    public function programa()
    {
        return $this->belongsTo(Programa::class);
    }
}
