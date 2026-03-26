<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpmnEtapa extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'bpmn_fluxo_id', 'nome', 'codigo_etapa_bpmn', 'cor_mapa', 'tempo_medio_minutos', 'perfis_autorizados', 'campos_formulario'];

    protected $casts = [
        'perfis_autorizados' => 'array',
        'campos_formulario' => 'array',
    ];

    public function fluxo() {
        return $this->belongsTo(BpmnFluxo::class, 'bpmn_fluxo_id');
    }
}