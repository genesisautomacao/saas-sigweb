<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessoTramitacao extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'processo_tramitacoes';

    protected $fillable = ['tenant_id', 'sequential_id', 'processo_digital_id', 'etapa_origem_id', 'etapa_destino_id', 'usuario_id', 'parecer', 'status_parecer'];

    public function processo() {
        return $this->belongsTo(ProcessoDigital::class, 'processo_digital_id');
    }
    
    public function responsavel() {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}