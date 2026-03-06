<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ZoneamentoRegra extends Model
{
    use BelongsToTenant;

    protected $table = 'zoneamento_regras';

    protected $fillable = ['tenant_id', 'zona_sigla', 'classificacao', 'status'];
}