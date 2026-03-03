<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class Lote extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'quadra_id', 'zona_id', 'code', 'numero_lote', 'area_geo', 'main_facade_length', 'geo'];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        // O 6 mantém o arquivo JSON leve sem perder precisão
        $result = DB::table('lotes')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }
}