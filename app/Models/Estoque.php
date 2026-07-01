<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estoque extends Model
{
    use HasFactory, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'estoques';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'produto_id',
        'local_estoque_id',
        'tipo_estoque_id',
        'lote_estoque_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function localEstoque()
    {
        return $this->belongsTo(LocalEstoque::class, 'local_estoque_id');
    }

    public function tipoEstoque()
    {
        return $this->belongsTo(TipoEstoque::class);
    }

    public function loteEstoque()
    {
        return $this->belongsTo(LoteEstoque::class);
    }
}