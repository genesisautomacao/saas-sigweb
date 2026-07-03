<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FonteWms extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'fontes_wms';

    protected $fillable = [
        'tenant_id',
        'categoria_wms_id',
        'nome',
        'url',
        'camadas',
        'formato',
        'opacidade',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'opacidade' => 'integer',
    ];

    public function categoria()
    {
        return $this->belongsTo(CategoriaWms::class, 'categoria_wms_id');
    }
}
