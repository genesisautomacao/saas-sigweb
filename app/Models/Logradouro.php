<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class Logradouro extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'code', 'name', 'geo'];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        $result = DB::table('logradouros')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Envelopa em ST_Multi para aceitar LineStrings simples e transformar em MultiLineString
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }
}