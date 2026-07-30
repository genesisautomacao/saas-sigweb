<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * R67-2 — personalização de um campo PADRÃO do sistema para um município:
 * rótulo, lista de valores, visibilidade e exigência na coleta.
 * A coluna no banco não muda — só a apresentação. Sem linha = padrão do sistema.
 */
class CampoDominio extends Model
{
    use BelongsToTenant;

    protected $table = 'campo_dominios';

    protected $fillable = [
        'tenant_id', 'entidade', 'campo', 'label', 'opcoes',
        'visivel', 'na_coleta', 'obrigatorio_coleta',
    ];

    protected $casts = [
        'opcoes' => 'array',
        'visivel' => 'boolean',
        'na_coleta' => 'boolean',
        'obrigatorio_coleta' => 'boolean',
    ];
}
