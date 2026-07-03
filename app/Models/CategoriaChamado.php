<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaChamado extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'categorias_chamado';

    protected $fillable = ['tenant_id', 'nome', 'pai_id', 'cor', 'icone', 'privada', 'ordem'];

    protected $casts = ['privada' => 'boolean'];

    public function pai()
    {
        return $this->belongsTo(CategoriaChamado::class, 'pai_id');
    }

    public function filhos()
    {
        return $this->hasMany(CategoriaChamado::class, 'pai_id');
    }

    public function fluxos()
    {
        return $this->hasMany(FluxoChamado::class, 'categoria_chamado_id');
    }
}
