<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpmnFluxo extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'nome', 'descricao', 'xml_diagrama', 'ativo', 'perfis_autorizados', 'modo_imovel'];

    protected $casts = [
        'ativo' => 'boolean',
        'perfis_autorizados' => 'array',
    ];

    /** Modos de seleção de imóvel (item 2). */
    public const MODO_IMOVEL = ['nenhum', 'mapa', 'busca'];

    public function etapas() {
        return $this->hasMany(BpmnEtapa::class);
    }
}