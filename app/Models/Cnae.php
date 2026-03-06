<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Cnae extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'codigo', 'descricao', 'classificacoes'];

    protected $casts = [
        'classificacoes' => 'array',
    ];
}