<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Camada de imagem aérea (tiles XYZ) de uma prefeitura — ver migration.
 *
 * ⚠️ SEM o trait BelongsToTenant de propósito: este model é gerenciado pelo
 * painel /admin (fora do contexto de tenant do Filament) e consumido pela API
 * via relação do Tenant — o global scope do trait quebraria o admin.
 */
class Ortofoto extends Model
{
    /** Tamanhos de tile aceitos (px). 512 = padrão de geração do SIGWEB; 256 = convenção XYZ de terceiros. */
    public const TILE_SIZES = [256, 512];

    protected $fillable = ['tenant_id', 'nome', 'url', 'tile_size', 'ordem', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
        'tile_size' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
