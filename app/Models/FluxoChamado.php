<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FluxoChamado extends Model
{
    use BelongsToTenant, HasTenantSequentialId, SoftDeletes;

    protected $table = 'fluxos_chamado';

    protected $fillable = ['tenant_id', 'sequential_id', 'nome', 'categoria_chamado_id', 'boletim', 'ativo', 'ordem'];

    protected $casts = ['boletim' => 'array', 'ativo' => 'boolean'];

    public function categoria()
    {
        return $this->belongsTo(CategoriaChamado::class, 'categoria_chamado_id');
    }

    public function fases()
    {
        return $this->hasMany(FaseChamado::class, 'fluxo_chamado_id')->orderBy('ordem');
    }
}
