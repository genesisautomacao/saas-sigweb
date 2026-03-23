<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoteValorHistorico extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'lote_valores_historicos';

    protected $fillable = ['tenant_id', 'sequential_id', 'lote_id', 'ano_vigente', 'valor_terreno', 'valor_edificacao', 'valor_total', 'setor_fiscal_id'];

    public function lote() {
        return $this->belongsTo(Lote::class);
    }
    
    public function setor() {
        return $this->belongsTo(SetorFiscal::class, 'setor_fiscal_id');
    }
}