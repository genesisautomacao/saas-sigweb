<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Respostas de formulário por etapa de um Processo Digital (fluxo híbrido).
 * Cada envio (cidadão ou analista) gera uma linha, sem sobrescrever. Ver processosConceito.md §9.2.
 */
class ProcessoResposta extends Model
{
    use BelongsToTenant;

    protected $table = 'processo_respostas';

    protected $fillable = [
        'tenant_id',
        'processo_digital_id',
        'bpmn_etapa_id',
        'usuario_id',
        'dados',
    ];

    protected $casts = [
        'dados' => 'array',
    ];

    public function processo()
    {
        return $this->belongsTo(ProcessoDigital::class, 'processo_digital_id');
    }

    public function etapa()
    {
        return $this->belongsTo(BpmnEtapa::class, 'bpmn_etapa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
