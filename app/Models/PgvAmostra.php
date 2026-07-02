<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PgvAmostra extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pgv_amostras';

    protected $fillable = [
        'tenant_id', 'sequential_id', 'lote_id', 'valor_m2',
        'idade_aparente', 'estado_conservacao', 'tipologia', 'padrao_cub',
        'area_terreno', 'area_edificacao', 'espuria', 'observacao', 'geo',
    ];

    protected $casts = [
        'valor_m2'        => 'decimal:2',
        'area_terreno'    => 'decimal:2',
        'area_edificacao' => 'decimal:2',
        'espuria'         => 'boolean',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }
        $r = DB::table('pgv_amostras')->select(DB::raw('ST_AsGeoJSON(geo) as g'))->where('id', $this->attributes['id'])->first();
        return $r && $r->g ? json_decode($r->g) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = empty($value) ? null : DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }
}
