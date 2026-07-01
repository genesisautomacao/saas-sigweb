<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class LoteTestada extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'lote_testadas';

    protected $fillable = [
        'tenant_id', 'lote_id', 'logradouro_id', 'secao_logradouro_id',
        'tipo', 'comprimento', 'geo',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected $casts = [
        'comprimento' => 'decimal:2',
    ];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || empty($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('lote_testadas')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }

    public function secaoLogradouro()
    {
        return $this->belongsTo(SecaoLogradouro::class, 'secao_logradouro_id');
    }
}
