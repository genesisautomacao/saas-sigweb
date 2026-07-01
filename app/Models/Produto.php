<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'produtos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'name',
        'sku',
        'description',
        'unit',
        'marca_id',
        'familia_produto_id',
        'unidade_medida_id',
        'embalagem_id',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_stock' => 'decimal:2',
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function familia()
    {
        return $this->belongsTo(FamiliaProduto::class, 'familia_produto_id');
    }

    public function unidadeMedida()
    {
        return $this->belongsTo(UnidadeMedida::class);
    }

    public function embalagem()
    {
        return $this->belongsTo(Embalagem::class);
    }

    public function estoques()
    {
        return $this->hasMany(Estoque::class);
    }

    public function lotes()
    {
        return $this->hasMany(LoteEstoque::class);
    }
}