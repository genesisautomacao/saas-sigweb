<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessoAnexo extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'processo_digital_id', 'etapa_id', 'usuario_id', 'nome_arquivo', 'caminho_arquivo', 'tipo_anexo', 'versao', 'anexo_origem_id'];

    public function processo() {
        return $this->belongsTo(ProcessoDigital::class, 'processo_digital_id');
    }

    /** Anexo original de onde esta anotação derivou (item 222). */
    public function origem() {
        return $this->belongsTo(ProcessoAnexo::class, 'anexo_origem_id');
    }
}