<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessoDigital extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'processos_digitais';

    protected $fillable = ['tenant_id', 'sequential_id', 'codigo_processo', 'requerente_id', 'lote_id', 'bpmn_fluxo_id', 'etapa_atual_id', 'etapa_retorno_id', 'analista_id', 'status', 'dados_formulario'];

    protected $casts = [
        'dados_formulario' => 'array',
    ];

    // Dentro da classe App\Models\ProcessoDigital

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requerente() {
        return $this->belongsTo(User::class, 'requerente_id');
    }

    public function analista() {
        return $this->belongsTo(User::class, 'analista_id');
    }

    public function lote() {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function fluxo() {
        return $this->belongsTo(BpmnFluxo::class, 'bpmn_fluxo_id');
    }

    public function etapaAtual() {
        return $this->belongsTo(BpmnEtapa::class, 'etapa_atual_id');
    }

    // Etapa para onde o processo deve retornar quando a pendência da reprova for resolvida.
    public function etapaRetorno() {
        return $this->belongsTo(BpmnEtapa::class, 'etapa_retorno_id');
    }

    public function respostas() {
        return $this->hasMany(ProcessoResposta::class, 'processo_digital_id');
    }

    public function tramitacoes() {
        return $this->hasMany(ProcessoTramitacao::class, 'processo_digital_id');
    }
}