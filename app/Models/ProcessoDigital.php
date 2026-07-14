<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessoDigital extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, SoftDeletes;

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

    public function requerente()
    {
        return $this->belongsTo(User::class, 'requerente_id');
    }

    public function analista()
    {
        return $this->belongsTo(User::class, 'analista_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function fluxo()
    {
        return $this->belongsTo(BpmnFluxo::class, 'bpmn_fluxo_id');
    }

    public function etapaAtual()
    {
        return $this->belongsTo(BpmnEtapa::class, 'etapa_atual_id');
    }

    // Etapa para onde o processo deve retornar quando a pendência da reprova for resolvida.
    public function etapaRetorno()
    {
        return $this->belongsTo(BpmnEtapa::class, 'etapa_retorno_id');
    }

    public function respostas()
    {
        return $this->hasMany(ProcessoResposta::class, 'processo_digital_id');
    }

    public function anexos()
    {
        return $this->hasMany(ProcessoAnexo::class, 'processo_digital_id');
    }

    /**
     * Gate do requerimento assinado (PD-1): o processo NÃO segue enquanto o requerimento
     * assinado não existir/estiver reprovado. A exigência dispara na etapa configurada
     * (`BpmnFluxo::etapaRequerimento()` — etapa marcada ou, sem marcação, a 1ª do fluxo),
     * para as variáveis do template poderem vir de etapas posteriores à abertura.
     */
    public function precisaRequerimentoAssinado(): bool
    {
        if (! $this->fluxo?->exige_requerimento) {
            return false;
        }

        $ultimo = $this->anexos()
            ->where('tipo_anexo', 'requerimento_assinado')
            ->orderByDesc('id')
            ->first();

        // Já assinado: só volta a exigir se o analista reprovou a assinatura
        // (aí o gate reaparece em QUALQUER etapa do solicitante, para reassinar na correção).
        if ($ultimo) {
            return $ultimo->status_analise === 'reprovado';
        }

        // Nunca assinado: exige apenas quando o processo está NA etapa do requerimento.
        $etapaRequerimento = $this->fluxo->etapaRequerimento();

        return $etapaRequerimento && (int) $this->etapa_atual_id === (int) $etapaRequerimento->id;
    }

    public function tramitacoes()
    {
        return $this->hasMany(ProcessoTramitacao::class, 'processo_digital_id');
    }
}
