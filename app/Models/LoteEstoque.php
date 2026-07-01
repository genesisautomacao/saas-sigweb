<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoteEstoque extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'lote_estoques';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'numero_lote', 'produto_id', 'fornecedor_id',
        'data_fabricacao', 'data_validade', 'data_garantia',
        'quantidade_inicial', 'observacao',
    ];

    protected $casts = [
        'data_fabricacao'    => 'date',
        'data_validade'      => 'date',
        'data_garantia'      => 'date',
        'quantidade_inicial' => 'decimal:3',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function estoques()
    {
        return $this->hasMany(Estoque::class);
    }

    /** Dias restantes de garantia (negativo = vencida). */
    public function getDiasGarantiaAttribute(): ?int
    {
        if (!$this->data_garantia) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->data_garantia, false);
    }
}
