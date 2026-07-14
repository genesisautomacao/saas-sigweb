<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpmnEtapa extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, SoftDeletes;

    protected $fillable = ['tenant_id', 'sequential_id', 'bpmn_fluxo_id', 'nome', 'executor', 'setor_id', 'ordem', 'codigo_etapa_bpmn', 'cor_mapa', 'tempo_medio_minutos', 'usuarios_autorizados', 'campos_formulario', 'exige_requerimento'];

    protected $casts = [
        'usuarios_autorizados' => 'array',
        'campos_formulario' => 'array',
        'exige_requerimento' => 'boolean',
    ];

    public function fluxo()
    {
        return $this->belongsTo(BpmnFluxo::class, 'bpmn_fluxo_id');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }
}
