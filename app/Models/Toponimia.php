<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Toponimia extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = [
        'tenant_id', 'sequential_id', 'texto', 'lat', 'lon', 'estilo',
    ];

    protected $casts = [
        'estilo' => 'array',
        'lat'    => 'float',
        'lon'    => 'float',
    ];

    protected $hidden  = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute(): ?object
    {
        if (!isset($this->attributes['id'])) return null;

        $result = DB::table('toponimias')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute(mixed $value): void
    {
        // Recebe um GeoJSON Point ou [lon, lat] simples
        if (is_array($value) && isset($value['type'])) {
            $lon = $value['coordinates'][0];
            $lat = $value['coordinates'][1];
        } else {
            $lon = $value[0] ?? $this->lon;
            $lat = $value[1] ?? $this->lat;
        }
        $this->attributes['geo'] = DB::raw("ST_SetSRID(ST_MakePoint({$lon},{$lat}),4326)");
    }
}
