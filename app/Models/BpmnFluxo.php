<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpmnFluxo extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, SoftDeletes;

    protected $fillable = ['tenant_id', 'sequential_id', 'nome', 'cor', 'descricao', 'xml_diagrama', 'ativo', 'perfis_autorizados', 'modo_imovel', 'exige_requerimento', 'template_requerimento'];

    protected $casts = [
        'ativo' => 'boolean',
        'perfis_autorizados' => 'array',
        'exige_requerimento' => 'boolean',
    ];

    /** Modos de seleção de imóvel (item 2). */
    public const MODO_IMOVEL = ['nenhum', 'mapa', 'busca'];

    public function etapas()
    {
        return $this->hasMany(BpmnEtapa::class);
    }

    /**
     * PD-1 — etapa em que o requerimento assinado é exigido: a etapa do SOLICITANTE marcada
     * com `exige_requerimento` (a primeira por ordem, se houver mais de uma); sem nenhuma
     * marcada, vale a 1ª etapa do fluxo (comportamento original — requerimento na abertura).
     */
    public function etapaRequerimento(): ?BpmnEtapa
    {
        if (! $this->exige_requerimento) {
            return null;
        }

        return $this->etapas()
            ->where('executor', 'solicitante')
            ->where('exige_requerimento', true)
            ->orderBy('ordem')->orderBy('id')
            ->first()
            ?? $this->etapas()->orderBy('ordem')->orderBy('id')->first();
    }
}
