<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaseChamado extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'fases_chamado';

    protected $fillable = [
        'tenant_id', 'fluxo_chamado_id', 'nome', 'cor',
        'aviso_duracao', 'duracao_minutos', 'ordem', 'encerramento', 'usuarios_autorizados',
    ];

    protected $casts = [
        'aviso_duracao' => 'boolean',
        'encerramento' => 'boolean',
        'usuarios_autorizados' => 'array',
    ];

    public function fluxo()
    {
        return $this->belongsTo(FluxoChamado::class, 'fluxo_chamado_id');
    }
}
