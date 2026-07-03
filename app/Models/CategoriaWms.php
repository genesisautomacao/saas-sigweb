<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaWms extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'categorias_wms';

    protected $fillable = ['tenant_id', 'nome', 'pai_id', 'ordem'];

    public function pai()
    {
        return $this->belongsTo(CategoriaWms::class, 'pai_id');
    }

    public function filhos()
    {
        return $this->hasMany(CategoriaWms::class, 'pai_id');
    }

    public function fontes()
    {
        return $this->hasMany(FonteWms::class, 'categoria_wms_id');
    }
}
