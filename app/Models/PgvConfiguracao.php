<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PgvConfiguracao extends Model
{
    use BelongsToTenant;

    protected $table = 'pgv_configuracoes';

    protected $fillable = [
        'tenant_id', 'fatores', 'lote_paradigma_id',
        'percentual_valor_venal', 'limite_aumento_iptu',
    ];

    protected $casts = [
        'fatores'                => 'array',
        'percentual_valor_venal' => 'decimal:2',
        'limite_aumento_iptu'    => 'decimal:2',
    ];

    public function loteParadigma()
    {
        return $this->belongsTo(Lote::class, 'lote_paradigma_id');
    }
}
