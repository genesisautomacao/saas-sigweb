<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PgvParametro extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pgv_parametros';

    protected $fillable = ['tenant_id', 'sequential_id', 'nome_padrao', 'valor_m2_terreno', 'valor_m2_edificacao', 'fatores_adicionais'];
    
    protected $casts = [
        'fatores_adicionais' => 'array',
    ];

    public function setores() {
        return $this->hasMany(SetorFiscal::class, 'pgv_parametro_id');
    }
}