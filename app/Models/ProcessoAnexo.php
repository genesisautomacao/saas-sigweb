<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessoAnexo extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, SoftDeletes;

    protected $fillable = ['tenant_id', 'sequential_id', 'processo_digital_id', 'etapa_id', 'campo_slug', 'usuario_id', 'nome_arquivo', 'caminho_arquivo', 'tipo_anexo', 'status_analise', 'observacao_analise', 'analisado_por_id', 'analisado_em', 'versao', 'anexo_origem_id'];

    protected $casts = [
        'analisado_em' => 'datetime',
    ];

    /** Tipos de anexo sujeitos ao checklist de análise por item (PD-2). */
    public const TIPOS_ANALISAVEIS = ['formulario', 'requerimento_assinado'];

    public function processo()
    {
        return $this->belongsTo(ProcessoDigital::class, 'processo_digital_id');
    }

    /** Anexo original de onde esta anotação derivou (item 222). */
    public function origem()
    {
        return $this->belongsTo(ProcessoAnexo::class, 'anexo_origem_id');
    }

    /** Analista que aprovou/reprovou este anexo no checklist (PD-2). */
    public function analisadoPor()
    {
        return $this->belongsTo(User::class, 'analisado_por_id')->withTrashed();
    }

    /** ID da cadeia de versões deste anexo (o original ou ele mesmo). */
    public function cadeiaId(): int
    {
        return $this->anexo_origem_id ?? $this->id;
    }
}
